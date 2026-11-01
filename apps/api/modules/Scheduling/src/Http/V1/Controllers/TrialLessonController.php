<?php

namespace Modules\Scheduling\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Trials\CancelOwnTrial;
use Modules\Scheduling\Actions\Trials\RescheduleOwnTrial;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;
use Modules\Scheduling\Contracts\TrialBooking;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\TrialSlots;
use Modules\Scheduling\Support\TrialLinks;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A family's own link to its coming free trial (Support\TrialLinks), with no
 * sign-in: what it is and when, the other times its teacher is free, moving
 * it (until MOVE_UNTIL_HOURS before) and "we cannot come". The token is the
 * whole proof; a token that names no coming trial is not found.
 */
final class TrialLessonController
{
    public function __construct(
        private readonly LocalTimePresenter $time,
        private readonly ViewerTimezone $viewer,
    ) {}

    /**
     * A family's coming free trial lesson, from its own link.
     *
     * Times on the family's clock (X-Timezone, else the learner's).
     *
     * @throws NotFoundHttpException the link names no coming trial (over, cancelled, or another academy's)
     */
    public function show(Request $request, string $token): JsonResponse
    {
        return response()->json(['data' => $this->present($this->lesson($token), $this->zone($request, $token))])->header('Cache-Control', 'no-store');
    }

    /**
     * Other times the trial's teacher is free.
     *
     * Starts on a half-hour grid of the family's clock, from three hours ahead, over 14 days from `from` (a date; default today).
     *
     * @throws NotFoundHttpException the link names no coming trial
     */
    public function slots(Request $request, string $token, TrialSlots $slots, PeopleApi $people): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'timezone' => ['nullable', Rule::in(Timezones::all())]]);
        $session = $this->lesson($token);
        $zone = $this->zone($request, $token);
        $student = (string) self::studentOf($session);
        // Nothing to offer once it cannot be moved (cannot_move on the lesson says why).
        $found = ! RescheduleOwnTrial::movable($session) ? [] : $slots->slots(new TrialSlotSearch(
            subjectId: $session->subject_id,
            timezone: $zone,
            forMinor: $people->minors([$student])[$student] ?? false,
            from: $request->filled('from') ? CarbonImmutable::parse((string) $request->query('from'), $zone) : null,
            durationMin: $session->durationMinutes(),
            teacherId: $session->teacher_id,
        ));
        $out = [];
        foreach ($found as $slot) {
            $out[] = ['starts_at' => $this->time->present($slot->startsAt, $zone)];
        }

        return response()->json(['data' => $out])->header('Cache-Control', 'no-store');
    }

    /**
     * Move the free trial to another time.
     *
     * starts_at: one of the starts `slots` offered (UTC or with an offset). Until six hours before the lesson.
     *
     * @throws NotFoundHttpException the link names no coming trial
     */
    public function move(Request $request, string $token, RescheduleOwnTrial $move): JsonResponse
    {
        $request->validate(['starts_at' => ['required', 'date']]);
        $session = $move->handle($this->lesson($token), CarbonImmutable::parse((string) $request->input('starts_at'))->utc());

        return response()->json(['data' => $this->present($session, $this->zone($request, $token))]);
    }

    /**
     * We cannot come: cancel the free trial.
     *
     * note: why, in the family's words (optional, up to 500 characters), for whoever follows them up.
     *
     * @throws NotFoundHttpException the link names no coming trial
     */
    public function cancel(Request $request, string $token, CancelOwnTrial $cancel): JsonResponse
    {
        $valid = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $note = trim((string) ($valid['note'] ?? ''));
        $cancel->handle($this->lesson($token), $note === '' ? null : $note);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    private static function studentOf(Session $session): ?string
    {
        return $session->participant(ParticipantRole::Student)?->user_id;
    }

    private function lesson(string $token): Session
    {
        return TrialLinks::lesson($token) ?? throw new NotFoundHttpException;
    }

    private function zone(Request $request, string $token): string
    {
        $asked = $request->input('timezone', $request->query('timezone'));
        if (is_string($asked) && in_array($asked, Timezones::all(), true)) {
            return $asked;
        }
        $lesson = TrialLinks::lesson($token);
        $student = $lesson !== null ? self::studentOf($lesson) : null;

        return $this->viewer->device()
            ?? ($student !== null ? DB::table('users')->where('id', $student)->value('timezone') : null)
            ?? $this->viewer->effective();
    }

    /** @return array{subject: string, teacher: string, starts_at: array<string, string>|null, minutes: int, can_move: bool, cannot_move: ?string, moves: int, moves_left: int, move_until: array<string, string>|null} */
    private function present(Session $session, string $zone): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $subject = DB::table('subjects')->where('id', $session->subject_id)->first(['name_ar', 'name_en']);
        $teacher = (string) DB::table('users')->where('id', $session->teacher_id)->value('name');

        return [
            'subject' => (string) ($locale === 'en' ? ($subject->name_en ?? '') : ($subject->name_ar ?? '')),
            /** The teacher's first name only (with a title before it, as "أ. منى"). */
            'teacher' => preg_match('/^(?:\S+\.\s+)?\S+/u', trim($teacher), $first) === 1 ? $first[0] : '',
            'starts_at' => $this->time->present($session->starts_at, $zone),
            'minutes' => $session->durationMinutes(),
            /** Whether it may still be moved (until six hours before, twice at most, while its teacher takes trials). */
            'can_move' => RescheduleOwnTrial::movable($session),
            /** Why it cannot be moved, in the reader's words, with what to do instead; null when it can. */
            'cannot_move' => RescheduleOwnTrial::whyNotMovable($session),
            /** Times it has been moved. */
            'moves' => (int) $session->reschedule_count,
            /** Times it may still be moved (at most twice in all). */
            'moves_left' => max(0, TrialBooking::MAX_MOVES - (int) $session->reschedule_count),
            'move_until' => $this->time->present($session->starts_at->subHours(TrialBooking::MOVE_UNTIL_HOURS), $zone),
        ];
    }
}
