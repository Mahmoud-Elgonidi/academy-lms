<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Collections\FollowUpCollections;
use Modules\Billing\Database\Seeders\DemoCollectionSeeder;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\Invoice;
use Modules\Communication\Models\Notification;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Texts\QuranText;

/*
 * The collection timeline in the demo (docs/architecture/02, section 27, J5a):
 * Hala's family invoice falls due tomorrow at 18:00 on her clock, so the
 * next morning's billing:follow-up reminds her; a demo already there gets the
 * same once.
 */

beforeEach(function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll', 'quran', 'groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site']]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/none')));
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('leaves Hala\'s family invoice due tomorrow, once, and reminds her the next morning', function () {
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    $this->actAsTenant(app(TenantDirectory::class)->bySlug('demo'));
    $hala = DB::table('users')->where('email', 'hala.guardian@demo.test')->value('id');

    $marked = CollectionStep::query()->where('key', 'like', DemoCollectionSeeder::MARKER.':%')->sole();
    $invoice = Invoice::query()->findOrFail($marked->invoice_id);
    $tomorrow = CarbonImmutable::now()->setTimezone('Asia/Riyadh')->addDay();
    expect($invoice->status)->toBe('issued')
        ->and($invoice->due_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i'))->toBe($tomorrow->format('Y-m-d').' 18:00');

    // Once: a later catch-up leaves it as it is.
    $this->clearTenant();
    $this->seed(DemoCatchUpSeeder::class);
    $this->actAsTenant(app(TenantDirectory::class)->bySlug('demo'));
    expect(Invoice::query()->findOrFail($invoice->id)->due_at->equalTo($invoice->due_at))->toBeTrue()
        ->and(CollectionStep::query()->where('key', 'like', DemoCollectionSeeder::MARKER.':%')->count())->toBe(1);

    // From ten this morning on her clock, she hears it is due tomorrow.
    CarbonImmutable::setTestNow(CarbonImmutable::now()->setTimezone('Asia/Riyadh')->setTime(10, 0)->utc());
    app(FollowUpCollections::class)->handle();
    expect(Notification::query()->where('user_id', $hala)->where('template_key', 'billing.due_tomorrow')->count())->toBe(1);
});
