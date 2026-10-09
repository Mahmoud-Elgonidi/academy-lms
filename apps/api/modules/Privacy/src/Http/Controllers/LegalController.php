<?php

namespace Modules\Privacy\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Privacy\Models\PrivacySettings;
use Modules\SharedKernel\Http\SetLocale;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * The public privacy notice, terms and account-deletion page, in Arabic and
 * English (?lang=, else the browser's, else the academy's). On an academy's
 * host (or the platform's host standing for its home academy) they are the
 * academy's: it is the controller, named with its legal name, its address
 * for data requests and its own words added; anywhere else the platform's,
 * which the app stores link to. No sign-in, nothing personal.
 */
final class LegalController
{
    /** The date of the current wording: raise it when the meaning changes. */
    public const VERSION = '2026-10-09';

    public const PAGES = ['privacy' => 'privacy.notice', 'terms' => 'privacy.terms', 'deletion' => 'privacy.deletion'];

    public function __construct(
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
        private readonly BrandingApi $branding,
    ) {}

    public function privacy(Request $request): Response
    {
        return $this->show($request, 'privacy');
    }

    public function terms(Request $request): Response
    {
        return $this->show($request, 'terms');
    }

    public function deletion(Request $request): Response
    {
        return $this->show($request, 'deletion');
    }

    private function show(Request $request, string $page): Response
    {
        $academy = $this->academy($request);
        $locale = $this->locale($request, $academy);
        app()->setLocale($locale);

        $about = $academy === null ? $this->platform() : $this->tenants->run($academy, fn () => $this->ofAcademy($page));

        return response()->view('privacy::legal', $about + [
            'page' => $page,
            'locale' => $locale,
            'version' => self::VERSION,
        ])->header('Content-Language', $locale)->header('Cache-Control', 'public, max-age=300');
    }

    /** @return array<string, mixed> */
    private function ofAcademy(string $page): array
    {
        $branding = $this->branding->current();
        $settings = PrivacySettings::current();
        $name = $branding->name();

        return [
            'side' => 'academy',
            'branding' => $branding,
            'names' => [
                'academy' => $name,
                'controller' => $settings->legal_name ?? $name,
                'platform' => (string) config('privacy.platform_name'),
                'contact' => $settings->contact_email ?? __('privacy::legal.contact_in_app'),
            ],
            'contactEmail' => $settings->contact_email,
            'address' => $settings->address,
            'extra' => match ($page) {
                'privacy' => $settings->privacy_extra,
                'terms' => $settings->terms_extra,
                default => null,
            },
        ];
    }

    /** @return array<string, mixed> */
    private function platform(): array
    {
        $name = (string) config('privacy.platform_name');
        $email = config('privacy.contact_email');

        return [
            'side' => 'platform',
            'branding' => null,
            'names' => [
                'academy' => $name,
                'controller' => $name,
                'platform' => $name,
                'contact' => is_string($email) && $email !== '' ? $email : __('privacy::legal.contact_in_app'),
            ],
            'contactEmail' => is_string($email) && $email !== '' ? $email : null,
            'address' => null,
            'extra' => null,
        ];
    }

    /** As ResolveTenant decides, without the development academy and the X-Academy header: a plain address names its academy, or none. */
    private function academy(Request $request): ?TenantRef
    {
        $tenant = $this->directory->byHost($request->getHost());
        if ($tenant === null && strtolower($request->getHost()) === strtolower((string) config('platform.domain'))) {
            $home = config('platform.home_academy');
            $tenant = is_string($home) && $home !== '' ? $this->directory->bySlug($home) : null;
        }

        return $tenant?->active ? $tenant : null;
    }

    private function locale(Request $request, ?TenantRef $academy): string
    {
        $asked = $request->query('lang');
        if (is_string($asked) && in_array($asked, SetLocale::SUPPORTED, true)) {
            return $asked;
        }
        if ($request->headers->has('Accept-Language')) {
            return $request->getPreferredLanguage(SetLocale::SUPPORTED) ?? 'ar';
        }

        return in_array($academy?->defaultLocale, SetLocale::SUPPORTED, true) ? (string) $academy->defaultLocale : 'ar';
    }
}
