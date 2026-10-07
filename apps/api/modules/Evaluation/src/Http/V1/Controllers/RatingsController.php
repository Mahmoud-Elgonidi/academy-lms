<?php

namespace Modules\Evaluation\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\Actions\RateLesson;
use Modules\Evaluation\Filament\KpiView;
use Modules\Evaluation\Filament\Pages\TeacherPerformance;
use Modules\Evaluation\Models\LessonRating;
use Modules\Evaluation\Queries\PerformanceEvents;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One-tap lesson ratings from the family app (the student or a guardian, a
 * lesson that took place within two weeks), and the teacher's own numbers:
 * KPIs, what moved them, and ratings without names.
 */
final class RatingsController
{
    /**
     * My rating of a lesson.
     *
     * can_rate is false (with why) for a lesson that did not take place, is too old, or is not mine to rate.
     */
    public function show(Request $request, string $session, RateLesson $rate): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $this->known($session);
        try {
            $rate->check($session, $me);
        } catch (ValidationException $e) {
            return response()->json(['can_rate' => false, 'why' => collect($e->errors())->flatten()->first()] + $this->empty());
        }

        return response()->json(['can_rate' => true, 'why' => null] + $this->rating(LessonRating::query()->where('session_id', $session)->where('rater_id', $me)->first()));
    }

    /**
     * Rate a lesson.
     *
     * Stars first; tags from choices (good ones for 4–5 stars, the others below); rating again replaces it.
     */
    public function save(Request $request, string $session, RateLesson $rate): JsonResponse
    {
        $request->validate([
            'stars' => ['required', 'integer', 'between:1,5'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:30'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $this->known($session);
        $rating = $rate->handle($session, (string) $request->user()?->getAuthIdentifier(), (int) $request->integer('stars'), (array) $request->input('tags', []), $request->input('comment'));

        return response()->json(['can_rate' => true, 'why' => null] + $this->rating($rating));
    }

    /**
     * My performance.
     *
     * The last 90 days: each KPI with its target, the lessons that moved them, and ratings without names.
     */
    public function performance(Request $request, TeacherKpis $kpis, PerformanceEvents $events, ViewerTimezone $viewer): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();

        return response()->json([
            'days' => TeacherKpis::DAYS,
            'kpis' => KpiView::cards($kpis->forTeachers([$me])[$me] ?? []),
            'events' => array_map(fn (array $e) => KpiView::event($e, $viewer->effective(), anonymousRatings: true), $events->forTeacher($me)),
            'ratings' => TeacherPerformance::ratingsOf($me, names: false),
        ]);
    }

    private function known(string $session): void
    {
        if (! Str::isUuid($session)) {
            throw new NotFoundHttpException;
        }
    }

    /** @return array<string, mixed> */
    private function rating(?LessonRating $rating): array
    {
        return [
            'stars' => $rating?->stars,
            'tags' => $rating?->tags ?? [],
            'comment' => $rating?->comment,
        ] + $this->choices();
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return ['stars' => null, 'tags' => [], 'comment' => null] + $this->choices();
    }

    /** @return array{choices: array{good: list<array{value: string, label: string}>, bad: list<array{value: string, label: string}>}} */
    private function choices(): array
    {
        return ['choices' => array_map(
            fn (array $tags) => array_map(fn (string $t) => ['value' => $t, 'label' => __('evaluation::ratings.tags.'.$t)], $tags),
            LessonRating::TAGS,
        )];
    }
}
