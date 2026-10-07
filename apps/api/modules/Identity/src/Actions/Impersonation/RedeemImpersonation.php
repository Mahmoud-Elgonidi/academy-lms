<?php

namespace Modules\Identity\Actions\Impersonation;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Exceptions\ImpersonationUnavailable;
use Modules\Identity\Models\Impersonation;

/**
 * Uses a one-time sign-in link, on the academy's host: once, within its two
 * minutes, for a person who is still active. From then on the sign-in lasts
 * the minutes chosen; the link itself is forgotten.
 */
final class RedeemImpersonation
{
    public function handle(string $token): Impersonation
    {
        return DB::transaction(function () use ($token) {
            $impersonation = Impersonation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            $now = CarbonImmutable::now();
            if ($impersonation === null || $impersonation->redeemed_at !== null || $impersonation->ended_at !== null || $impersonation->redeem_by->lessThanOrEqualTo($now)) {
                throw new ImpersonationUnavailable;
            }
            if (! $impersonation->user?->isActive()) {
                throw new ImpersonationUnavailable;
            }

            $impersonation->forceFill([
                'token_hash' => null,
                'redeemed_at' => $now,
                'expires_at' => $now->addMinutes($impersonation->minutes),
            ])->save();

            return $impersonation;
        });
    }
}
