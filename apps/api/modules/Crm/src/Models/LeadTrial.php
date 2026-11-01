<?php

namespace Modules\Crm\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A lead's free trial lesson (a Scheduling session of type trial) and how
 * it went: booked, attended, missed or cancelled, and once attended the
 * teacher's assessment: what the family reads (the level, strengths, what
 * to work on, the teacher's words to them, the plan and lessons a week they
 * suggest) and notes for the academy alone. The report that went out with
 * it, the offer made then, and the trial it follows (booked again after a
 * missed one, or the second trial with another teacher).
 *
 * @property string $id
 * @property string $lead_id
 * @property string $session_id
 * @property string $teacher_id
 * @property CarbonImmutable $starts_at
 * @property string $status booked | attended | missed | cancelled
 * @property ?string $level
 * @property ?string $recommended_plan_id
 * @property ?int $recommended_per_week
 * @property ?string $assessment
 * @property ?string $assessed_by
 * @property ?CarbonImmutable $assessed_at
 * @property ?string $strengths
 * @property ?string $to_work_on
 * @property ?string $family_note the teacher's words to the family
 * @property ?CarbonImmutable $report_sent_at
 * @property ?string $offer_coupon_id
 * @property ?int $offer_percent_bp
 * @property ?CarbonImmutable $offer_ends_at
 * @property ?CarbonImmutable $offer_reminded_at
 * @property ?string $outcome why it did not take place: student_absent | teacher_absent (missed), family | academy (cancelled)
 * @property string $kind first | rebook | second
 * @property ?string $follows_trial_id
 */
final class LeadTrial extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** What the family reads of an assessment (the assessment form marks these). */
    public const FAMILY_READS = ['level', 'strengths', 'to_work_on', 'family_note', 'plan_id', 'per_week'];

    protected $fillable = ['lead_id', 'session_id', 'teacher_id', 'starts_at', 'status', 'level', 'recommended_plan_id', 'recommended_per_week', 'assessment', 'assessed_by', 'assessed_at',
        'strengths', 'to_work_on', 'family_note', 'outcome', 'kind', 'follows_trial_id'];

    protected $attributes = ['status' => 'booked', 'kind' => 'first'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'assessed_at' => 'immutable_datetime',
            'recommended_per_week' => 'integer',
            'report_sent_at' => 'immutable_datetime',
            'offer_percent_bp' => 'integer',
            'offer_ends_at' => 'immutable_datetime',
            'offer_reminded_at' => 'immutable_datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function isAssessed(): bool
    {
        return $this->assessed_at !== null;
    }
}
