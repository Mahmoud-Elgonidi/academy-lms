<?php

namespace Modules\Scheduling\Actions\Shifts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftRulesChanged;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Queries\ShiftImpactQuery;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Turns a proposal into the teacher's schedule from its start date
 * (docs/architecture/02, section 2, "النسخ والتطبيق"), in one transaction:
 * the version in force is closed the day before, a new version starts, the
 * proposal is approved and older approved ones are superseded.
 *
 * Who may approve: an admin approves what the teacher sent; the teacher
 * accepts an admin's counter-offer. An admin approving their own
 * counter-offer needs a written override reason, unless the academy is
 * "admin only". Every booked session left outside the new shifts must be
 * resolved first, or overridden with a reason.
 */
final class ApproveShiftProposal
{
    public function __construct(
        private readonly ShiftProposals $proposals,
        private readonly ShiftImpactQuery $impact,
    ) {}

    public function handle(ShiftProposal $proposal, string $actorId, string $actorRole, ?string $overrideReason = null): ShiftProposal
    {
        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;
        $adminOnly = $this->proposals->settings()->shift_mode === 'admin_only';

        $allowed = match (true) {
            $proposal->status === ShiftProposalStatus::Submitted => $actorRole === 'admin',
            $proposal->status === ShiftProposalStatus::Countered && $actorRole === 'teacher' => $proposal->teacher_id === $actorId,
            $proposal->status === ShiftProposalStatus::Countered => $adminOnly || $overrideReason !== null,
            default => false,
        };
        if (! $allowed) {
            throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.cannot_approve')]);
        }

        $shifts = $proposal->shifts();
        $unresolved = array_filter(
            $this->impact->conflicts($proposal->teacher_id, $shifts, $proposal->effective_from),
            fn (array $conflict) => ! isset($proposal->resolutions[$conflict['key']]),
        );
        if ($unresolved !== [] && $overrideReason === null) {
            throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.unresolved', ['count' => count($unresolved)])]);
        }

        return DB::transaction(function () use ($proposal, $shifts, $actorId, $actorRole, $overrideReason) {
            $this->proposals->lock($proposal->teacher_id);
            $from = $proposal->effective_from->toDateString();

            // Versions that start on or after the new date never come into force; the one
            // in force is cut the day before.
            ShiftSchedule::query()->where('teacher_id', $proposal->teacher_id)->where('status', 'active')
                ->whereRaw('lower(effective) >= ?::date', [$from])
                ->update(['status' => 'superseded']);
            ShiftSchedule::query()->where('teacher_id', $proposal->teacher_id)->where('status', 'active')
                ->whereRaw('effective @> ?::date', [$from])
                ->update(['effective' => DB::raw('daterange(lower(effective), '.DB::getPdo()->quote($from).'::date)')]);

            $schedule = ShiftSchedule::query()->create([
                'teacher_id' => $proposal->teacher_id,
                'version' => (int) ShiftSchedule::query()->where('teacher_id', $proposal->teacher_id)->max('version') + 1,
                'timezone' => $shifts->timezone,
                'status' => 'active',
                'effective' => "[{$from},)",
                'approved_by' => $actorId,
                'source_proposal_id' => $proposal->id,
            ]);
            foreach ($shifts->blocks as $block) {
                $schedule->rules()->create([
                    'teacher_id' => $proposal->teacher_id,
                    'weekday' => $block->weekday,
                    'local_start' => $block->start(),
                    'duration_min' => $block->durationMin,
                ]);
            }

            ShiftProposal::query()->where('teacher_id', $proposal->teacher_id)
                ->where('status', ShiftProposalStatus::Approved->value)->whereKeyNot($proposal->id)
                ->update(['status' => ShiftProposalStatus::Superseded->value]);

            $proposal->transitionTo(ShiftProposalStatus::Approved);
            $proposal->fill(['decided_by' => $actorId, 'decided_at' => CarbonImmutable::now(), 'override_reason' => $overrideReason])->save();
            $this->proposals->comment($proposal, null, 'system', __('scheduling::shifts.comments.approved', [
                'date' => $from,
                'by' => __('scheduling::shifts.roles.'.$actorRole),
            ]).($overrideReason ? ' · '.__('scheduling::shifts.comments.override', ['reason' => $overrideReason]) : ''));

            if ($overrideReason !== null) {
                app(AuditTrail::class)->record('shift.approved_override', 'shift_proposal', $proposal->id, ['teacher_id' => $proposal->teacher_id, 'reason' => $overrideReason]);
            }
            DB::afterCommit(function () use ($proposal, $schedule, $from, $actorId) {
                event(new ShiftProposalApproved($proposal->academy_id, $proposal->id, $proposal->teacher_id, $actorId));
                event(new ShiftRulesChanged($proposal->academy_id, $proposal->teacher_id, $from, $schedule->version));
            });

            return $proposal;
        });
    }
}
