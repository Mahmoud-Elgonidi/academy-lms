<?php

namespace Modules\Crm\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Crm\Actions\BookNextTrial;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\FamilyTrials;
use Modules\Crm\Queries\TrialReport;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A signed-in family's free trials (docs/architecture/02, section 27, J2):
 * the learner's trials, a trial's report, and one more trial from it (the
 * free second trial with another teacher, or a new time after one that did
 * not take place). Prices, the offer and booking are for the classic look
 * (a guardian or an adult); a child's own screen reads the report without
 * them, and cannot book.
 */
final class FamilyTrialsController
{
    public function __construct(
        private readonly FamilyView $family,
        private readonly ViewerTimezone $viewer,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * A learner's free trials.
     *
     * Latest first: the subject, when, the teacher's first name, how it went, whether its report is ready, and (classic look)
     * one more trial it allows with why not.
     */
    public function index(Request $request, FamilyTrials $trials): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $me = $this->me($request);
        $learner = $this->family->learner($me, $request->query('learner_id')) ?? throw new NotFoundHttpException;
        $stage = $this->family->stage($me);

        return response()->json([
            'learner' => ['id' => $learner->id, 'name' => $learner->name],
            /** The viewer's look: kids, middle, teen or classic (prices, the offer and booking only in classic). */
            'viewer_stage' => $stage->value,
            'data' => $trials->of($learner->id, $stage === LearnerStage::Classic, $this->viewer->effective()),
        ]);
    }

    /**
     * A free trial's report.
     *
     * As the family's link shows it (GET site/trials/report/{token}); in a look other than classic, without prices, the offer or the
     * second trial. Not found until the trial took place and was assessed.
     */
    public function show(Request $request, string $session, TrialReport $reports): JsonResponse
    {
        $me = $this->me($request);
        $trial = $this->own($me, $session);
        if (! TrialReport::ready($trial)) {
            throw new NotFoundHttpException;
        }
        $stage = $this->family->stage($me);

        return response()->json([
            'viewer_stage' => $stage->value,
            'data' => $reports->present($trial, $stage === LearnerStage::Classic, $this->viewer->effective(), viewerId: $me),
        ]);
    }

    /**
     * Times for one more free trial from this one.
     *
     * The second trial (any teacher but the first) after a report, or a new time after a trial that did not take place; on the
     * viewer's clock over 14 days from `from`. 422 with why it cannot be booked; 403 outside the classic look.
     */
    public function slots(Request $request, string $session, BookNextTrial $book): JsonResponse
    {
        $me = $this->me($request);
        $trial = $this->bookable($me, $session);
        $valid = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'timezone' => ['nullable', Rule::in(Timezones::all())]]);
        $zone = $valid['timezone'] ?? $this->viewer->effective();
        $found = $book->slots($trial, true, isset($valid['from']) ? CarbonImmutable::parse($valid['from'], $zone) : null, $zone);

        return response()->json(['data' => TrialReportController::present($found, $zone, $this->time)]);
    }

    /**
     * Book one more free trial from this one.
     *
     * starts_at: one of the times offered. Send an Idempotency-Key. The family hears the time and its links; 422 with why not.
     */
    public function book(Request $request, string $session, BookNextTrial $book): JsonResponse
    {
        $me = $this->me($request);
        $trial = $this->bookable($me, $session);
        $valid = $request->validate(['starts_at' => ['required', 'date']]);
        $sessionId = $book->handle($trial, CarbonImmutable::parse($valid['starts_at'])->utc(), true, $me);

        return response()->json(['data' => TrialReportController::booked($sessionId, $this->viewer->effective(), $this->time)], 201);
    }

    private function me(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }

    /** A trial of a learner the viewer follows (by its lesson). */
    private function own(string $me, string $session): LeadTrial
    {
        $trial = LeadTrial::query()->with('lead')->where('session_id', $session)->first();
        if ($trial === null || $trial->lead?->learner_id === null || ! $this->family->follows($me, $trial->lead->learner_id)) {
            throw new NotFoundHttpException;
        }

        return $trial;
    }

    private function bookable(string $me, string $session): LeadTrial
    {
        $trial = $this->own($me, $session);
        if ($this->family->stage($me) !== LearnerStage::Classic) {
            throw new AccessDeniedHttpException(__('crm::report.next.why.family_only'));
        }

        return $trial;
    }
}
