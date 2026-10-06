<?php

namespace Modules\Communication\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A phone or browser that receives push for a signed-in person.
 *
 * @property string $id
 * @property string $user_id
 * @property string $platform ios | android | web
 * @property string $token
 * @property ?string $app_version
 * @property CarbonImmutable $last_seen_at
 */
final class PushDevice extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const PLATFORMS = ['ios', 'android', 'web'];

    protected $fillable = ['user_id', 'platform', 'token', 'app_version', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime'];
    }
}
