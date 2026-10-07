<?php

namespace Modules\Identity\Actions\Impersonation;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Impersonation;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Ends a sign-in as someone: the platform admin left (exit), its time ran
 * out (expired), or the console ended it (ended). The browser that holds it
 * is signed out on its next request. Ending twice does nothing.
 */
final class EndImpersonation
{
    public const REASONS = ['exit', 'expired', 'ended'];

    public function handle(Impersonation $impersonation, string $reason): Impersonation
    {
        $reason = in_array($reason, self::REASONS, true) ? $reason : 'ended';

        return DB::transaction(function () use ($impersonation, $reason) {
            $fresh = Impersonation::query()->lockForUpdate()->findOrFail($impersonation->id);
            if ($fresh->ended_at !== null) {
                return $fresh;
            }
            $now = CarbonImmutable::now();
            // A link never used ends without having been a sign-in; one that ran out ends when it did.
            $endedAt = $reason === 'expired' && $fresh->expires_at !== null && $fresh->expires_at->lessThan($now) ? $fresh->expires_at : $now;
            $fresh->forceFill(['ended_at' => $endedAt, 'ended_reason' => $reason, 'token_hash' => null])->save();

            if ($fresh->redeemed_at !== null) {
                app(AuditTrail::class)->record('impersonation.ended', 'user', $fresh->user_id,
                    ['user' => $fresh->user?->name, 'how' => $reason],
                    new AuditActor('platform', $fresh->admin_id, $fresh->admin_name, $fresh->user_id, $fresh->id));
            }

            return $fresh;
        });
    }
}
