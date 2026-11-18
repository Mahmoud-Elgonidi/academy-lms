<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * A server deploys with DEMO_DATA=0 once its demo exists: every deploy
 * brings the demo up to date (the demo's plan takes the AI helpers' area),
 * each step only once. Nothing of the Quran is written: it moved to its own
 * project, and its old tables stay empty here.
 */

function inDemo(callable $read): mixed
{
    return app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('demo'), $read);
}

it('does nothing on a server without the demo academy', function () {
    $this->artisan('db:seed', ['--class' => DemoCatchUpSeeder::class, '--force' => true])
        ->expectsOutputToContain('No demo academy here')->assertSuccessful();
});

it('brings a demo up to date once, and writes nothing of the Quran', function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll', 'groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site']]);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    // A demo older than the AI helpers: its plan without their area.
    $demo = app(TenantDirectory::class)->bySlug('demo');
    $before = SaasSubscription::currentFor($demo->id);
    AuditActor::use(new AuditActor('system', null, null));
    app(ChangeAcademyPlan::class)->handle($demo->id, $before->plan_id, 'خطة الديمو قبل المساعدات', $before->limits);
    AuditActor::forget();
    $quran = fn () => inDemo(fn () => array_sum(array_map(fn (string $table) => DB::table($table)->count(), [
        'quran_lesson_passages', 'hifz_pages', 'hifz_reviews', 'recitation_mistakes', 'quran_programs', 'quran_plans', 'quran_certificates', 'submission_checks',
    ])));

    $this->seed(DemoCatchUpSeeder::class);
    expect(SaasSubscription::currentFor($demo->id)->features_on)->toBe(['ai'])
        ->and($quran())->toBe(0)
        ->and(inDemo(fn () => DB::table('subjects')->where('kind', '!=', 'general')->count()))->toBe(0);

    // Again, as on the next deploy: nothing new.
    $subscriptions = SaasSubscription::query()->where('academy_id', $demo->id)->count();
    $this->seed(DemoCatchUpSeeder::class);
    expect(SaasSubscription::query()->where('academy_id', $demo->id)->count())->toBe($subscriptions)
        ->and($quran())->toBe(0);
});
