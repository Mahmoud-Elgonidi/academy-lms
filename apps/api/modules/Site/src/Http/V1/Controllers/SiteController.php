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
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Http\FormToken;
use Modules\SharedKernel\Http\ProblemException;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Domain\Page\LegacyContent;
use Modules\Site\Domain\Page\LegacyImport;
use Modules\Site\Domain\Page\Localize;
use Modules\Site\Domain\Page\MediaRefs;
use Modules\Site\Domain\Page\PageContext;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SiteSettings;
use Modules\Site\Support\PreviewLinks;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * In the request's language (Accept-Language): the published page the academy built (`page`: its settings and its visible
     * blocks in order), and the live lists its blocks read. Not found while the academy keeps its page off.
     */
    public function show(TenantContext $tenants, CatalogApi $catalog, CurriculumApi $curriculum, PeopleApi $people, BillingApi $billing, BrandingApi $branding): JsonResponse
    {
        $settings = SiteSettings::current();
        abort_unless($settings->shows(), 404);
        $page = SitePage::current();
        $doc = $page->publishedDoc() ?? LegacyImport::fromContent($settings->said());
        $revision = $page->published !== null ? (int) $page->published_revision : 0;

        return response()->json(['data' => $this->payload($doc, $revision, $settings, $tenants, $catalog, $curriculum, $people, $billing, $branding, FormToken::issue(SubmitJoinRequest::FORM))])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * The academy's page as its draft stands: the builder's live preview.
     *
     * The same shape as `GET v1/site`, from the draft, even while the page is off, with `preview` added and an empty `form_token`
     * (the preview's form does not send). token is the builder's preview token (two hours): another academy's or a forged one is
     * not found; an expired one is gone (410, link-expired).
     */
    public function preview(Request $request, TenantContext $tenants, CatalogApi $catalog, CurriculumApi $curriculum, PeopleApi $people, BillingApi $billing, BrandingApi $branding): JsonResponse
    {
        $token = $request->query('token');
        $token = is_string($token) ? $token : null;
        if (! PreviewLinks::valid($token)) {
            if (PreviewLinks::expired($token)) {
                throw new ProblemException(410, 'link-expired', (string) __('site::builder.preview.expired'));
            }
            throw new NotFoundHttpException;
        }
        abort_unless(app(AcademyPlan::class)->has(Feature::Site), 404);
        $settings = SiteSettings::current();
        $page = SitePage::current();
        $data = $this->payload($page->draftDoc(), (int) $page->draft_revision, $settings, $tenants, $catalog, $curriculum, $people, $billing, $branding, '');
        $data['preview'] = [
            /** The draft's revision shown. */
            'revision' => (int) $page->draft_revision,
            /** The revision that is live, or null when nothing was published yet. */
            'published_revision' => $page->published !== null ? (int) $page->published_revision : null,
            /** Whether the page is on (it may be built before it is). */
            'enabled' => $settings->shows(),
        ];

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function payload(array $doc, int $revision, SiteSettings $settings, TenantContext $tenants, CatalogApi $catalog, CurriculumApi $curriculum, PeopleApi $people, BillingApi $billing, BrandingApi $branding, string $formToken): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $academy = $tenants->require();

        $subjects = [];
        $subjectIds = [];
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
            $subjectIds[] = (string) $id;
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

        // The live blocks that show, and their filters (never sent: applied to the lists below).
        $teachersBlock = null;
        $pricingBlock = null;
        foreach ($doc['blocks'] as $block) {
            if ($block['hidden']) {
                continue;
            }
            if ($block['type'] === 'teachers' && $teachersBlock === null) {
                $teachersBlock = $block;
            }
            if ($block['type'] === 'pricing' && $pricingBlock === null) {
                $pricingBlock = $block;
            }
        }

        $teachers = [];
        if ($teachersBlock !== null) {
            $cards = [];
            foreach ($people->publicTeachers() as $teacher) {
                $cards[$teacher->id] = $teacher;
            }
            $only = $teachersBlock['data']['only'] ?? [];
            $chosen = [];
            if ($only === []) {
                $chosen = array_values($cards);
            } else {
                foreach ($only as $id) {
                    if (isset($cards[$id])) {
                        $chosen[] = $cards[$id];
                    }
                }
            }
            foreach (array_slice($chosen, 0, (int) ($teachersBlock['data']['max'] ?? 12)) as $teacher) {
                $teachers[] = $this->teacher($teacher);
            }
        }

        $plans = [];
        if ($pricingBlock !== null) {
            $groupSize = $people->largestGroupSize();
            $options = [];
            foreach ($billing->planOptions() as $plan) {
                $options[$plan->id] = $plan;
            }
            $only = $pricingBlock['data']['only'] ?? [];
            $chosen = [];
            if ($only === []) {
                $chosen = array_values($options);
            } else {
                foreach ($only as $id) {
                    if (isset($options[$id])) {
                        $chosen[] = $options[$id];
                    }
                }
            }
            foreach ($chosen as $plan) {
                $plans[] = $this->plan($plan, $locale, $groupSize);
            }
        }

        $regions = [];
        foreach ($billing->regions() as $region) {
            $regions[] = $this->region($region);
        }

        $media = [];
        $ids = MediaRefs::of($doc);
        if ($ids !== []) {
            foreach (SiteMedia::query()->whereIn('id', $ids)->get() as $file) {
                $media[$file->id] = [
                    'kind' => $file->kind, 'mime' => $file->mime, 'url' => $file->url(), 'width' => $file->width, 'height' => $file->height,
                    'alt' => ['ar' => (string) ($file->alt['ar'] ?? ''), 'en' => (string) ($file->alt['en'] ?? '')],
                ];
            }
        }
        $tokens = $branding->current()->tokens;
        $localized = Localize::page($doc, new PageContext(
            locale: $locale,
            media: $media,
            subjects: $subjectIds,
            brandFonts: ['heading' => (string) $tokens['heading_font'], 'body' => (string) $tokens['body_font']],
            fontsUrl: fn (string ...$families): string => $branding->fontsUrl(...$families),
        ));
        $content = LegacyContent::from($doc, $locale);

        return [
            'academy' => [
                /** The academy's name in the request's language. */
                'name' => $locale === 'ar' ? $academy->nameAr : $academy->nameEn,
                /** The academy's code: what the app asks for to open it (X-Academy). */
                'code' => $academy->slug,
            ],
            /**
             * The page the academy built: revision (the published one), settings (audience, fonts, accent, mood, corners,
             * motion, header, footer, whatsapp, floating_whatsapp, seo) and blocks (visible only, in order: id, type, variant,
             * anchor, nav, style, data; see docs/architecture/site-builder.md, sections 3 and 8.3).
             */
            'page' => [
                'revision' => $revision,
                'settings' => $localized['settings'],
                /** @var list<array<string, mixed>> */
                'blocks' => $localized['blocks'],
            ],
            /** Deprecated: the old page's words (hero, why, features, testimonials, faqs, sections shown, whatsapp, seo), made from `page`. */
            'content' => $content,
            /** Every subject the academy teaches (the free-trial form picks one). */
            'subjects' => $subjects,
            /** The teachers its teachers block shows, in its order; empty without a visible teachers block. */
            'teachers' => $teachers,
            /** The plans its pricing block shows, with a price in each region; empty without a visible pricing block. */
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
            'whatsapp' => $localized['settings']['whatsapp'],
            /** How the academy takes a free trial asked for on the page. */
            'trials' => [
                /** family: the family picks its own time (site/trial-slots, then a code to its number: site/trials); staff: the academy books it after the request. */
                'booking' => $settings->trial_booking,
                /** How many hours the academy promises to answer a request within. */
                'response_hours' => (int) $settings->response_hours,
                /** A family books its own trial at least this many hours ahead. */
                'notice_hours' => (int) $settings->trial_notice_hours,
                /** Whether a family that asks for help choosing starts with the academy's own trial subject (else sales choose with them). */
                'help_choose_subject' => $settings->default_trial_subject_id !== null,
                /** That subject (one of `subjects`), whose times a family that asks for help choosing books from; null when sales choose with them. */
                'help_choose_subject_id' => $settings->default_trial_subject_id !== null ? (string) $settings->default_trial_subject_id : null,
            ],
            /** Sent back with the request form, unchanged ("" in the preview: its form does not send). */
            'form_token' => $formToken,
        ];
    }

    /**
     * Ask for a free trial lesson, or to teach at the academy.
     *
     * kind is trial (subject_id, or help_choose; for_child with child_name and child_birth_year; preferred_days as ISO weekdays with
     * preferred_from and preferred_to as hours on the family's clock; teacher_gender female or male) or teach (subject_ids, years_teaching).
     * The academy answers each request; whoever asked for a trial hears back at once by WhatsApp and email.
     * A form sent too soon or with the hidden field filled is answered the same and kept nowhere.
     * Not found while the academy keeps its page off.
     */
    public function store(Request $request, SubmitJoinRequest $submit): JsonResponse
    {
        abort_unless(SiteSettings::isOn(), 404);
        $submit->handle($request->only([
            'kind', 'name', 'phone', 'email', 'country', 'timezone', 'subject_id', 'help_choose', 'for_child', 'child_name', 'child_birth_year',
            'preferred_days', 'preferred_from', 'preferred_to', 'teacher_gender',
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
