<?php

use Database\Seeders\LoadAcademySeeder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Tenancy\TenantContext;

// The academy the heavy screens are measured on (docs/architecture/03, section 13), here small:
// its weeks still go through the real actions, so it keeps working as they change.

function loadSize(?array $sizes): void
{
    foreach (['LOAD_TEACHERS', 'LOAD_STUDENTS', 'LOAD_WEEKS'] as $key) {
        $value = $sizes[$key] ?? null;
        putenv($value === null ? $key : "{$key}={$value}");
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            $_ENV[$key] = $_SERVER[$key] = (string) $value;
        }
    }
}

afterEach(fn () => loadSize(null));

it('never runs in production', function () {
    app()->instance('env', 'production');

    expect(fn () => app(LoadAcademySeeder::class)->setContainer(app())->__invoke())->toThrow(RuntimeException::class);
    expect(app(TenantDirectory::class)->bySlug('load'))->toBeNull();
});

it('lives an academy\'s week through the real actions: courses, lessons, reports, money and pay', function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll']]);
    loadSize(['LOAD_TEACHERS' => 3, 'LOAD_STUDENTS' => 6, 'LOAD_WEEKS' => 1]);

    $this->seed(LoadAcademySeeder::class);

    app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('load'), function () {
        $s001 = DB::table('users')->where('email', 's001@load.test')->value('id');
        $count = fn (string $table, array $where = []) => DB::table($table)->where($where)->count();

        expect($count('user_roles', ['role' => 'teacher']))->toBe(3)
            ->and($count('user_roles', ['role' => 'student']))->toBe(6)
            ->and($count('enrollments', ['student_id' => $s001]))->toBe(3)
            ->and($count('sessions', ['status' => 'completed']))->toBeGreaterThan(0)
            ->and($count('session_reports', ['status' => 'submitted']))->toBeGreaterThan(0)
            ->and($count('invoices', ['status' => 'paid']))->toBeGreaterThan(0)
            ->and($count('teacher_earnings'))->toBeGreaterThan(0)
            ->and(DB::table('sessions')->where('starts_at', '>', now()->addWeeks(2))->exists())->toBeTrue();
    });
});
