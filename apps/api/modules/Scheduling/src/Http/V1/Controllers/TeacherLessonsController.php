<?php

namespace Modules\Scheduling\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Actions\TimeOff\WithdrawTimeOff;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\Scheduling\Http\V1\Requests\DisputeLessonRequest;
use Modules\Scheduling\Http\V1\Requests\TimeOffRequest;
use Modules\Scheduling\Http\V1\Resources\LessonPresenter;
use Modules\Scheduling\Queries\LessonList;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The teacher app: my lessons day by day (and disputing what one was recorded as), and my time off (ask, see the answer, withdraw). */
final class TeacherLessonsController
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /**
     * My lessons.
     *
     * From a day (default today, on my clock) for a number of days (default 7, at most 31).
     */
    public function lessons(Request $request, LessonList $lessons, LessonPresenter $present, ViewerTimezone $viewer): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'days' => ['nullable', 'integer', 'min:1', 'max:31']]);
        $tz = $viewer->effective();
        $now = CarbonImmutable::now();
        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->query('from'), $tz)->startOfDay() : $now->setTimezone($tz)->startOfDay();
        $to = $from->addDays((int) $request->query('days', 7));
        $rows = $lessons->forTeacher((string) $request->user()?->getAuthIdentifier(), $from->utc(), $to->utc());
        $held = array_filter($rows, fn (object $r) => $r->status !== 'cancelled');

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->subDay()->toDateString(),
            /** Lessons not cancelled, and their minutes. */
            'count' => count($held),
            'minutes' => (int) array_sum(array_map(fn (object $r) => $r->starts_at->diffInMinutes($r->ends_at), $held)),
            'data' => array_map(fn (object $r) => $present->lesson($r, $now, 'student'), $rows),
        ]);
    }

    /**
     * Dispute a lesson's outcome.
     *
     * Within 48 hours of the end of my lesson recorded as held or missed (can_dispute), once per lesson: it goes back
     * under review and the academy answers (the lesson's dispute). Send an Idempotency-Key.
     *
     * @throws NotFoundHttpException not my lesson
     */
    public function dispute(DisputeLessonRequest $request, string $session, DisputeLesson $dispute, LessonList $lessons, LessonPresenter $present): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $lesson = Str::isUuid($session) ? Session::query()->where('teacher_id', $me)->find($session) : null;
        if ($lesson === null) {
            throw new NotFoundHttpException;
        }
        $dispute->handle($lesson, 'teacher', $me, (string) $request->string('reason'), (string) $request->string('note'));
        $row = collect($lessons->forTeacher($me, $lesson->starts_at, $lesson->starts_at->addSecond()))->firstWhere('id', $lesson->id);

        return response()->json(['data' => $present->lesson($row, CarbonImmutable::now(), 'student')]);
    }

    /** My time off: requests of the last two months and every one still waiting, with the academy's holidays. */
    public function timeOff(Request $request): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $requests = TeacherTimeOff::query()->where('teacher_id', $me)
            ->where(fn ($q) => $q->where('ends_at', '>', CarbonImmutable::now()->subDays(60))->orWhere('status', 'pending'))
            ->orderByDesc('starts_at')->limit(30)->get();

        return response()->json([
            'data' => $requests->map(fn (TeacherTimeOff $t) => $this->timeOffRow($t))->all(),
            'holidays' => AcademyHoliday::query()->where('ends_at', '>', CarbonImmutable::now())->orderBy('starts_at')->limit(5)->get()
                ->map(fn (AcademyHoliday $h) => ['name' => $h->name, 'period' => TimeOffView::holiday($h), 'starts_at' => $this->time->present($h->starts_at), 'ends_at' => $this->time->present($h->ends_at)])->all(),
        ]);
    }

    /**
     * Ask for time off.
     *
     * Whole days (first_day to last_day) or part of one day (first_day, from, to), on my own clock. The academy decides.
     */
    public function requestTimeOff(TimeOffRequest $request, RequestTimeOff $ask): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $first = (string) $request->string('first_day');
        $partOfDay = $request->filled('from');
        $timeOff = $ask->handle(
            $me, $first, $partOfDay ? $first : (string) ($request->input('last_day') ?? $first),
            $partOfDay ? (string) $request->string('from') : null, $partOfDay ? (string) $request->string('to') : null,
            (string) $request->string('reason'), $request->input('note'), $me,
        );

        return response()->json(['data' => $this->timeOffRow($timeOff)], 201);
    }

    /** Withdraw a request still waiting. */
    public function withdrawTimeOff(Request $request, string $timeOff, WithdrawTimeOff $withdraw): Response
    {
        $own = TeacherTimeOff::query()->where('teacher_id', (string) $request->user()?->getAuthIdentifier())->findOrFail($timeOff);
        $withdraw->handle($own, (string) $request->user()?->getAuthIdentifier());

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function timeOffRow(TeacherTimeOff $t): array
    {
        $l = 'scheduling::timeoff.';

        return [
            'id' => $t->id,
            'starts_at' => $this->time->present($t->starts_at, $t->timezone),
            'ends_at' => $this->time->present($t->ends_at, $t->timezone),
            'all_day' => $t->all_day,
            'period' => TimeOffView::period($t),
            'reason' => $t->reason,
            'reason_label' => __($l.'reasons.'.$t->reason),
            'note' => $t->note,
            /** pending, approved, rejected, withdrawn */
            'status' => $t->status->value,
            'status_label' => __($l.'statuses.'.$t->status->value),
            'decision_note' => $t->decision_note,
            /** Recorded by the academy, not asked by me. */
            'by_academy' => $t->requested_by !== $t->teacher_id,
            /** For approved leave: my lessons in it and how each was handled. */
            'cover' => $t->status === TimeOffStatus::Approved ? TimeOffView::summary($t) : null,
            'can_withdraw' => $t->status === TimeOffStatus::Pending && ! $t->ends_at->isPast(),
        ];
    }
}
