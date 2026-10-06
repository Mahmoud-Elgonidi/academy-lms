<?php

namespace Modules\Scheduling\Actions\Shifts;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\ShiftProposalChangesRequested;
use Modules\Scheduling\Contracts\Events\ShiftProposalRejected;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;

/** An admin asks the teacher for changes, or rejects the proposal; both need a reason the teacher reads. */
final class ReviewShiftProposal
{
    public function __construct(private readonly ShiftProposals $proposals) {}

    public function requestChanges(ShiftProposal $proposal, string $note, string $actorId): ShiftProposal
    {
        return $this->decide($proposal, ShiftProposalStatus::ChangesRequested, $note, $actorId);
    }

    public function reject(ShiftProposal $proposal, string $reason, string $actorId): ShiftProposal
    {
        return $this->decide($proposal, ShiftProposalStatus::Rejected, $reason, $actorId);
    }

    private function decide(ShiftProposal $proposal, ShiftProposalStatus $to, string $note, string $actorId): ShiftProposal
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('scheduling::shifts.errors.reason_required')]);
        }
        if (! $proposal->status->canTransitionTo($to)) {
            throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.not_now')]);
        }

        return DB::transaction(function () use ($proposal, $to, $note, $actorId) {
            $this->proposals->lock($proposal->teacher_id);

            $proposal->transitionTo($to);
            $proposal->fill(['decided_by' => $actorId, 'decided_at' => now(), 'decision_note' => trim($note)])->save();
            $this->proposals->comment($proposal, $actorId, 'admin', $note);

            $event = $to === ShiftProposalStatus::Rejected ? ShiftProposalRejected::class : ShiftProposalChangesRequested::class;
            DB::afterCommit(fn () => event(new $event($proposal->academy_id, $proposal->id, $proposal->teacher_id, $actorId)));

            return $proposal;
        });
    }
}
