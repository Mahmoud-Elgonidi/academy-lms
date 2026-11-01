<?php

namespace Modules\Scheduling\Actions\Trials;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Contracts\TrialBooking;
use Modules\Scheduling\Domain\Models\Session;

/**
 * The family moves its free trial from its own link (Support\TrialLinks):
 * until MOVE_UNTIL_HOURS before it starts and at most MAX_MOVES times,
 * while its teacher still takes trials, to a start the same teacher is
 * free at (the same slots, TrialSlots with the teacher), at least
 * MIN_NOTICE_HOURS ahead. RescheduleSession does the rest (the student's
 * reschedule under the policy; the family and the teacher hear of it, and
 * the CRM moves the lead's trial).
 */
final class RescheduleOwnTrial
{
    public function __construct(private readonly RescheduleSession $reschedule) {}

    public function handle(Session $session, CarbonImmutable $newStart, ?CarbonImmutable $now = null): Session
    {
        $now ??= CarbonImmutable::now();
        $why = self::whyNotMovable($session, $now);
        if ($why !== null) {
            throw ValidationException::withMessages(['starts_at' => $why]);
        }
        if ($newStart->lessThan($now->addHours(TrialBooking::MIN_NOTICE_HOURS))) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::trials.errors.too_soon', ['hours' => TrialBooking::MIN_NOTICE_HOURS])]);
        }

        return $this->reschedule->handle($session, $newStart, 'student', null, at: $now);
    }

    public static function movable(Session $session, ?CarbonImmutable $now = null): bool
    {
        return self::whyNotMovable($session, $now) === null;
    }

    /**
     * Why the family cannot move it, in their words (what to do instead), or
     * null: too close to the start, moved MAX_MOVES times already, or its
     * teacher no longer takes trials (the lesson stays; they may cancel and ask again).
     */
    public static function whyNotMovable(Session $session, ?CarbonImmutable $now = null): ?string
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            ! $session->starts_at->greaterThan($now->addHours(TrialBooking::MOVE_UNTIL_HOURS)) => __('scheduling::trials.errors.too_late_to_move', ['hours' => TrialBooking::MOVE_UNTIL_HOURS]),
            $session->reschedule_count >= TrialBooking::MAX_MOVES => __('scheduling::trials.errors.moved_enough', ['times' => TrialBooking::MAX_MOVES]),
            ! DB::table('teacher_profiles')->where('user_id', $session->teacher_id)->where('accepts_trials', true)->where('status', 'active')->exists() => __('scheduling::trials.errors.teacher_not_taking'),
            default => null,
        };
    }
}
