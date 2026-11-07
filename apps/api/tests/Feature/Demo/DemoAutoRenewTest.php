<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Collections\FollowUpCollections;
use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Database\Seeders\DemoAutoRenewSeeder;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\Renewals\NextRenewals;
use Modules\Communication\Models\Notification;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Texts\QuranText;

/*
 * Automatic renewal in the demo (J5b): the family whose next renewal is
 * nearest has a demo card saved and automatic renewal on, once; when the
 * renewal is issued, billing:follow-up tells them three days ahead.
 */

beforeEach(function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll', 'quran', 'groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site']]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/none')));
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('puts the family whose renewal is nearest on automatic renewal with a demo card, once, and tells them when it comes', function () {
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    $this->actAsTenant(app(TenantDirectory::class)->bySlug('demo'));

    $marked = CollectionStep::query()->where('key', 'like', DemoAutoRenewSeeder::MARKER.':%')->sole();
    $card = PaymentMethod::query()->sole();
    $subscription = Subscription::query()->with('plan')->findOrFail($marked->subscription_id);
    expect($card->payer_id)->toBe($marked->payer_id)->and($card->label())->toBe('Visa ****4242')
        ->and(BillingAccount::query()->where('payer_id', $card->payer_id)->value('auto_renew'))->toBeTrue()
        ->and(app(NextRenewals::class)->of([$subscription])[$subscription->id]->days)->toBeGreaterThanOrEqual(1);

    $this->clearTenant();
    $this->seed(DemoCatchUpSeeder::class);
    $this->actAsTenant(app(TenantDirectory::class)->bySlug('demo'));
    expect(PaymentMethod::query()->count())->toBe(1);

    // The renewal comes out: from nine on the payer's clock they hear it, and the charge is scheduled.
    $invoice = app(IssueRenewal::class)->handle($subscription);
    $tz = DB::table('users')->where('id', $card->payer_id)->value('timezone') ?: 'Asia/Riyadh';
    CarbonImmutable::setTestNow(CarbonImmutable::now()->setTimezone($tz)->addDay()->setTime(10, 0)->utc());
    app(FollowUpCollections::class)->handle();
    expect(AutoCharge::query()->where('invoice_id', $invoice->id)->sole()->status)->toBe('scheduled')
        ->and(Notification::query()->where('user_id', $card->payer_id)->where('template_key', 'billing.renewal_coming')->count())->toBe(1);
});
