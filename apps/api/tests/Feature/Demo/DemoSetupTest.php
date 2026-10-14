<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Setup\SetupStep;

/*
 * The demo academy comes out of the seeders ready for its first families:
 * every step of "Start here" done (the essentials, and the optional ones the
 * demo shows too), so its owner signs in to the live board, not the checklist.
 */

it('comes out with every step of "Start here" done, so its owner opens on the live board', function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll']]);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();

    $this->actAsTenant(app(TenantDirectory::class)->bySlug('demo'));
    $progress = app(SetupChecklist::class)->progress();
    $left = array_values(array_map(fn (SetupStep $step) => $step->key(), array_filter($progress->steps(), fn (SetupStep $step) => ! $progress->isDone($step))));

    expect($left)->toBe([])
        ->and($progress->complete())->toBeTrue();

    // Signing in as the owner, past the authenticator app's set-up every owner goes through first.
    $owner = User::query()->where('email', 'owner@demo.test')->sole();
    $owner->forceFill(['app_authentication_secret' => UserFactory::TWO_FACTOR_SECRET])->saveQuietly();
    Auth::guard('web')->login($owner);
    $this->clearTenant();
    $this->get('http://demo.lms.test/admin')->assertOk();
    $this->get('http://demo.lms.test/admin/start-here')->assertOk()->assertSee('aria-valuenow="100"', false);
});
