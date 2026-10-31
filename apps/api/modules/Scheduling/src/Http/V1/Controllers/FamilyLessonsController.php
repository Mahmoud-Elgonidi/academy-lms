<?php

namespace Modules\Scheduling\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Actions\RefuseSubstitute;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Http\V1\Requests\CancelLessonRequest;
use Modules\Scheduling\Http\V1\Requests\DisputeLessonRequest;
use Modules\Scheduling\Http\V1\Resources\LessonPresenter;
use Modules\Scheduling\Queries\FamilyCancellation;
use Modules\Scheduling\Queries\FamilyNotices;
use Modules\Scheduling\Queries\LessonList;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A learner's lessons for the family app, and what the family may do with
 * one: cancel it under the academy's policy (told the outcome first), turn
 * down a substitute, or dispute what a lesson was recorded as. A child in
 * the kids' look does none of these.
 */
final class FamilyLessonsController
{
    public function __construct(
        private readonly FamilyView $family,
        private readonly LessonList $lessons,
        private readonly LessonPresenter $present,
    ) {}

    /**
     * A learner's lessons.
     *
     * upcoming (default): from the start of today, four weeks ahead; past: the last four weeks, latest first.
     * notices: coming holidays, the learner's courses paused, ending or just ended, and clock changes that move the learner's hour.
     */
    public function index(Request $request, ViewerTimezone $viewer, FamilyNotices $notices): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64'], 'when' => ['nullable', 'in:upcoming,past']]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $learner = $this->family->learner($me, $request->query('learner_id')) ?? throw new NotFoundHttpException;
        $now = CarbonImmutable::now();
        $today = $now->setTimezone($viewer->effective())->startOfDay()->utc();
        $rows = $request->query('when') === 'past'
            ? $this->lessons->forStudent($learner->id, $now->subWeeks(4), $today, latestFirst: true)
            : $this->lessons->forStudent($learner->id, $today, $now->addWeeks(4));
        $mayAct = $this->mayAct($me);

