<?php

namespace Modules\Quran\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * What a Quran lesson recorded for one student (RecordQuranLesson): each
 * part's verses and pages, the mistakes counted by kind, the worked-out and
 * the given grade (with the teacher's note when they differ), and whether
 * the position moved. One per lesson and student; never changed.
 *
 * @property string $id
 * @property string $session_id
 * @property string $student_id
 * @property ?string $enrollment_id
 * @property string $plan_id
 * @property string $recorded_by
 * @property array<string, array{ranges: list<array{surah: int, from: int, to: int}>, pages: list<int>, mistakes: array<string, int>, computed: string, penalty: float, grade: string, note: ?string}> $parts
 * @property ?string $new_grade
 * @property bool $advanced
 * @property bool $frozen
 * @property ?array{surah: ?int, verse: int} $position_before
 * @property ?array{surah: ?int, verse: int} $position_after
 * @property CarbonImmutable $recorded_at
 */
final class QuranLessonResult extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'student_id', 'enrollment_id', 'plan_id', 'recorded_by', 'parts', 'new_grade', 'advanced', 'frozen',
        'position_before', 'position_after', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'parts' => 'array', 'position_before' => 'array', 'position_after' => 'array', 'advanced' => 'boolean', 'frozen' => 'boolean',
            'recorded_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('A lesson\'s Quran result is never changed.'));
    }
}
