<?php

namespace Modules\Scheduling\Actions\Shifts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\ShiftProposalCountered;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/**
 * An admin edits the blocks and sends them back (a counter-offer), or starts
 * a schedule for a teacher with no open proposal. The teacher accepts with
 * one tap or edits again. In an "admin only" academy it applies at once.
 * Same validation as the teacher's own proposals: one code path.
 */
final class CounterShiftProposal
{
    public function __construct(
        private readonly ShiftProposals $proposals,
        private readonly ApproveShiftProposal $approve,
    ) {}

    public function handle(string $teacherId, WeeklyShifts $shifts, CarbonImmutable $effectiveFrom, ?string $note, string $actorId): ShiftProposal
    {
        $this->proposals->assertValid($teacherId, $shifts, $effectiveFrom, null, null);
        $adminOnly = $this->proposals->settings()->shift_mode === 'admin_only';

        $proposal = DB::transaction(function () use ($teacherId, $shifts, $effectiveFrom, $note, $actorId) {
            $this->proposals->lock($teacherId);

            $proposal = $this->proposals->openOrDraft($teacherId);
            if ($proposal !== null && $proposal->status === ShiftProposalStatus::Draft) {
                throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.teacher_drafting')]);
            }

            $proposal ??= ShiftProposal::query()->create([
                'teacher_id' => $teacherId,
                'author_role' => 'admin',
                'status' => ShiftProposalStatus::Submitted,
                'timezone' => $shifts->timezone,
                'effective_from' => $effectiveFrom->toDateString(),
                'submitted_at' => now(),
            ]);

            $proposal->fill([
                'base_schedule_id' => ShiftSchedule::inForce($teacherId, $effectiveFrom)?->id,
                'timezone' => $shifts->timezone,
                'effective_from' => $effectiveFrom->toDateString(),
            ]);
            if ($proposal->status !== ShiftProposalStatus::Countered) {
                $proposal->transitionTo(ShiftProposalStatus::Countered);
            }
            $proposal->save();
            $proposal->storeRevision($shifts, 'admin', newRevision: $proposal->rules()->exists());
            $proposal->save();

            $this->proposals->comment($proposal, $actorId, 'admin', $note);
            DB::afterCommit(fn () => event(new ShiftProposalCountered($proposal->academy_id, $proposal->id, $teacherId, $actorId)));

            return $proposal;
        });

        // An "admin only" academy does not wait for the teacher.
        return $adminOnly ? $this->approve->handle($proposal->refresh(), $actorId, 'admin') : $proposal;
    }
}
