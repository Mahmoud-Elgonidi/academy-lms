<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Discounts\InvoiceDiscounts;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\RevenueBoard;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\ShiftSchedule;

// The revenue board's renewals coming (docs/architecture/03, section 13): read in the same few
// queries however many courses are due (a 400-student academy has hundreds), each priced as
// InvoiceDiscounts prices one invoice: the family discount for the child's place, and the coupon.

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Riyadh.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create();
    $this->kids = User::factory()->withRole(Role::Student)->count(4)->create(['timezone' => 'Asia/Riyadh']);
    foreach ($this->kids as $kid) {
        app(PeopleApi::class)->linkGuardian($this->guardian->id, $kid->id);
    }
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([6, 2] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '15:00', 'duration_min' => 360]);
    }
    BillingSettings::query()->create(['tax_rate_bp' => 1500, 'prices_include_tax' => true, 'invoice_prefix' => 'NR-']);
    app(SaveSiblingDiscount::class)->handle(10, 15, 0);   // no family invoices: each course on its own
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
    $this->plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    app(SaveCoupon::class)->handle(null, ['code' => 'WELCOME', 'kind' => 'percent', 'percent' => 10, 'blocks' => 3], $this->accountant->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A child's eight weeks from Tuesday 6 and Saturday 10 October at this hour (Cairo), paid: due to renew within the month. */
function forecastCourse(int $kid, int $hour, ?string $coupon = null): void
{
    $t = test();
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse(sprintf('%s %02d:00', $first, $hour), 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $student = $t->kids[$kid]->id;
    $batch = app(PlaceBookingHold::class)->handle($t->teacher->id, $student, $times, 30, $t->scheduler->id);
    $booked = app(EnrollStudent::class)->handle($batch, $student, $t->quran, new EnrollmentTerms($t->plan->id, $t->guardian->id, $t->gulf->id, $coupon), $t->scheduler->id);
    $invoice = Invoice::query()->findOrFail($booked->invoice->id);
    app(RecordPayment::class)->handle($invoice->id, 'bank_transfer', $invoice->outstanding(), 'T-1', CarbonImmutable::now(), null, $t->accountant->id);
}

function forecastQueries(): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $forecast = app(RevenueBoard::class)->forecast('SAR', 30);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [$forecast['count'], $queries];
}

it('reads the renewals coming in the same queries however many courses are due', function () {
    forecastCourse(0, 15, 'WELCOME');
    [$one, $queries] = forecastQueries();

    forecastCourse(1, 16);
    forecastCourse(2, 17);
    forecastCourse(3, 18);

    expect($one)->toBe(1)
        ->and(forecastQueries())->toBe([4, $queries]);
});

it('prices each coming renewal as its own invoice would be: the child\'s place and the coupon', function () {
    forecastCourse(0, 15, 'WELCOME');
    forecastCourse(1, 16);
    forecastCourse(2, 17);
    $discounts = app(InvoiceDiscounts::class);
    $subscriptions = Subscription::query()->orderBy('created_at')->get();

    $batch = $discounts->blockLines($subscriptions);

    foreach ($subscriptions as $subscription) {
        expect($batch[$subscription->id])->toBe($discounts->lines($subscription, $subscription->amount_minor, withCoupon: true));
    }
    // The first child: the coupon; the second and third: their family discount.
    expect(array_map(fn (Subscription $s) => array_map(fn (array $l) => [$l['meta']['source'], $l['amount_minor']], $batch[$s->id]), $subscriptions->all()))
        ->toBe([[['coupon', -4000]], [['sibling', -4000]], [['sibling', -6000]]]);
});
