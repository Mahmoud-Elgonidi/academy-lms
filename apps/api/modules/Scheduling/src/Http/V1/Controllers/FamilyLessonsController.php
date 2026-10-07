<?php

namespace Modules\Scheduling\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\RefuseSubstitute;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Http\V1\Requests\CancelLessonRequest;
use Modules\Scheduling\Http\V1\Resources\LessonPresenter;
use Modules\Scheduling\Queries\FamilyCancellation;
use Modules\Scheduling\Queries\FamilyNotices;
use Modules\Scheduling\Queries\LessonList;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A learner's lessons for the family app, and what the family may do with
 * one: cancel it under the academy's policy (told the outcome first), or
 * turn down a substitute. A child in the kids' look does neither.
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
     * notices: coming holidays and clock changes that move the learner's hour.
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
            'data' => array_map(fn (object $r) => $this->present->lesson($r, $now, 'teacher', $mayAct), $rows),
            /** @var list<string> */
            'notices' => [
                ...array_map(fn (array $h) => $h['name'].' · '.$h['period'], $notices->holidays()),
                ...$notices->clockChanges($learner->id, $viewer->effective()),
            ],
        ]);
    }

    /** What cancelling would do. */
    public function cancellation(Request $request, string $session, FamilyCancellation $cancellation): JsonResponse
    {
        $lesson = $this->ownLesson($request, $session);
        $l = 'scheduling::family.cancel.';

        return response()->json([
            /** The policy's answer: back to the balance, or counted. */
            'outcome' => $cancellation->outcome($lesson),
            'reasons' => array_map(fn (string $r) => ['value' => $r, 'label' => __($l.'reasons.'.$r)], CancelSession::REASONS['student']),
        ]);
    }

    /**
     * Cancel a lesson.
     *
     * Send an Idempotency-Key: a retry answers the same without cancelling twice.
     */
    public function cancel(CancelLessonRequest $request, string $session, CancelSession $cancel): JsonResponse
    {
        $lesson = $this->ownLesson($request, $session);
        $cancel->handle($lesson, 'student', (string) $request->string('reason'), $request->input('note'), (string) $request->user()?->getAuthIdentifier());

        return $this->one($lesson->refresh());
    }

    /**
     * Turn down the substitute.
     *
     * The lesson is cancelled as the academy's doing and its minutes come back as makeup credit (makeup_days to book it).
     */
    public function refuseSubstitute(Request $request, string $session, RefuseSubstitute $refuse): JsonResponse
    {
        $lesson = $this->ownLesson($request, $session);
        $refuse->handle($lesson, $request->input('note'), (string) $request->user()?->getAuthIdentifier());

        return $this->one($lesson->refresh(), ['makeup_days' => (int) PolicyVersion::settingsInForce()->get('makeup_validity_days')]);
    }

    /** @param  array<string, mixed>  $extra */
    private function one(Session $session, array $extra = []): JsonResponse
    {
        $student = $session->participants->firstWhere('role', ParticipantRole::Student);
        $row = collect($this->lessons->forStudent((string) $student?->user_id, $session->starts_at, $session->starts_at->addSecond()))->firstWhere('id', $session->id);

        return response()->json(['data' => $this->present->lesson($row, CarbonImmutable::now(), 'teacher', true)] + $extra);
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
        $student = $session?->participants->firstWhere('role', ParticipantRole::Student);
        if ($student === null || ! $this->family->follows($me, $student->user_id)) {
            throw new NotFoundHttpException;
        }
        if (! $this->mayAct($me) || $session->status !== SessionStatus::Scheduled || ! $session->starts_at->isFuture()) {
            throw new AccessDeniedHttpException(__('scheduling::family.cancel.not_now'));
        }

        return $session;
    }
}
