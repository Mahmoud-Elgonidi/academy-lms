<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\ExportRevenue;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Filament\Pages\Revenue;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Queries\RevenueBoard;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Riyadh, the academy's clock.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->arabic = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create(['name' => 'Hala']);
    $this->kids = User::factory()->withRole(Role::Student)->count(3)->sequence(['name' => 'Ziad'], ['name' => 'Maryam'], ['name' => 'Yousef'])->create(['timezone' => 'Asia/Riyadh']);
    foreach ($this->kids as $kid) {
        app(PeopleApi::class)->linkGuardian($this->guardian->id, $kid->id);
    }

    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([6, 2] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '15:00', 'duration_min' => 360]);
    }

    // VAT 15% inside the prices.
    BillingSettings::query()->create(['tax_rate_bp' => 1500, 'prices_include_tax' => true, 'invoice_prefix' => 'NR-']);
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]);
    $this->usa = PriceRegion::query()->create(['name_ar' => 'أمريكا', 'name_en' => 'US', 'currency' => 'USD']);
    $this->plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 sessions', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    $this->plan->prices()->create(['price_region_id' => $this->usa->id, 'amount_minor' => 12000, 'currency' => 'USD']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** Enrols a child with the teacher for eight weeks, from Tuesday 6 and Saturday 10 October at this hour, Cairo time. */
function revenueEnrol(int $kid, string $subjectId, int $hour = 16, ?string $regionId = null): EnrollmentBooked
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

    return app(EnrollStudent::class)->handle($batch, $student, $subjectId, new EnrollmentTerms($t->plan->id, $t->guardian->id, $regionId ?? $t->gulf->id), $t->scheduler->id);
}

function revenuePay(string $invoiceId, string $method = 'bank_transfer', ?int $minor = null, ?CarbonImmutable $at = null): Invoice
{
    $invoice = Invoice::query()->findOrFail($invoiceId);
    $amount = $minor === null ? $invoice->outstanding() : Money::of($minor, $invoice->currency);

    return app(RecordPayment::class)->handle($invoice->id, $method, $amount, 'T-1', $at ?? CarbonImmutable::now(), null, test()->accountant->id);
}

/** Uses up the reserved minutes of an enrollment's first N funded lessons, as if they were taught. */
function revenueTeach(string $enrollmentId, int $count): void
{
    Session::query()->where('enrollment_id', $enrollmentId)->where('funding_status', FundingStatus::Funded->value)
        ->whereExists(fn ($q) => $q->from('credit_reservations as r')->whereColumn('r.session_id', 'sessions.id')->where('r.status', 'held'))
        ->orderBy('starts_at')->limit($count)->get()
        ->each(fn (Session $s) => app(CreditsApi::class)->consume($s->id, "session:{$s->id}:consume"));
}

it('counts what came in on the academy\'s days, with its VAT, against what was invoiced and is still owed', function () {
    $ziad = revenueEnrol(0, $this->quran, hour: 16);
    $maryam = revenueEnrol(1, $this->arabic, hour: 17);
    // 00:30 on 1 October in Riyadh is still 30 September in UTC: it is October's money.
    revenuePay($ziad->invoice->id, at: CarbonImmutable::parse('2026-09-30 21:30', 'UTC'));
    revenuePay($maryam->invoice->id, 'cash', 20000, CarbonImmutable::parse('2026-10-04 15:00', 'UTC'));

    $board = app(RevenueBoard::class);
    $october = $board->summary($board->period('this_month'), 'SAR');
    $september = $board->summary($board->period('last_month'), 'SAR');

    expect($october['collected']->minor)->toBe(60000)
        ->and($october['tax']->minor)->toBe(5217 + 2609)             // 15/115 of each payment, in proportion to its invoice's VAT
        ->and($october['net']->minor)->toBe(60000 - 7826)
        ->and([$october['payments'], $october['payers']])->toBe([2, 1])
        ->and([$october['invoiced']->minor, $october['invoices']])->toBe([80000, 2])
        ->and([$october['outstanding']->minor, $october['outstanding_count']])->toBe([20000, 1])
        ->and($october['overdue']->minor)->toBe(0)
        ->and($october['collected_before']->minor)->toBe(0)
        ->and($september['collected']->minor)->toBe(0);

    // The chart: October's first day has Ziad's payment, the 4th Maryam's half; both invoices went out on the 5th.
    $bars = collect($board->series($board->period('this_month'), 'SAR'))->keyBy(fn ($b) => $b['first']->toDateString());
    expect($bars->count())->toBe(5)
        ->and($bars['2026-10-01']['collected'])->toBe(40000)
        ->and($bars['2026-10-04']['collected'])->toBe(20000)
        ->and($bars['2026-10-05']['invoiced'])->toBe(80000)
        ->and($bars->sum('collected'))->toBe(60000);

    // Late: three days after issue, the half still owed.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 07:00', 'UTC'));
    $late = $board->overdue('SAR');
    expect($board->summary($board->period('this_month'), 'SAR')['overdue']->minor)->toBe(20000)
        ->and($late)->toHaveCount(1)
        ->and($late[0]['number'])->toBe($maryam->invoice->number)
        ->and($late[0]['students'])->toBe('Maryam')
        ->and($late[0]['owed'])->toBe(20000)
        ->and($late[0]['days_late'])->toBe(1);
});

