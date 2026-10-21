<?php

namespace Modules\Platform\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Actions\Site\SubmitSignupRequest;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\Site\PlatformSite;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Site\SiteContent;
use Modules\SharedKernel\Http\FormToken;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * The platform's own page (public, no academy): what it says, the plans it
 * shows, and the form an academy asks to join with. An academy's address has
 * its own page instead (Site).
 */
final class SiteController
{
    /**
     * The platform's page.
     *
     * In the request's language (Accept-Language). Not on an academy's address (404).
     */
    public function show(Request $request, TenantDirectory $directory): JsonResponse
    {
        abort_if($directory->byHost($request->getHost()) !== null, 404);
        $locale = app()->getLocale();

        $plans = SaasPlan::query()->offered()->where('shown_on_site', true)->orderBy('sort')->orderBy('code')->get()
            ->map(fn (SaasPlan $plan) => [
                /** The plan's code, sent back with the request form. */
                'code' => $plan->code,
                'name' => $plan->name($locale),
                /** One line about who it is for, or null. */
                'tagline' => ($locale === 'ar' ? $plan->tagline_ar : $plan->tagline_en) ?: null,
                /** A month, written out ("SAR 299.00"), or null when the plan has no price shown. */
                'price' => PlanFormat::price($plan),
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                /** The one plan the page sets apart. */
                'highlight' => (bool) $plan->highlight,
                'limits' => array_map(fn (Limit $l) => ['key' => $l->value, 'label' => __('shared::plans.limits.'.$l->value), 'value' => PlanFormat::amount($l, $plan->limit($l)), 'unlimited' => $plan->limit($l) === null], Limit::cases()),
                'features' => array_map(fn (Feature $f) => ['key' => $f->value, 'label' => $f->label(), 'on' => in_array($f->value, $plan->features, true)], Feature::cases()),
            ])->values()->all();

        return response()->json([
            'data' => [
                /** The words the console set: hero, stats, testimonials, faqs, sections shown, contact, demo_url, seo. */
                'content' => SiteContent::localized(PlatformSite::content(), $locale),
                'plans' => $plans,
                'app' => [
                    /** The store page on Android, or null. */
                    'android_url' => config('platform.app.android_url'),
                    /** The App Store page, or null. */
                    'ios_url' => config('platform.app.ios_url'),
                ],
                /** The platform's legal name, for the footer. */
                'legal_name' => (string) config('privacy.platform_name'),
                /** Sent back with the request form, unchanged. */
                'form_token' => FormToken::issue(SubmitSignupRequest::FORM),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Ask to join the platform.
     *
     * The platform team answers each request. A form sent too soon or with the hidden field filled is answered the same and kept nowhere.
     */
    public function store(Request $request, TenantDirectory $directory, SubmitSignupRequest $submit): JsonResponse
    {
        abort_if($directory->byHost($request->getHost()) !== null, 404);
        $submit->handle($request->only([
            'academy_name', 'contact_name', 'email', 'phone', 'country', 'students', 'plan', 'message',
            'utm_source', 'utm_medium', 'utm_campaign', 'form_token', 'website',
        ]), app()->getLocale());

        return response()->json(['data' => ['received' => true]], 201);
    }
}
