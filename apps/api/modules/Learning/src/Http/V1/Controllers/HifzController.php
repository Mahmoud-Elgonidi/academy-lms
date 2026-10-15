<?php

namespace Modules\Learning\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\Hifz\GradeHifzPage;
use Modules\Learning\Actions\Hifz\ReciteHifzPage;
use Modules\Learning\Http\V1\Resources\HifzPresenter;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\HifzRecitation;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A learner's hifz map and daily review for the apps and the portal (the
 * student, or a guardian looking at their child): where each page stands,
 * today's pages, a page's text, and reciting a page to be checked (while the
 * academy has the recitation check on) or saying how it went.
 */
final class HifzController
{
    public function __construct(
        private readonly FamilyView $family,
        private readonly HifzPresenter $present,
    ) {}

    /**
     * A learner's hifz map.
     *
     * Every page of the mushaf by part (new, learning, or memorized and strong, fading or weak now), how many of
     * each, and today's review on the learner's clock. mode: check (recite a page; it is heard and checked) or self
     * (say how it went). The latest page recited in the last day comes with its check.
     */
    public function show(Request $request, AcademyAi $ai, CurriculumApi $curriculum): JsonResponse
    {
        $learner = $this->learner($request);
        $now = CarbonImmutable::now();
        $latest = HifzRecitation::query()->where('student_id', $learner)->where('created_at', '>=', $now->subDay())->latest('created_at')->first();

        return response()->json(['data' => [
            'learner_id' => $learner,
            /** check: recite a page and it is checked word by word; self: say how it went */
            'mode' => $ai->on(AiFeature::RecitationCheck) && $curriculum->quranPages() !== [] ? 'check' : 'self',
            ...$this->present->map($learner, $now),
            'wird' => $this->present->wird($learner, $now),
            'latest_recitation' => $latest === null ? null : $this->present->recitation($latest),
        ]]);
    }

    /**
     * A page's text.
     *
     * Its verses as Tanzil writes them (perhaps the end of one surah and the start of the next), with where it
     * stands on the learner's map.
     */
    public function page(Request $request, int $page, CurriculumApi $curriculum): JsonResponse
    {
        $learner = $this->learner($request);
        $found = $curriculum->quranPage($page) ?? throw new NotFoundHttpException;
        $card = HifzPage::query()->where('student_id', $learner)->where('page', $page)->first();

        return response()->json(['data' => $this->present->page($found, $card, CarbonImmutable::now())]);
    }

    /**
     * Recite a page.
     *
     * The recording (audio) is heard and checked against the page in the background; GET learning/hifz/recitations/{id}
     * (or the hifz signal) gives the result, which counts as the page's review. Only while the academy has the
     * recitation check on (else ai-off); the recording is deleted once heard.
     */
    public function recite(Request $request, int $page, ReciteHifzPage $recite): JsonResponse
    {
        $learner = $this->learner($request);
        $recitation = $recite->handle($learner, $page, $request->file('audio'), (string) $request->user()->getAuthIdentifier());

        return response()->json(['data' => $this->present->recitation($recitation)], 202);
    }

    /** A recited page's check. */
    public function recitation(Request $request, string $recitation): JsonResponse
    {
        $learner = $this->learner($request);
        $found = HifzRecitation::query()->where('student_id', $learner)->find($recitation) ?? throw new NotFoundHttpException;

        return response()->json(['data' => $this->present->recitation($found)]);
    }

    /**
     * Say how a page went.
     *
     * grade 1 forgot, 2 hard, 3 good, 4 easy: the page's review for today (once a day; the first word counts).
     */
    public function grade(Request $request, int $page, GradeHifzPage $grade, CurriculumApi $curriculum): JsonResponse
    {
        $request->validate(['grade' => ['required', 'integer', 'between:1,4']]);
        $learner = $this->learner($request);
        $found = $curriculum->quranPage($page) ?? throw new NotFoundHttpException;
        $card = $grade->handle($learner, $page, (int) $request->input('grade'));

        return response()->json(['data' => $this->present->page($found, $card, CarbonImmutable::now())]);
    }

    private function learner(Request $request): string
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $asked = $request->query('learner_id', $request->input('learner_id'));

        return $this->family->learner((string) $request->user()?->getAuthIdentifier(), is_string($asked) && $asked !== '' ? $asked : null)?->id
            ?? throw new NotFoundHttpException;
    }
}
