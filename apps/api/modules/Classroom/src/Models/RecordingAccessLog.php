<?php

namespace Modules\Classroom\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Someone opened a recording: who, as what, from where, when. Kept as it
 * happened (the app may only add rows).
 *
 * @property string $id
 * @property string $recording_id
 * @property string $user_id
 * @property string $as staff | teacher | family
 * @property ?string $ip
 * @property ?string $user_agent
 * @property CarbonImmutable $opened_at
 */
final class RecordingAccessLog extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['recording_id', 'user_id', 'as', 'ip', 'user_agent', 'opened_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'immutable_datetime'];
    }
}
