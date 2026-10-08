<?php

namespace Modules\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Social\Provider;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * An account's link to a Google or Apple account: once linked, the
 * provider's subject (not the email) signs the person in.
 *
 * @property string $id
 * @property string $user_id
 * @property Provider $provider
 * @property string $subject
 * @property ?string $email
 * @property string $via
 * @property CarbonImmutable $linked_at
 * @property ?CarbonImmutable $last_used_at
 */
final class SocialIdentity extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $guarded = ['id', 'academy_id'];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'linked_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
