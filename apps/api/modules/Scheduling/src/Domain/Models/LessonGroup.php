<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A group of students learning together with one teacher, at weekly times
 * of its own (docs/architecture/02, "الحصص الجماعية"). It waits as forming
 * until it has the students it starts with, runs from its first day, and
 * once ended stays ended.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $teacher_id
 * @property string $subject_id
 * @property ?string $level_id
 * @property ?string $age_group People's AgeGroup value
 * @property int $capacity
 * @property ?int $min_to_start
 * @property int $session_minutes
 * @property int $sessions_per_week
 * @property string $anchor_tz the teacher's clock its weekly times are on
 * @property string $status forming | running | ended
 * @property ?CarbonImmutable $started_on
 * @property ?CarbonImmutable $ended_on
 * @property ?string $name
 * @property ?string $created_by
 */
final class LessonGroup extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const FORMING = 'forming';

    public const RUNNING = 'running';

    public const ENDED = 'ended';

    protected $table = 'lesson_groups';

    protected $fillable = [
        'teacher_id', 'subject_id', 'level_id', 'age_group', 'capacity', 'min_to_start', 'session_minutes', 'sessions_per_week',
        'anchor_tz', 'status', 'started_on', 'ended_on', 'name', 'created_by',
    ];

    protected $attributes = ['status' => self::FORMING];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'min_to_start' => 'integer',
            'session_minutes' => 'integer',
            'sessions_per_week' => 'integer',
            'started_on' => 'immutable_date',
            'ended_on' => 'immutable_date',
        ];
    }

    /** @return HasMany<GroupMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    /** @return HasMany<RecurringSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(RecurringSlot::class, 'group_id');
    }

    /** Members holding a seat on this day ("Y-m-d" on the academy's calendar): one leaving on a day ahead holds it until then. */
    public function membersOn(string $day): HasMany
    {
        return $this->members()->where('joined_on', '<=', $day)->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $day));
    }

    /** Seats taken from this day on: everyone in the group then or joining later, until they leave. */
    public function seatsTaken(string $day): int
    {
        return $this->members()->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $day))->count();
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }
}
