<?php

use Modules\Identity\Enums\Role;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->user = User::factory()->withRole(Role::Guardian)->create([
        'name' => 'Huda',
        'email' => 'huda@example.test',
        'phone_e164' => '+966500000001',
        'username' => 'huda',
    ]);
    $this->clearTenant();
});

function attempt(string $login, string $password = 'password', array $headers = [])
{
    return test()->withHeaders(['X-Academy' => 'noor'] + $headers)->postJson('/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'device_name' => 'iPhone',
    ]);
}

it('signs in with email, phone or username', function (string $login) {
    attempt($login)
        ->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'roles', 'academy']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $this->user->id)
        ->assertJsonPath('user.roles', ['guardian'])
        ->assertJsonPath('user.academy.id', $this->academy->id);
})->with(['HUDA@example.test', '+966500000001', 'huda']);

it('stores the academy on the issued token', function () {
    attempt('huda@example.test')->assertOk();

    expect(PersonalAccessToken::query()->sole()->academy_id)->toBe($this->academy->id);
});

it('rejects a wrong password with a translated problem', function () {
    attempt('huda@example.test', 'wrong', ['Accept-Language' => 'ar'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('type', 'validation')
        ->assertJsonPath('title', 'البيانات المرسلة غير صحيحة.')
        ->assertJsonPath('errors.login.0', 'بيانات الدخول غير صحيحة.');
});

it('rejects a disabled user with the same message', function () {
    $this->actAsTenant($this->academy);
    $this->user->update(['status' => 'disabled']);
    $this->clearTenant();

    attempt('huda@example.test')
        ->assertUnprocessable()
        ->assertJsonPath('errors.login.0', 'These credentials do not match our records.');
});

it('rate limits repeated failures', function () {
    foreach (range(1, 5) as $_) {
        attempt('huda@example.test', 'wrong')->assertUnprocessable();
    }

    attempt('huda@example.test', 'password')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('type', 'too-many-requests');
});

it('returns the signed-in user from /me', function () {
    $token = attempt('huda')->json('token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Huda')
        ->assertJsonPath('data.academy.slug', 'noor');
});

it('revokes the token on logout', function () {
    $token = attempt('huda')->json('token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('refuses an expired token', function () {
    $token = attempt('huda')->json('token');
    PersonalAccessToken::query()->update(['expires_at' => now()->subMinute()]);

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('needs a token for /me', function () {
    $this->withHeader('X-Academy', 'noor')->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('type', 'unauthenticated');
});