it('shares the money of a family invoice between its courses, and says how it was paid and what was sold', function () {
    app(SaveSiblingDiscount::class)->handle(10, 15);
    $ziad = revenueEnrol(0, $this->quran, hour: 16);
    $maryam = revenueEnrol(1, $this->arabic, hour: 17);
    revenuePay($ziad->invoice->id);
    revenuePay($maryam->invoice->id, 'cash');

    // Both reach their mark the same week: one family invoice, Ziad's block 400 and Maryam's 360 (her sibling discount).
    revenueTeach($maryam->enrollmentId, 4);
    revenueTeach($ziad->enrollmentId, 6);
    $family = Invoice::query()->where('status', 'issued')->sole();
    expect($family->total_minor)->toBe(76000);
    revenuePay($family->id, 'wallet', 38000, CarbonImmutable::now()->addHour());   // half of it, an hour later

    $board = app(RevenueBoard::class);
    $by = $board->breakdown($board->period('this_month'), 'SAR');
    $rows = fn (string $group) => collect($by[$group])->mapWithKeys(fn ($r) => [$r['key'] => $r['minor']])->all();

    expect($by['methods'])->toBe([
        ['key' => 'bank_transfer', 'label' => __('billing::billing.methods.bank_transfer'), 'minor' => 40000, 'count' => 1],
        ['key' => 'wallet', 'label' => __('billing::billing.methods.wallet'), 'minor' => 38000, 'count' => 1],
        ['key' => 'cash', 'label' => __('billing::billing.methods.cash'), 'minor' => 36000, 'count' => 1],
    ])
        // Maryam's first block had her discount too (360); the family half splits 400 : 360.
        ->and($rows('subjects'))->toBe([$this->quran => 40000 + 20000, $this->arabic => 36000 + 18000])
        ->and($rows('kinds'))->toBe(['new' => 76000, 'renewal' => 38000])
        ->and($rows('plans'))->toBe([$this->plan->id => 114000])
        ->and(array_sum($rows('subjects')))->toBe($board->summary($board->period('this_month'), 'SAR')['collected']->minor)
        ->and($board->summary($board->period('this_month'), 'SAR')['discounts']->minor)->toBe(4000 + 4000);

    $recent = $board->recentPayments($board->period('this_month'), 'SAR');
    expect($recent[0]['method'])->toBe(__('billing::billing.methods.wallet'))
        ->and($recent[0]['students'])->toContain('Maryam')->toContain('Ziad')
        ->and($recent[0]['minor'])->toBe(38000);
});

it('keeps each currency apart and leaves cancelled invoices out', function () {
    $ziad = revenueEnrol(0, $this->quran, hour: 16);
    $maryam = revenueEnrol(1, $this->arabic, hour: 17);
    $yousef = revenueEnrol(2, $this->quran, hour: 18, regionId: $this->usa->id);
    revenuePay($yousef->invoice->id);
    app(VoidInvoice::class)->handle($maryam->invoice->id, 'Enrolled twice', $this->accountant->id);

    $board = app(RevenueBoard::class);
    $sar = $board->summary($board->period('this_month'), 'SAR');
    $usd = $board->summary($board->period('this_month'), 'USD');

    expect($board->currencies())->toBe(['SAR', 'USD'])     // the one used most first
        ->and([$sar['collected']->minor, $sar['invoiced']->minor, $sar['invoices'], $sar['outstanding']->minor])->toBe([0, 40000, 1, 40000])
        ->and([$usd['collected']->minor, $usd['collected']->currency, $usd['invoiced']->minor])->toBe([12000, 'USD', 12000])
        ->and($ziad->invoice->total->currency)->toBe('SAR');
});

