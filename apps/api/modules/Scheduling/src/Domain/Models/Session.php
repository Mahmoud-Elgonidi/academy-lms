<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\LessonChanged;
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
 * @property ?string $enrollment_id
 * @property ?string $group_id a group's lesson (enrollment_id null; each student's course is on their participant row)
 * @property ?string $slot_id
 * @property ?string $occurrence_key
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
        'type', 'teacher_id', 'original_teacher_id', 'subject_id', 'enrollment_id', 'group_id', 'slot_id', 'occurrence_key', 'starts_at', 'ends_at',
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

    public function isGroup(): bool
    {
        return $this->group_id !== null;
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

    /**
     * Every change of a lesson (its state, time or teacher) bumps its version.
     * Saving one locks the row and checks the version is still the one read:
     * two people, or a person and the sweeper, deciding the same lesson at once
     * cannot both go through; the second waits for the first, then is refused
     * (LessonChanged) and its transaction rolls back. No key update, so the
     * lesson's own log rows (which hold a key share on it) never deadlock with it.
     */
    protected function performUpdate(Builder $query)
    {
        if ($this->exists && $this->isDirty('version')) {
            $current = self::query()->whereKey($this->getKey())->lock('for no key update')->value('version');
            if ((int) $current !== (int) $this->getOriginal('version')) {
                throw new LessonChanged($this->getKey());
            }
        }

        return parent::performUpdate($query);
    }
}
