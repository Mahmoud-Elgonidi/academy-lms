<?php

use Database\Seeders\DemoTrainingSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Tenancy\Audience;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * The demo's training office for adult professionals (docs/architecture/02,
 * section 29): academy "training" teaches adults only, with trainers,
 * trainees who are their own payers and no guardian anywhere, two
 * programmes booked (one paid), and its page for adults on, where a visitor
 * books for themself. Its accounts are …@demo.test with the demo password.
 * Seeded once.
 */

function inTraining(callable $read): mixed
{
    return app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('training'), $read);
}

it('seeds a training office for adults once, with no guardian and its page for adults on', function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll', 'groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site']]);
    $this->seed(DemoTrainingSeeder::class);
    $this->clearTenant();

    $academy = app(TenantDirectory::class)->bySlug('training');
    expect($academy)->not->toBeNull()->and($academy->audience)->toBe(Audience::Adults);
    $counts = inTraining(fn () => [
        'owner' => DB::table('users')->where('email', 'training.owner@demo.test')->exists(),
        'not_demo' => DB::table('users')->where('email', 'not like', '%@demo.test')->count(),
        'guardians' => DB::table('user_roles')->where('role', 'guardian')->count() + DB::table('guardian_student')->count(),
        'trainees' => DB::table('user_roles')->where('role', 'student')->count(),
        'trainers' => DB::table('user_roles')->where('role', 'teacher')->count(),
        'courses' => DB::table('enrollments')->count(),
        'paid' => DB::table('invoices')->where('status', 'paid')->count(),
        'sessions' => DB::table('sessions')->count(),
    ]);
    expect($counts)->toMatchArray(['owner' => true, 'not_demo' => 0, 'guardians' => 0, 'trainees' => 4, 'trainers' => 3, 'courses' => 2, 'paid' => 1])
        ->and($counts['sessions'])->toBeGreaterThan(0);

    $site = $this->getJson('http://training.lms.test/api/v1/site')->assertOk()->json('data');
    $form = collect($site['page']['blocks'])->firstWhere('type', 'form');
    expect($site['page']['settings']['audience'])->toBe('adults')->and($form['data']['who'])->toBe('adults');

    // Again, as on the next deploy: nothing new.
    $users = inTraining(fn () => DB::table('users')->count());
    $this->seed(DemoTrainingSeeder::class);
    $this->clearTenant();
    expect(inTraining(fn () => DB::table('users')->count()))->toBe($users);
});