it('forecasts the renewals coming from each course\'s balance and pace, priced as the invoice will be', function () {
    app(SaveSiblingDiscount::class)->handle(10, 15, 0);   // no family invoices: each course on its own
    $ziad = revenueEnrol(0, $this->quran, hour: 16);
    $maryam = revenueEnrol(1, $this->arabic, hour: 17);
    $yousef = revenueEnrol(2, $this->quran, hour: 18);
    foreach ([$ziad, $maryam, $yousef] as $booked) {
        revenuePay($booked->invoice->id);
    }

    // Ziad: four lessons left, his mark is two, at two a week: due in a week. Maryam: renewed already (waiting).
    // Yousef: all eight left, three weeks away.
    revenueTeach($ziad->enrollmentId, 4);
    revenueTeach($maryam->enrollmentId, 6);

    $forecast = app(RevenueBoard::class)->forecast('SAR', 30);
    $items = collect($forecast['items'])->keyBy('student');

    expect($forecast['count'])->toBe(2)
        ->and($items->keys()->all())->toBe(['Ziad', 'Yousef'])
        ->and($items['Ziad']['on']->toDateString())->toBe('2026-10-12')
        ->and($items['Ziad']['minor'])->toBe(40000)
        ->and($items['Yousef']['on']->toDateString())->toBe('2026-10-26')
        ->and($items['Yousef']['minor'])->toBe(34000)          // the third child: 15% off
        ->and($items['Yousef']['payer'])->toBe('Hala')
        ->and($forecast['total']->minor)->toBe(74000)
        ->and(app(RevenueBoard::class)->forecast('SAR', 14)['count'])->toBe(1);
});

it('shows the screen to whoever may see the money, steps through periods and downloads the period as a spreadsheet', function () {
    $ziad = revenueEnrol(0, $this->quran, hour: 16);
    revenuePay($ziad->invoice->id, 'bank_transfer');
    $this->clearTenant();

    $this->actingAs($this->accountant)->get('http://noor.lms.test/admin/revenue')->assertOk()
        ->assertSee(__('billing::revenue.tiles.collected'))->assertSee(Money::of(40000, 'SAR')->format(), false)
        ->assertSee('data-live-channel="academy.'.$this->academy->id.'.revenue"', false)
        ->assertSee('Hala');
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/admin/revenue')->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->scheduler)->get('http://noor.lms.test/admin/revenue')->assertForbidden();

    $this->actAsTenant($this->academy);
    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Livewire::test(Revenue::class)
        ->assertSee('1 – 5 '.CarbonImmutable::parse('2026-10-05')->locale(app()->getLocale())->translatedFormat('F Y'))
        ->call('setRange', 'last_month')->assertSet('range', 'last_month')
        ->assertSee(__('billing::revenue.chart.empty'))
        ->call('setRange', 'custom')->assertSet('from', '2026-09-01')->assertSet('to', '2026-09-30')
        ->set('from', '2026-10-01')->set('to', '2026-10-05')
        ->assertDontSee(__('billing::revenue.chart.empty'))
        ->callAction('export')
        ->assertFileDownloaded('revenue-2026-10-01-2026-10-05-SAR.xlsx');

    app()->setLocale('en');
    $board = app(RevenueBoard::class);
    $path = app(ExportRevenue::class)->handle($board->period('this_month'), 'SAR');
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);

    $has = fn (array $row, array $values) => collect($values)->every(fn ($v) => in_array($v, $row));   // 400 may come back as 400.0
    expect(array_keys($sheets))->toBe([__('billing::revenue.export.summary'), __('billing::revenue.export.payments'), __('billing::revenue.export.invoices')])
        ->and($has($sheets[__('billing::revenue.export.payments')][1], [$ziad->invoice->number, 'Hala', 'Ziad · Quran', 400, 52.17, 'SAR']))->toBeTrue()
        ->and($has($sheets[__('billing::revenue.export.invoices')][1], [$ziad->invoice->number, __('billing::revenue.export.statuses.paid'), 400, 52.17]))->toBeTrue();
});

it('raises the academy\'s revenue signal when an invoice goes out or is paid, for those who may see the money', function () {
    config(['database.connections.revenue_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('revenue_listener')->getPdo();
    $pdo->exec('listen lms_live');
    $raised = function () use ($pdo) {
        $all = [];
        while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
            $all[] = $note['payload'];
        }

        return $all;
    };

    $ziad = revenueEnrol(0, $this->quran);
    expect($raised())->toContain("revenue:{$this->academy->id}");
    revenuePay($ziad->invoice->id);
    expect($raised())->toContain("revenue:{$this->academy->id}");

    // The channels are declared on the broadcaster in use at boot (none in tests): put them on Reverb's.
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
        'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test',
        'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
    ]]);
    foreach (Broadcast::driver('null')->getChannels() as $pattern => $callback) {
        Broadcast::driver('reverb')->channel($pattern, $callback);
    }
    $this->clearTenant();
    $auth = fn (User $user, string $academyId) => $this->flushSession()->actingAs($user)
        ->post('http://noor.lms.test/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-academy.{$academyId}.revenue"]);
    $auth($this->accountant, $this->academy->id)->assertOk();
    app('auth')->forgetGuards();
    $auth($this->teacher, $this->academy->id)->assertForbidden();
    app('auth')->forgetGuards();
    $auth($this->accountant, '01a11111-1111-7111-8111-111111111111')->assertForbidden();
});
