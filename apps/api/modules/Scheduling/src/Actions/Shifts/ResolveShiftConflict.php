<?php

namespace Modules\Scheduling\Actions\Shifts;

use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Queries\ShiftImpactQuery;

/**
 * Settles one booked weekly slot a new schedule would leave outside the
 * shifts (docs/architecture/02, section 2, "قواعد الاعتماد"):
 *  - exception: the slot stays with this teacher, agreed by both;
 *  - delay: the new schedule starts the day after the slot's last booked session;
 *  - moved: the student was moved to another teacher (Find a teacher);
 *  - null clears an earlier choice.
 */
final class ResolveShiftConflict
{
    public const KINDS = ['exception', 'delay', 'moved'];

    public function __construct(
        private readonly ShiftImpactQuery $impact,
        private readonly ShiftProposals $proposals,
    ) {}

    public function handle(ShiftProposal $proposal, string $key, ?string $kind, string $actorId): ShiftProposal
    {
        if (! $proposal->status->isOpen() || ($kind !== null && ! in_array($kind, self::KINDS, true))) {
            throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.not_now')]);
        }

        $conflict = collect($this->impact->conflicts($proposal->teacher_id, $proposal->shifts(), $proposal->effective_from))->firstWhere('key', $key);
        if ($conflict === null && $kind !== null) {
            throw ValidationException::withMessages(['proposal' => __('scheduling::shifts.errors.conflict_gone')]);
        }

        $resolutions = $proposal->resolutions;
        if ($kind === null) {
            unset($resolutions[$key]);
        } else {
            $resolutions[$key] = ['kind' => $kind, 'by' => $actorId, 'at' => now()->toIso8601String()];
        }

        if ($kind === 'delay') {
            $proposal->effective_from = $conflict['last']->addDay()->startOfDay();
            $this->proposals->comment($proposal, null, 'system', __('scheduling::shifts.comments.delayed', ['date' => $proposal->effective_from->toDateString()]));
        }

        $proposal->resolutions = $resolutions;
        $proposal->save();

        return $proposal;
    }
}
