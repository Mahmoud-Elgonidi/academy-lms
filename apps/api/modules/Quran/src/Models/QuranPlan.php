<?php

namespace Modules\Quran\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Quran\Domain\Programs\Position;
use Modules\Quran\Domain\Programs\ProgramShape;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A course's plan on a Quran program (or a group's, its members sharing one
 * position): where the learner is, their own pace and where the far review
 * reached. One active a course (or group); a new one closes the last.
 * Made only through Actions\Plans\StartQuranPlan, changed through
 * AdjustQuranPlan and RecordQuranLesson.
 *
 * @property string $id
 * @property string $program_id
 * @property ?string $enrollment_id
 * @property ?string $student_id
 * @property ?string $group_id
 * @property ?int $position_surah
 * @property ?int $position_verse
 * @property ?float $new_amount
 * @property ?int $near_portions
 * @property ?int $far_pages
 * @property ?int $far_cursor
 * @property string $status active | closed
 * @property ?CarbonImmutable $finished_at
 * @property ?CarbonImmutable $closed_at
 * @property-read QuranProgram $program
 */
final class QuranPlan extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'program_id', 'enrollment_id', 'student_id', 'group_id', 'position_surah', 'position_verse', 'new_amount', 'near_portions', 'far_pages',
        'far_cursor', 'status', 'finished_at', 'closed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'position_surah' => 'integer', 'position_verse' => 'integer', 'new_amount' => 'float', 'near_portions' => 'integer',
            'far_pages' => 'integer', 'far_cursor' => 'integer', 'finished_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<QuranProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(QuranProgram::class, 'program_id');
    }

    /** Where the learner is; null once the program's scope is done. */
    public function position(): ?Position
    {
        return $this->position_verse === null ? null : new Position($this->position_surah, $this->position_verse);
    }

    /** @return array{position_surah: ?int, position_verse: ?int} */
    public static function positionColumns(?Position $at): array
    {
        return ['position_surah' => $at?->surah, 'position_verse' => $at?->verse];
    }

    /** The program at this learner's own pace. */
    public function shape(): ProgramShape
    {
        return $this->program->shape()->withPace($this->new_amount, $this->near_portions, $this->far_pages);
    }

    public function isGroup(): bool
    {
        return $this->group_id !== null;
    }
}
