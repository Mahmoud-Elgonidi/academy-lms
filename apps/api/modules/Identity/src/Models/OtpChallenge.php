<?php

namespace Modules\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Enums\OtpPurpose;

/**
 * A one-time code sent to an email or a phone. The code and the discovery
 * token are kept only as hashes, the contact encrypted. Not a tenant model:
 * see the migration. Pruned a day after it was made.
 *
 * @property string $id
 * @property OtpPurpose $purpose
 * @property ?string $academy_id
 * @property ?string $user_id
 * @property string $channel
 * @property string $destination_hash
 * @property string $destination
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $verified_at
 * @property ?CarbonImmutable $consumed_at
 * @property ?string $token_hash
 * @property ?string $ip
 * @property CarbonImmutable $created_at
 */
final class OtpChallenge extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'token_hash', 'destination_hash', 'destination'];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'destination' => 'encrypted',
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDay());
    }
}