        return response()->json([
            'learner' => ['id' => $learner->id, 'name' => $learner->name],
            'data' => array_map(fn (object $r) => $this->present->lesson($r, $now, 'teacher', $mayAct, kids: ! $mayAct), $rows),
            /** Coming holidays; the learner's courses paused, ending or just ended; clock changes that move the learner's hour. */
            'notices' => [
                ...array_map(fn (array $h) => $h['name'].' · '.$h['period'], $notices->holidays()),
                ...$notices->aboutLearner($learner->id, $viewer->effective()),
            ],
        ]);
    }

    /**
     * What cancelling would do.
     *
     * @throws NotFoundHttpException not a lesson of someone I follow
     * @throws AccessDeniedHttpException started, over, or a child in the kids' look
     */
    public function cancellation(Request $request, string $session, FamilyCancellation $cancellation): JsonResponse
    {
        $lesson = $this->ownLesson($request, $session);
        $l = 'scheduling::family.cancel.';
        $seat = $this->seatOf($request, $lesson);

        return response()->json([
            /** The policy's answer: back to the balance, or counted. A group lesson's says the learner's seat is excused, the lesson goes on. */
            'outcome' => $cancellation->outcome($lesson, $lesson->isGroup() ? $seat->enrollment_id : null),
            /** A group lesson: "I won't attend" excuses the learner's own seat; the lesson is never cancelled. */
            'excuse_only' => $lesson->isGroup(),
            'reasons' => array_map(fn (string $r) => ['value' => $r, 'label' => __($l.'reasons.'.$r)], CancelSession::REASONS['student']),
        ]);
    }

    /**
     * Cancel a lesson.
     *
     * A group lesson is never cancelled: the learner's own seat is excused ("I won't attend") and the policy's notice
     * rule decides their credit; the lesson goes on for the others (learner_id names the seat when several are followed).
     * Send an Idempotency-Key: a retry answers the same without cancelling twice.
     *
     * @throws NotFoundHttpException not a lesson of someone I follow
     * @throws AccessDeniedHttpException started, over, or a child in the kids' look
     */
    public function cancel(CancelLessonRequest $request, string $session, CancelSession $cancel): JsonResponse
    {
        $lesson = $this->ownLesson($request, $session);
        $seat = $this->seatOf($request, $lesson);
        $cancel->handle($lesson, 'student', (string) $request->string('reason'), $request->input('note'), (string) $request->user()?->getAuthIdentifier(), studentId: $seat->user_id);

        return $this->one($lesson->refresh(), [], $seat->user_id);
    }

    /**
     * Turn down the substitute.
     *
     * The lesson is cancelled as the academy's doing and its minutes come back as makeup credit (makeup_days to book it).
     *
     * @throws NotFoundHttpException not a lesson of someone I follow
     * @throws AccessDeniedHttpException started, over, or a child in the kids' look
     */
    public function refuseSubstitute(Request $request, string $session, RefuseSubstitute $refuse): JsonResponse
    {
        $lesson = $this->ownLesson($request, $session);
        $refuse->handle($lesson, $request->input('note'), (string) $request->user()?->getAuthIdentifier());

        return $this->one($lesson->refresh(), ['makeup_days' => (int) PolicyVersion::settingsInForce()->get('makeup_validity_days')]);
    }

    /**
     * Dispute a lesson's outcome.
     *
     * Within 48 hours of the end of a lesson recorded as held or missed (can_dispute), once per lesson: it goes back
     * under review and the academy answers (the lesson's dispute). Send an Idempotency-Key.
     *
     * @throws NotFoundHttpException not a lesson of someone I follow
     * @throws AccessDeniedHttpException a child in the kids' look
     */
    public function dispute(DisputeLessonRequest $request, string $session, DisputeLesson $dispute): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $lesson = Str::isUuid($session) ? Session::query()->with('participants')->find($session) : null;
        $student = $lesson === null ? null : $this->followedSeat($me, $lesson, $request->input('learner_id'));
        if ($student === null) {
            throw new NotFoundHttpException;
        }
        $dispute->handle($lesson, 'family', $me, (string) $request->string('reason'), (string) $request->string('note'), $student->user_id);

        return $this->one($lesson->refresh(), [], $student->user_id);
    }

    /** @param  array<string, mixed>  $extra */
    private function one(Session $session, array $extra = [], ?string $studentId = null): JsonResponse
    {
        $studentId ??= $session->participants->firstWhere('role', ParticipantRole::Student)?->user_id;
        $row = collect($this->lessons->forStudent((string) $studentId, $session->starts_at, $session->starts_at->addSecond()))->firstWhere('id', $session->id);

        return response()->json(['data' => $this->present->lesson($row, CarbonImmutable::now(), 'teacher', true)] + $extra);
    }

    /**
     * The seat in the lesson of a learner this person follows: the learner named (learner_id), else the first
     * one they follow (a guardian of two in the same group lesson names which).
     */
    private function followedSeat(string $me, Session $session, mixed $learnerId): ?SessionParticipant
    {
        return $session->participants
            ->filter(fn ($p) => $p->role === ParticipantRole::Student && (! is_string($learnerId) || $learnerId === '' || $p->user_id === $learnerId))
            ->sortBy('user_id')
            ->first(fn ($p) => $this->family->follows($me, $p->user_id));
    }

    private function seatOf(Request $request, Session $session): SessionParticipant
    {
        return $this->followedSeat((string) $request->user()?->getAuthIdentifier(), $session, $request->input('learner_id')) ?? throw new NotFoundHttpException;
    }

    private function mayAct(string $viewerId): bool
    {
        return $this->family->stage($viewerId) !== LearnerStage::Kids;
    }

    /** A lesson of someone this person follows, still ahead and booked; anything else is not found or refused. */
    private function ownLesson(Request $request, string $id): Session
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $session = Str::isUuid($id) ? Session::query()->with('participants')->find($id) : null;
        $student = $session === null ? null : $this->followedSeat($me, $session, $request->input('learner_id'));
        if ($student === null) {
            throw new NotFoundHttpException;
        }
        // A group lesson: the learner's own seat must still be theirs to excuse.
        if (! $this->mayAct($me) || $session->status !== SessionStatus::Scheduled || ! $session->starts_at->isFuture()
            || ($session->isGroup() && (! $student->is_active || $student->outcome !== null))) {
            throw new AccessDeniedHttpException(__('scheduling::family.cancel.not_now'));
        }

        return $session;
    }
}
