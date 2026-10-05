<?php

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Tenancy\TenantContext;

beforeEach(function () {
    $this->a = $this->createAcademy(['slug' => 'alpha']);
    $this->b = $this->createAcademy(['slug' => 'beta']);

    // The same email at two academies is two separate accounts.
    $this->actAsTenant($this->a);
    $this->userA = User::factory()->withRole(Role::Teacher)->create(['email' => 'same@example.test', 'name' => 'Teacher A']);
    $this->actAsTenant($this->b);
    $this->userB = User::factory()->withRole(Role::Student)->create(['email' => 'same@example.test', 'name' => 'Student B']);
    $this->clearTenant();
});

function login(string $slug, string $email = 'same@example.test', string $password = 'password')
{
    return test()->withHeader('X-Academy', $slug)->postJson('/api/v1/auth/login', [
        'login' => $email,
        'password' => $password,
        'device_name' => 'pest',
    ]);
}

it('answers 404 problem details when no academy can be resolved', function () {
    $this->postJson('/api/v1/auth/login', ['login' => 'x@example.test', 'password' => 'x', 'device_name' => 'pest'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'academy-not-found');
});

it('answers 404 for an unknown academy header', function () {
    login('nope')->assertNotFound()->assertJsonPath('type', 'academy-not-found');
});

it('resolves the academy from its subdomain', function () {
    $this->postJson('http://alpha.lms.test/api/v1/auth/login', [
        'login' => 'same@example.test', 'password' => 'password', 'device_name' => 'pest',
    ])->assertOk()->assertJsonPath('user.id', $this->userA->id);
});

it('logs the same email into a different account at each academy', function () {
    login('alpha')->assertOk()->assertJsonPath('user.id', $this->userA->id)->assertJsonPath('user.roles', ['teacher']);
    login('beta')->assertOk()->assertJsonPath('user.id', $this->userB->id)->assertJsonPath('user.roles', ['student']);
});

it('lets a token reach only its own academy', function () {
    $token = login('alpha')->json('token');

    // The token alone is enough: it decides the academy.
    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->userA->id)
        ->assertJsonPath('data.academy.slug', 'alpha');

    // Sent to the other academy's host or header, it is refused.
    $this->withToken($token)->getJson('http://beta.lms.test/api/v1/me')
        ->assertForbidden()->assertJsonPath('type', 'forbidden');
    $this->withToken($token)->withHeader('X-Academy', 'beta')->getJson('/api/v1/me')
        ->assertForbidden();
});

it('refuses requests for a suspended academy', function () {
    $token = login('alpha')->json('token');

    Academy::query()->whereKey($this->a->id)->update(['status' => AcademyStatus::Suspended]);

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('type', 'academy-suspended');
});

it('answers 401 for an unknown token with no other hint', function () {
    $this->withToken('1|not-a-real-token')->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('type', 'unauthenticated');
});

it('clears the tenant when the request ends', function () {
    login('alpha')->assertOk();

    expect(app(TenantContext::class)->check())->toBeFalse()
        ->and(DB::table('users')->count())->toBe(0);
});

it('uses the development academy on plain localhost, in local development only', function () {
    $this->createAcademy(['slug' => 'demo']);
    config(['platform.dev_academy' => 'demo']);

    $this->get('http://localhost/admin/login')->assertNotFound();

    app()->detectEnvironment(fn () => 'local');
    $this->get('http://localhost/admin/login')->assertOk();
});
