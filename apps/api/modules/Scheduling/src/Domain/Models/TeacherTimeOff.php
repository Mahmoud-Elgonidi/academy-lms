<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher's leave: whole days on their clock (midnight to midnight there)
 * or some hours. Approved, it takes the hours out of their availability, and
 * its lessons wait for a substitute or a makeup (TimeOffCover).
 *
 * @property string $id
 * @property string $teacher_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property bool $all_day
 * @property string $reason
 * @property ?string $note
 * @property TimeOffStatus $status
 * @property string $requested_by
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?string $decision_note
 */
final class TeacherTimeOff extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const REASONS = ['sick', 'vacation', 'personal', 'family', 'emergency', 'other'];

    protected $table = 'teacher_time_off';

    protected $fillable = ['teacher_id', 'starts_at', 'ends_at', 'timezone', 'all_day', 'reason', 'note', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note'];

    protected $attributes = ['status' => 'pending', 'all_day' => true];

    protected function casts(): array
    {
        return [
            'status' => TimeOffStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'all_day' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    /** The last day off on the teacher's clock (the end is the next midnight for whole days). */
    public function lastDay(): CarbonImmutable
    {
        $end = $this->ends_at->setTimezone($this->timezone);

        return $this->all_day ? $end->subDay()->startOfDay() : $end;
    }

    public function days(): int
    {
        return max(1, (int) $this->starts_at->setTimezone($this->timezone)->startOfDay()->diffInDays($this->lastDay()->startOfDay()) + 1);
    }

    public function covers(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->starts_at->lessThan($to) && $this->ends_at->greaterThan($from);
    }
}
