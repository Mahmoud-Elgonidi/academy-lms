<?php

namespace Modules\Identity\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Auth\DummyPassword;

/**
 * Checks credentials in the current academy and issues a Sanctum token.
 * The login field may be an email, an E.164 phone number, or a username.
 */
final class Login
{
    public function __construct(private readonly IssueAccessToken $tokens) {}

    /** @return array{user: User, token: string, expires_at: ?CarbonImmutable} */
    public function handle(string $login, string $password, string $deviceName): array
    {
        $user = User::query()->where($this->field($login), $login)->first();
        // As long whether or not the account exists, so the time says nothing about who has one.
        $valid = $user?->password !== null ? Hash::check($password, $user->password) : DummyPassword::check($password);

        // Same message whether the user is missing, disabled or the password is wrong.
        if ($user === null || ! $user->isActive() || $user->password === null || ! $valid) {
            throw ValidationException::withMessages(['login' => __('identity::auth.failed')]);
        }

        return $this->tokens->handle($user, $deviceName);
    }

    private function field(string $login): string
    {
        return match (true) {
            str_contains($login, '@') => 'email',
            (bool) preg_match('/^\+\d+$/', $login) => 'phone_e164',
            default => 'username',
        };
    }
}
