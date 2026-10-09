<?php

namespace Modules\Evaluation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Evaluation\Actions\RateLesson;
use Modules\Evaluation\Models\LessonRating;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The page behind the link in "how was the lesson?": five stars, one tap
 * saves it; then, if they like, a few quick words and a comment. In the
 * academy's look, in the person's language, no sign-in (the link is signed
 * for them and lasts a week).
 */
final class RateLessonController
{
    public const LINK_DAYS = 7;

    public function show(Request $request, string $session, RateLesson $rate, BrandingApi $branding, TenantContext $tenants): View
    {
        $raterId = (string) $request->query('u');
        $this->speak($raterId, $tenants);
        try {
            $lesson = $rate->check($session, $raterId);
            $problem = null;
        } catch (ValidationException $e) {
            [$lesson, $problem] = [null, collect($e->errors())->flatten()->first()];
        }
        $rating = $lesson ? LessonRating::query()->where('session_id', $session)->where('rater_id', $raterId)->first() : null;
        $tz = DB::table('users')->where('id', $raterId)->value('timezone') ?? $tenants->require()->defaultTimezone;

        return view('evaluation::rate.show', [
            'lesson' => $lesson,
            'problem' => $problem,
            'rating' => $rating,
            'saved' => $request->boolean('saved'),
            'when' => $lesson ? CarbonImmutable::parse($lesson->starts_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i') : null,
            'subject' => $lesson ? (app()->getLocale() === 'ar' ? $lesson->subject_ar : $lesson->subject_en) : null,
            'action' => URL::temporarySignedRoute('evaluation.rate.store', now()->addHours(12), ['session' => $session, 'u' => $raterId], absolute: false),
            'branding' => $branding->current(),
        ]);
    }

    public function store(Request $request, string $session, RateLesson $rate, TenantContext $tenants): RedirectResponse
    {
        $raterId = (string) $request->query('u');
        $this->speak($raterId, $tenants);
        $current = LessonRating::query()->where('session_id', $session)->where('rater_id', $raterId)->first();
        $stars = (int) ($request->input('stars') ?? $current?->stars ?? 0);
        $rate->handle($session, $raterId, $stars, array_values((array) $request->input('tags', $current?->tags ?? [])),
            $request->has('comment') ? $request->input('comment') : $current?->comment, 'link');

        return redirect(URL::temporarySignedRoute('evaluation.rate', now()->addDays(self::LINK_DAYS), ['session' => $session, 'u' => $raterId, 'saved' => 1], absolute: false));
    }

    private function speak(string $raterId, TenantContext $tenants): void
    {
        // A link always names who rates, someone of this academy.
        $user = Str::isUuid($raterId) ? DB::table('users')->where('id', $raterId)->first(['locale']) : null;
        abort_if($user === null, 404);
        app()->setLocale($user->locale ?: $tenants->require()->defaultLocale);
    }
}
