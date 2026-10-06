<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A teacher's proposed weekly schedule (or an admin's counter-offer), and the
 * conversation around it until it is approved, rejected or withdrawn.
 *
 * @property string $id
 * @property string $teacher_id
 * @property ?string $base_schedule_id
 * @property string $author_role
 * @property ShiftProposalStatus $status
 * @property int $revision
 * @property string $timezone
 * @property CarbonImmutable $effective_from
 * @property ?string $note
 * @property ?int $min_weekly_min
 * @property ?int $max_weekly_min
 * @property ?CarbonImmutable $submitted_at
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?string $decision_note
 * @property ?string $override_reason
 * @property array<string, array{kind: string, by: ?string, at: string}> $resolutions
 * @property int $version
 */
final class ShiftProposal extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'teacher_id', 'base_schedule_id', 'author_role', 'status', 'revision', 'timezone', 'effective_from', 'note',
        'min_weekly_min', 'max_weekly_min', 'submitted_at', 'decided_by', 'decided_at', 'decision_note', 'override_reason', 'resolutions',
    ];

    protected $attributes = ['status' => 'draft', 'revision' => 1, 'resolutions' => '{}', 'version' => 1];

    protected function casts(): array
    {
        return [
            'status' => ShiftProposalStatus::class,
            'effective_from' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'resolutions' => 'array',
            'min_weekly_min' => 'integer',
            'max_weekly_min' => 'integer',
            'revision' => 'integer',
        ];
    }

    /** @return HasMany<ShiftProposalRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(ShiftProposalRule::class, 'proposal_id');
    }

    /** @return HasMany<ShiftProposalComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ShiftProposalComment::class, 'proposal_id')->orderBy('created_at');
    }

    /** @return BelongsTo<ShiftSchedule, $this> */
    public function baseSchedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class, 'base_schedule_id');
    }

    /** The blocks of the latest revision (or of an earlier one). */
    public function shifts(?int $revision = null): WeeklyShifts
    {
        $revision ??= $this->revision;

        return new WeeklyShifts(
            $this->rules->where('revision', $revision)
                ->map(fn (ShiftProposalRule $r) => new ShiftBlock($r->weekday, ShiftBlock::minutes($r->local_start), $r->duration_min))
                ->values()->all(),
            $this->timezone,
        );
    }

    public function transitionTo(ShiftProposalStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException("A {$this->status->value} proposal cannot become {$next->value}.");
        }

        $this->status = $next;
        $this->version++;
    }

    /** Stores the blocks as a new revision. */
    public function storeRevision(WeeklyShifts $shifts, string $authorRole, bool $newRevision = true): void
    {
        if ($newRevision && $this->rules()->exists()) {
            $this->revision++;
        }
        $this->rules()->where('revision', $this->revision)->delete();

        foreach ($shifts->blocks as $block) {
            $this->rules()->create([
                'revision' => $this->revision,
                'author_role' => $authorRole,
                'weekday' => $block->weekday,
                'local_start' => $block->start(),
                'duration_min' => $block->durationMin,
            ]);
        }

        $this->unsetRelation('rules');
    }
}
