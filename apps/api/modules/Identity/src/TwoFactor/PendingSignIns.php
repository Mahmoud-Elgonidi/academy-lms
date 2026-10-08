<?php

namespace Modules\Identity\TwoFactor;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * An app's sign-in waiting for its second step: the password or the code was
 * right, and the token for the authenticator's code is good for five
 * minutes, five tries, once, in the same academy. Only its hash is kept.
 */
final class PendingSignIns
{
    public const MINUTES = 5;

    public const TRIES = 5;

    /** @return array{token: string, expires_at: CarbonImmutable} */
    public function begin(User $user, string $deviceName): array
    {
        $token = Str::random(48);
        $expiresAt = CarbonImmutable::now()->addMinutes(self::MINUTES);
        Cache::put(self::key($token), [
            'academy' => app(TenantContext::class)->id(),
            'user' => $user->id,
            'device' => $deviceName,
            'tries' => 0,
            'until' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /** @return ?array{academy: ?string, user: string, device: string, tries: int, until: int} still good here, else null */
    public function find(string $token): ?array
    {
        $pending = $token === '' ? null : Cache::get(self::key($token));
        if (! is_array($pending) || $pending['until'] < CarbonImmutable::now()->getTimestamp() || $pending['academy'] !== app(TenantContext::class)->id()) {
            return null;
        }

        return $pending;
    }

    /** A wrong code: one try fewer; none left, the sign-in is over. */
    public function missed(string $token): void
    {
        $pending = Cache::get(self::key($token));
        if (! is_array($pending)) {
            return;
        }
        if (++$pending['tries'] >= self::TRIES) {
            $this->forget($token);

            return;
        }
        Cache::put(self::key($token), $pending, CarbonImmutable::createFromTimestamp($pending['until']));
    }

    public function forget(string $token): void
    {
        Cache::forget(self::key($token));
    }

    private static function key(string $token): string
    {
        return 'identity:two-factor:'.hash('sha256', $token);
    }
}
