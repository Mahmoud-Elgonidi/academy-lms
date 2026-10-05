<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Tenancy\Exceptions\TenantNotResolved;
use Modules\SharedKernel\Tenancy\TenantContext;
use Tests\Support\RecordTenantJob;

beforeEach(function () {
    $this->a = $this->createAcademy();
    $this->b = $this->createAcademy();

    $this->actAsTenant($this->a);
    User::factory()->count(2)->create();
    $this->actAsTenant($this->b);
    User::factory()->count(3)->create();
    $this->clearTenant();
});

it('shows no tenant rows on the app connection when no academy is set', function () {
    expect(DB::table('users')->count())->toBe(0)
        ->and(DB::table('academies')->count())->toBe(0)
        ->and(DB::connection('pgsql_platform')->table('users')->count())->toBe(5);
});

it('shows only the current academy rows, even to raw queries', function () {
    $this->actAsTenant($this->a);

    expect(DB::table('users')->count())->toBe(2)
        ->and(DB::table('users')->distinct()->pluck('academy_id')->all())->toBe([$this->a->id])
        ->and(DB::table('academies')->pluck('id')->all())->toBe([$this->a->id]);
});

it('refuses to write a row for another academy', function () {
    $this->actAsTenant($this->a);

    DB::table('users')->insert([
        'id' => (string) Str::uuid7(),
        'academy_id' => $this->b->id,
        'name' => 'Intruder',
        'email' => 'intruder@example.test',
    ]);
})->throws(QueryException::class, 'row-level security');

it('cannot move a row to another academy', function () {
    $this->actAsTenant($this->a);
    $user = User::query()->firstOrFail();

    DB::table('users')->where('id', $user->id)->update(['academy_id' => $this->b->id]);
})->throws(QueryException::class, 'row-level security');

it('fills academy_id from the session setting when a raw insert leaves it out', function () {
    $this->actAsTenant($this->a);

    $id = (string) Str::uuid7();
    DB::table('users')->insert(['id' => $id, 'name' => 'Raw', 'email' => 'raw@example.test']);

    expect(DB::connection('pgsql_platform')->table('users')->where('id', $id)->value('academy_id'))->toBe($this->a->id);
});

it('blocks cross-academy references with composite foreign keys, even for BYPASSRLS', function () {
    $userOfB = DB::connection('pgsql_platform')->table('users')->where('academy_id', $this->b->id)->value('id');

    DB::connection('pgsql_platform')->table('user_roles')->insert([
        'academy_id' => $this->a->id,
        'user_id' => $userOfB,
        'role' => 'teacher',
    ]);
})->throws(QueryException::class, 'foreign key');

it('returns nothing from tenant models when no academy is set', function () {
    expect(User::query()->count())->toBe(0);
});

it('refuses to create a tenant model when no academy is set', function () {
    User::factory()->create();
})->throws(TenantNotResolved::class);

it('restores the tenant setting after a reconnect', function () {
    $this->actAsTenant($this->a);

    DB::reconnect();

    expect(DB::selectOne("select current_setting('app.tenant_id', true) as v")->v)->toBe($this->a->id)
        ->and(DB::table('users')->count())->toBe(2);
});

it('runs a queued job as the academy that dispatched it', function () {
    config(['queue.default' => 'database']);
    RecordTenantJob::$seen = null;

    $this->actAsTenant($this->b);
    RecordTenantJob::dispatch();

    // What a separate worker process would start with: no tenant at all.
    $this->clearTenant();
    Context::flush();

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect(RecordTenantJob::$seen)->toBe([
        'tenant' => $this->b->id,
        'pg_setting' => $this->b->id,
        'visible_users' => 3,
        'raw_visible_users' => 3,
    ])->and(app(TenantContext::class)->check())->toBeFalse()
        ->and(DB::table('users')->count())->toBe(0);
});
