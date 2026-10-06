<?php

namespace Modules\Scheduling\Actions\TimeOff;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\TimeOffCover;

/**
 * A lesson of a teacher on approved leave that nobody covered is cancelled
 * an hour before it starts, as the academy's doing (rule 21: makeup credit),
 * so the family is told in time and the teacher is not marked absent.
 * Runs every minute (scheduling:sweep).
 */
final class CancelUncoveredLessons
{
    public const BEFORE_MIN = 60;

    public function __construct(private readonly CancelSession $cancel) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $ids = TimeOffCover::uncoveredQuery($now->addMinutes(self::BEFORE_MIN), $now)->pluck('s.id');

        $done = 0;
        foreach (Session::query()->whereIn('id', $ids)->get() as $session) {
            try {
                $this->cancel->handle($session, 'system', 'teacher_leave', null, null, at: $now);
                $done++;
            } catch (ValidationException) {
                continue;
            }
        }

        return $done;
    }
}
