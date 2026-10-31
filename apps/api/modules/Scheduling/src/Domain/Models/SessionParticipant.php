<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $academy_id
 * @property string $session_id
 * @property string $user_id
 * @property ParticipantRole $role
 * @property bool $is_active
 * @property ?CarbonImmutable $first_join_at
 * @property ?CarbonImmutable $last_left_at
 * @property int $attended_min
 * @property ?string $enrollment_id a group lesson's student: their own course
 * @property ?string $funding_status a group lesson's student: funded | unfunded | makeup
 * @property ?string $outcome a group lesson's student: attended | absent | excused | makeup
 */
final class SessionParticipant extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'session_participants';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = ['session_id', 'user_id', 'role', 'is_active', 'first_join_at', 'last_left_at', 'attended_min', 'block_period', 'enrollment_id', 'funding_status', 'outcome'];

    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'is_active' => 'boolean',
            'first_join_at' => 'immutable_datetime',
            'last_left_at' => 'immutable_datetime',
        ];
    }

    /** The key is (academy_id, session_id, user_id); updates must match all of it. */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('session_id', $this->getAttribute('session_id'))
            ->where('user_id', $this->getAttribute('user_id'));
    }

    public static function blockPeriod(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return '['.$from->utc()->toIso8601String().','.$to->utc()->toIso8601String().')';
    }
}
