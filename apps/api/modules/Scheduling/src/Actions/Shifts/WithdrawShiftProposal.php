<?php

namespace Modules\Scheduling\Actions\Shifts;

use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;

/** The teacher takes back a proposal (or a draft); the schedule in force does not change. */
final class WithdrawShiftProposal
{
    public function __construct(private readonly ShiftProposals $proposals) {}

    public function handle(ShiftProposal $proposal, string $teacherId): ShiftProposal
    {
        if ($proposal->teacher_id !== $teacherId || ! $proposal->status->canTransitionTo(ShiftProposalStatus::Withdrawn)) {
            throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.not_now')]);
        }

        $proposal->transitionTo(ShiftProposalStatus::Withdrawn);
        $proposal->save();
        $this->proposals->comment($proposal, null, 'system', __('scheduling::shifts.comments.withdrawn'));

        return $proposal;
    }
}
