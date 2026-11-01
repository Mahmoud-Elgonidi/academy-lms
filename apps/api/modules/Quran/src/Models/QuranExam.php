<?php

namespace Modules\Quran\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A learner's exam at a stop of their plan's program (docs/architecture/02,
 * section 26): suggested, booked as an assessment lesson with an examiner,
 * passed or failed, or cancelled. Only through Actions\Exams\*.
 *
 * @property string $id
 * @property ?string $plan_id null once the learner's plan was erased behind a certificate
 * @property string $program_id
 * @property string $student_id
 * @property ?string $enrollment_id
 * @property string $stop_kind juz | surah | program | khatma
 * @property ?int $stop_number
 * @property string $status suggested | booked | passed | failed | cancelled
 * @property ?string $retake_of
 * @property ?CarbonImmutable $not_before
 * @property ?string $examiner_id
 * @property ?string $session_id
 * @property ?CarbonImmutable $booked_at
 * @property ?string $grade
 * @property ?string $computed
 * @property ?float $penalty
 * @property ?array<string, int> $mistakes
 * @property ?string $result_note
 * @property ?CarbonImmutable $retake_on
 * @property ?CarbonImmutable $recorded_at
 * @property ?CarbonImmutable $cancelled_at
 * @property ?string $cancel_reason
 * @property-read QuranProgram $program
 * @property-read QuranPlan $plan
 */
final class QuranExam extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const KINDS = ['juz', 'surah', 'program', 'khatma'];

    public const OPEN = ['suggested', 'booked'];

    protected $fillable = [
        'plan_id', 'program_id', 'student_id', 'enrollment_id', 'stop_kind', 'stop_number', 'status', 'retake_of', 'not_before',
        'examiner_id', 'session_id', 'booked_at', 'booked_by', 'grade', 'computed', 'penalty', 'mistakes', 'result_note', 'retake_on',
        'recorded_at', 'cancelled_at', 'cancel_reason', 'cancelled_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stop_number' => 'integer', 'not_before' => 'immutable_date', 'booked_at' => 'immutable_datetime', 'penalty' => 'float',
            'mistakes' => 'array', 'retake_on' => 'immutable_date', 'recorded_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<QuranProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(QuranProgram::class, 'program_id');
    }

    /** @return BelongsTo<QuranPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(QuranPlan::class, 'plan_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
