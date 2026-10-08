<?php

namespace Modules\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Social\Provider;
use Modules\Identity\Social\SocialProfile;

/**
 * What a provider confirmed, for a few minutes: a hand-off ticket from the
 * platform's callback to the academy's host, or a discovery of the person's
 * accounts for the shared app. Found by its token's hash only; pruned after
 * a day.
 *
 * @property string $id
 * @property string $purpose
 * @property ?string $academy_id
 * @property Provider $provider
 * @property string $subject
 * @property ?string $email
 * @property bool $email_verified
 * @property ?string $intent
 * @property ?string $user_id
 * @property ?string $client
 * @property ?string $bind_hash
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $consumed_at
 */
final class SocialSignIn extends Model
{
    use HasUuids;
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'email' => 'encrypted',
            'email_verified' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function profile(): SocialProfile
    {
        return new SocialProfile($this->provider, $this->subject, $this->email, $this->email_verified);
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDay());
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
