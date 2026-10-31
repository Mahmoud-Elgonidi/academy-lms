<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A student's seat in a group, through their own course (an enrollment on
 * a group plan), from the day they joined to the first day they are out.
 *
 * @property string $id
 * @property string $group_id
 * @property string $enrollment_id
 * @property string $student_id
 * @property CarbonImmutable $joined_on
 * @property ?CarbonImmutable $left_on
 */
final class GroupMember extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['group_id', 'enrollment_id', 'student_id', 'joined_on', 'left_on', 'created_by'];

    protected function casts(): array
    {
        return ['joined_on' => 'immutable_date', 'left_on' => 'immutable_date'];
    }

    /** @return BelongsTo<LessonGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(LessonGroup::class, 'group_id');
    }

    /** In the group on this day ("Y-m-d" on the academy's calendar). */
    public function liveOn(string $day): bool
    {
        return $this->joined_on->toDateString() <= $day && ($this->left_on === null || $this->left_on->toDateString() > $day);
    }
}
