<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One lesson between a teacher and a student.
 *
 * @property string $id
 * @property string $academy_id
 * @property SessionType $type
 * @property string $teacher_id
 * @property ?string $original_teacher_id
 * @property string $subject_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property SessionStatus $status
 * @property ?string $review_reason
 * @property FundingStatus $funding_status
 * @property ?string $makeup_for_session_id
 * @property string $anchor_tz
 * @property ?array<string, mixed> $decision
 * @property int $version
 */
final class Session extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'sessions';

    protected $fillable = [
        'type', 'teacher_id', 'original_teacher_id', 'subject_id', 'starts_at', 'ends_at',
        'status', 'review_reason', 'funding_status', 'makeup_for_session_id', 'anchor_tz', 'decision',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'funding_status' => 'funded',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => SessionType::class,
            'status' => SessionStatus::class,
            'funding_status' => FundingStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'decision' => 'array',
        ];
    }

    /** @return HasMany<SessionParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    /** @return HasMany<SessionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SessionEvent::class)->orderBy('occurred_at');
    }

    public function participant(ParticipantRole $role): ?SessionParticipant
    {
        return $this->participants->first(fn (SessionParticipant $p) => $p->role === $role && $p->is_active);
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /** Moves to $next or throws; callers save. */
    public function transitionTo(SessionStatus $next, ?string $reviewReason = null): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException("A {$this->status->value} session cannot become {$next->value}.");
        }

        $this->status = $next;
        $this->review_reason = $next === SessionStatus::NeedsReview ? $reviewReason : null;
        $this->version++;
    }
}
