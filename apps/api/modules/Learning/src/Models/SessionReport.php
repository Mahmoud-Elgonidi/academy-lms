<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The teacher's report on one lesson, or a short note when the student did
 * not come (docs/architecture/02, section 5, "حالة التقرير").
 *
 * @property string $id
 * @property string $academy_id
 * @property string $session_id
 * @property ?string $enrollment_id
 * @property string $teacher_id
 * @property string $student_id
 * @property string $kind lesson | absence
 * @property string $status due | overdue | submitted | reviewed
 * @property CarbonImmutable $session_starts_at
 * @property CarbonImmutable $due_at
 * @property ?CarbonImmutable $submitted_at
 * @property bool $late
 * @property ?int $engagement
 * @property ?int $overall
 * @property ?string $notes_for_student
 * @property ?string $notes_internal
 * @property ?string $absence_note
 * @property ?string $reviewed_by
 * @property ?CarbonImmutable $reviewed_at
 * @property ?string $review_note
 * @property ?string $template_id the report template version its answers follow
 * @property array<string, mixed> $answers that template's answers, by field key
 */
final class SessionReport extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const OPEN = ['due', 'overdue'];

    protected $fillable = [
        'session_id', 'enrollment_id', 'teacher_id', 'student_id', 'kind', 'status', 'session_starts_at', 'due_at', 'submitted_at', 'late',
        'engagement', 'overall', 'notes_for_student', 'notes_internal', 'absence_note', 'reviewed_by', 'reviewed_at', 'review_note', 'template_id', 'answers',
    ];

    protected $attributes = ['kind' => 'lesson', 'status' => 'due', 'late' => false, 'answers' => '{}'];

    protected function casts(): array
    {
        return [
            'session_starts_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'late' => 'boolean',
            'engagement' => 'integer',
            'overall' => 'integer',
            'answers' => 'array',
        ];
    }

    /** @return HasMany<ReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReportItem::class, 'report_id');
    }

    /** @return HasOne<Homework, $this> */
    public function homework(): HasOne
    {
        return $this->hasOne(Homework::class, 'report_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
