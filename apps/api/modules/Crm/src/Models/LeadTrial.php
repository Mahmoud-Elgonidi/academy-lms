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
 * teacher's assessment (level, the plan and lessons a week they suggest,
 * notes for the academy).
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
 */
final class LeadTrial extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['lead_id', 'session_id', 'teacher_id', 'starts_at', 'status', 'level', 'recommended_plan_id', 'recommended_per_week', 'assessment', 'assessed_by', 'assessed_at'];

    protected $attributes = ['status' => 'booked'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'assessed_at' => 'immutable_datetime',
            'recommended_per_week' => 'integer',
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
