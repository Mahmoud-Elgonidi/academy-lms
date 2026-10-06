<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A weekly time whose hour moves for the student at a clock change (the DST
 * planner, docs/architecture/02, section 1). Until the academy decides, the
 * teacher's time is kept (the default) and the family is told 14 and 2 days
 * before; keeping the student's time moves the teacher's for the period.
 *
 * @property string $id
 * @property string $slot_id
 * @property string $enrollment_id
 * @property string $teacher_id
 * @property string $student_id
 * @property string $anchor_tz
 * @property string $other_tz
 * @property string $cause_tz
 * @property CarbonImmutable $changes_at
 * @property ?CarbonImmutable $reverts_at
 * @property int $before_weekday
 * @property string $before_time
 * @property int $after_weekday
 * @property string $after_time
 * @property int $delta_min
 * @property int $lessons
 * @property string $decision
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?CarbonImmutable $notified_early_at
 * @property ?CarbonImmutable $notified_late_at
 */
final class ShiftNotice extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const DECISIONS = ['pending', 'keep_teacher', 'keep_student'];

    protected $table = 'schedule_shift_notices';

    protected $fillable = [
        'slot_id', 'enrollment_id', 'teacher_id', 'student_id', 'anchor_tz', 'other_tz', 'cause_tz', 'changes_at', 'reverts_at',
        'before_weekday', 'before_time', 'after_weekday', 'after_time', 'delta_min', 'lessons', 'decision', 'decided_by', 'decided_at',
        'notified_early_at', 'notified_late_at',
    ];

    protected $attributes = ['decision' => 'pending', 'lessons' => 0];

    protected function casts(): array
    {
        return [
            'changes_at' => 'immutable_datetime',
            'reverts_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'notified_early_at' => 'immutable_datetime',
            'notified_late_at' => 'immutable_datetime',
            'before_weekday' => 'integer',
            'after_weekday' => 'integer',
            'delta_min' => 'integer',
            'lessons' => 'integer',
        ];
    }

    /** The family's hour moves (nobody chose to keep it). */
    public function movesForFamily(): bool
    {
        return $this->decision !== 'keep_student';
    }
}
