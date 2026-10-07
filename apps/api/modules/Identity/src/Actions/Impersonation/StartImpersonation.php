<?php

namespace Modules\Identity\Actions\Impersonation;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\Impersonation;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * The platform team asks to sign in as someone of an academy, with a reason
 * and a time limit. Gives back a one-time link on the academy's own host
 * that works for two minutes; only its hash is kept. The academy's audit log
 * says who, as whom, why and for how long.
 */
final class StartImpersonation
{
    public const MINUTES = [15, 30, 60];

    public const REASON_MIN = 10;

    /** How long the one-time link works before it is used. */
    public const LINK_SECONDS = 120;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TenantDirectory $directory,
    ) {}

    /** @return array{impersonation: Impersonation, url: string} */
    public function handle(TenantRef $academy, string $userId, PlatformAdminRef $admin, string $reason, int $minutes): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < self::REASON_MIN) {
            throw ValidationException::withMessages(['reason' => __('identity::impersonation.errors.reason', ['min' => self::REASON_MIN])]);
        }
        if (! in_array($minutes, self::MINUTES, true)) {
            throw ValidationException::withMessages(['minutes' => __('identity::impersonation.errors.minutes')]);
        }
        if (! $academy->active) {
            throw ValidationException::withMessages(['academy' => __('identity::impersonation.errors.suspended')]);
        }

        return $this->tenants->run($academy, function () use ($academy, $userId, $admin, $reason, $minutes) {
            $user = User::query()->find($userId);
            if ($user === null || ! $user->isActive()) {
                throw ValidationException::withMessages(['user' => __('identity::impersonation.errors.user')]);
            }

            $token = Str::random(48);
            $now = CarbonImmutable::now();
            $impersonation = Impersonation::query()->create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'reason' => $reason,
                'minutes' => $minutes,
                'token_hash' => hash('sha256', $token),
                'redeem_by' => $now->addSeconds(self::LINK_SECONDS),
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            app(AuditTrail::class)->record('impersonation.started', 'user', $user->id,
                ['user' => $user->name, 'minutes' => $minutes, 'reason' => $reason],
                new AuditActor('platform', $admin->id, $admin->name, $user->id, $impersonation->id));

            return [
                'impersonation' => $impersonation,
                'url' => $this->directory->baseUrl($academy).route('identity.impersonate', ['token' => $token], absolute: false),
            ];
        });
    }
}
