<?php

namespace Modules\Site\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\People\Contracts\DTOs\PublicTeacher;
use Modules\People\Contracts\DTOs\PublicTeacherSubject;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Http\FormToken;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/**
 * The academy's public page (on its own host, before anyone signs in): what
 * it says, its subjects, the teachers who chose to show, its plans and
 * prices, and the form to ask for a free trial or to teach there. Nothing
 * while the academy keeps its page off. Lists are shaped with plain loops:
 * Scramble's inference of collection closures breaks other endpoints.
 */
final class SiteController
{
    /**
     * The academy's page.
     *
     * In the request's language (Accept-Language). Not found while the academy keeps its page off.
     */
    public function show(TenantContext $tenants, CatalogApi $catalog, CurriculumApi $curriculum, PeopleApi $people, BillingApi $billing): JsonResponse
    {
        $settings = SiteSettings::current();
        abort_unless($settings->exists && $settings->enabled, 404);
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $academy = $tenants->require();
        $said = $settings->said();
        $content = SiteContent::localized($said, $locale);

        $subjects = [];
        foreach ($catalog->subjectNames() as $id => $name) {
            $programs = [];
            foreach ($curriculum->programsFor((string) $id) as $program) {
                $programs[] = [
                    'name' => $program->name,
                    /** What the program is, or null. */
                    'description' => $program->description !== null ? (string) $program->description : null,
                ];
            }
            $levels = [];
            foreach ($catalog->levels((string) $id) as $level) {
                $levels[] = (string) $level;
            }
            $categories = [];
            foreach ($catalog->categories((string) $id) as $category) {
                $categories[] = (string) $category;
            }
            $subjects[] = [
                /** Sent back with the free-trial form as subject_id. */
                'id' => (string) $id,
                'name' => (string) $name,
                /** The subject's levels, in order. */
                'levels' => $levels,
                /** Its tracks or the curricula it follows. */
                'categories' => $categories,
                /** Its published programs. */
                'programs' => $programs,
            ];
        }

        $teachers = [];
        if (SiteContent::shows($said, 'teachers')) {
            foreach ($people->publicTeachers() as $teacher) {
                $teachers[] = $this->teacher($teacher);
            }
        }

        $plans = [];
        if (SiteContent::shows($said, 'pricing')) {
            $groupSize = $people->largestGroupSize();
            foreach ($billing->planOptions() as $plan) {
                $plans[] = $this->plan($plan, $locale, $groupSize);
            }
        }

        $regions = [];
        foreach ($billing->regions() as $region) {
            $regions[] = $this->region($region);
        }

        return response()->json([
            'data' => [
                'academy' => [
                    /** The academy's name in the request's language. */
                    'name' => $locale === 'ar' ? $academy->nameAr : $academy->nameEn,
                    /** The academy's code: what the app asks for to open it (X-Academy). */
                    'code' => $academy->slug,
                ],
                /** The words the academy set: hero, features, testimonials, faqs, sections shown, whatsapp, seo. */
                'content' => $content,
                /** Every subject the academy teaches (the free-trial form picks one). */
                'subjects' => $subjects,
                /** The teachers who chose to show on the page; empty when the section is off. */
                'teachers' => $teachers,
                /** The plans families enrol on, with a price in each region; empty when the section is off. */
                'plans' => $plans,
                /** The price regions: the page picks the visitor's by their country, else the default one. */
                'regions' => $regions,
                'app' => [
                    /** The store page on Android, or null. */
                    'android_url' => config('platform.app.android_url'),
                    /** The App Store page, or null. */
                    'ios_url' => config('platform.app.ios_url'),
                ],
                /** The academy's WhatsApp number (E.164), or null. */
                'whatsapp' => $content['whatsapp'],
                /** Sent back with the request form, unchanged. */
                'form_token' => FormToken::issue(SubmitJoinRequest::FORM),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Ask for a free trial lesson, or to teach at the academy.
     *
     * kind is trial (subject_id, for_child with child_name and child_birth_year) or teach (subject_ids, years_teaching).
     * The academy answers each request. A form sent too soon or with the hidden field filled is answered the same and kept nowhere.
     * Not found while the academy keeps its page off.
     */
    public function store(Request $request, SubmitJoinRequest $submit): JsonResponse
    {
        abort_unless(SiteSettings::isOn(), 404);
        $submit->handle($request->only([
            'kind', 'name', 'phone', 'email', 'country', 'timezone', 'subject_id', 'for_child', 'child_name', 'child_birth_year',
            'subject_ids', 'years_teaching', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website',
        ]), app()->getLocale());

        return response()->json(['data' => ['received' => true]], 201);
    }

    /** @return array{id: string, name: string, bio: ?string, photo_url: ?string, gender: ?string, languages: list<string>, age_groups: list<string>, subjects: list<array{name: string, level_from: ?string, level_to: ?string}>, accepts_trials: bool, accepts_groups: bool} */
    private function teacher(PublicTeacher $teacher): array
    {
        $subjects = [];
        foreach ($teacher->subjects as $subject) {
            $subjects[] = $this->subject($subject);
        }

        return [
            /** The card's id (not the teacher's account). */
            'id' => $teacher->id,
            'name' => $teacher->displayName,
            'bio' => $teacher->bio,
            'photo_url' => $teacher->photoUrl,
            /** female or male. */
            'gender' => $teacher->gender,
            /** ISO 639-1 codes they explain in. */
            'languages' => $teacher->languages,
            /** kids, middle, teen, adults: the ages they teach (none: any age). */
            'age_groups' => $teacher->ageGroups,
            'subjects' => $subjects,
            /** Whether they take free trial lessons. */
            'accepts_trials' => $teacher->acceptsTrials,
            /** Whether they teach group lessons. */
            'accepts_groups' => $teacher->acceptsGroups,
        ];
    }

    /** @return array{name: string, level_from: ?string, level_to: ?string} */
    private function subject(PublicTeacherSubject $subject): array
    {
        return [
            'name' => $subject->name,
            /** The first level they teach, or null (from the start). */
            'level_from' => $subject->levelFrom,
            /** The last level they teach, or null (to the end). */
            'level_to' => $subject->levelTo,
        ];
    }

    /** @return array{id: string, name: string, format: string, max_students: ?int, session_minutes: int, sessions_per_week: int, sessions_per_block: int, validity_days: int, shared: bool, prices: list<array{region_id: string, price: string, amount_minor: int, currency: string}>} */
    private function plan(PlanOption $plan, string $locale, ?int $groupSize): array
    {
        $prices = [];
        foreach ($plan->prices as $regionId => $money) {
            $prices[] = [
                'region_id' => (string) $regionId,
                /** The block's price, written out ("SAR 480"). */
                'price' => $money->format($locale),
                'amount_minor' => (int) $money->minor,
                'currency' => $money->currency,
            ];
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            /** private (one student a lesson) or group. */
            'format' => $plan->group ? 'group' : 'private',
            /** A group plan: the most students in one of its lessons (the largest group any of the academy's teachers takes); null for a private plan. */
            'max_students' => $plan->group ? $groupSize : null,
            'session_minutes' => $plan->sessionMinutes,
            'sessions_per_week' => $plan->sessionsPerWeek,
            /** Lessons in each paid block. */
            'sessions_per_block' => $plan->sessionsPerBlock,
            /** Days a block lasts from payment. */
            'validity_days' => $plan->validityDays,
            /** One balance for all of a student's courses on it. */
            'shared' => $plan->shared,
            'prices' => $prices,
        ];
    }

    /** @return array{id: string, name: string, currency: string, default: bool, countries: list<string>} */
    private function region(RegionOption $region): array
    {
        return [
            'id' => $region->id,
            'name' => $region->name,
            'currency' => $region->currency,
            /** The region for a visitor whose country no region names. */
            'default' => $region->isDefault,
            /** ISO 3166-1 alpha-2 codes of the countries it serves. */
            'countries' => $region->countries,
        ];
    }
}
