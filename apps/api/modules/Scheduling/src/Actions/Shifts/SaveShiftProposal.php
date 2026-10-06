<?php

namespace Modules\Scheduling\Actions\Shifts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/**
 * The teacher saves (and optionally submits) a full weekly schedule. While a
 * proposal is open the teacher keeps editing that one (a new revision each
 * time), so the conversation stays in one place. Editing after a decision
 * request or a counter-offer sends it back to the admins.
 */
final class SaveShiftProposal
{
    public function __construct(private readonly ShiftProposals $proposals) {}

    public function handle(
        string $teacherId,
        WeeklyShifts $shifts,
        CarbonImmutable $effectiveFrom,
        ?string $note = null,
        ?int $minWeekly = null,
        ?int $maxWeekly = null,
        bool $submit = true,
    ): ShiftProposal {
        if ($submit) {
            $this->proposals->assertValid($teacherId, $shifts, $effectiveFrom, $minWeekly, $maxWeekly);
        }

        return DB::transaction(function () use ($teacherId, $shifts, $effectiveFrom, $note, $minWeekly, $maxWeekly, $submit) {
            $this->proposals->lock($teacherId);

            $proposal = $this->proposals->openOrDraft($teacherId) ?? new ShiftProposal([
                'teacher_id' => $teacherId,
                'author_role' => 'teacher',
                'status' => ShiftProposalStatus::Draft,
            ]);

            if (! $submit && $proposal->exists && $proposal->status !== ShiftProposalStatus::Draft) {
                throw ValidationException::withMessages(['shifts' => __('scheduling::shifts.errors.already_sent')]);
            }

            $wasNew = ! $proposal->exists;
            // Blocks the admins have already seen become a new revision; a draft is overwritten.
            $newRevision = ! $wasNew && $proposal->status !== ShiftProposalStatus::Draft;
            $proposal->fill([
                'base_schedule_id' => ShiftSchedule::inForce($teacherId, $effectiveFrom)?->id,
                'author_role' => 'teacher',
                'timezone' => $shifts->timezone,
                'effective_from' => $effectiveFrom->toDateString(),
                'min_weekly_min' => $minWeekly,
                'max_weekly_min' => $maxWeekly,
                'resolutions' => [],
            ]);
            if ($note !== null && trim($note) !== '') {
                $proposal->note = trim($note);
            }

            if ($submit) {
                $proposal->transitionTo(ShiftProposalStatus::Submitted);
                $proposal->submitted_at = CarbonImmutable::now();
            }
            $proposal->save();

            $proposal->storeRevision($shifts, 'teacher', $newRevision);
            $proposal->save();

            if ($submit) {
                $this->proposals->comment($proposal, $teacherId, 'teacher', $wasNew ? $note : null);
                DB::afterCommit(fn () => event(new ShiftProposalSubmitted($proposal->academy_id, $proposal->id, $teacherId, $teacherId)));
            }

            return $proposal;
        });
    }
}
