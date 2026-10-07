<?php

namespace Modules\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Someone of the platform team signed in as a person of this academy.
 *
 * @property string $id
 * @property string $user_id
 * @property string $admin_id
 * @property string $admin_name
 * @property string $reason
 * @property int $minutes
 * @property ?string $token_hash
 * @property CarbonImmutable $redeem_by
 * @property ?CarbonImmutable $redeemed_at
 * @property ?CarbonImmutable $expires_at
 * @property ?CarbonImmutable $ended_at
 * @property ?string $ended_reason
 * @property CarbonImmutable $created_at
 */
final class Impersonation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** The browser session key that marks a signed-in-as session. */
    public const SESSION_KEY = 'impersonation_id';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'redeem_by' => 'immutable_datetime',
            'redeemed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->redeemed_at !== null && $this->ended_at === null && $this->expires_at !== null && $this->expires_at->greaterThan($now);
    }

    /** waiting (link not used yet) | live | ended | unused (the link ran out) */
    public function state(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            $this->ended_at !== null => 'ended',
            $this->redeemed_at === null => $this->redeem_by->greaterThan($now) ? 'waiting' : 'unused',
            $this->expires_at !== null && $this->expires_at->greaterThan($now) => 'live',
            default => 'ended',
        };
    }
}
