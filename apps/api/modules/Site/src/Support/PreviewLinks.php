<?php

namespace Modules\Site\Support;

use Carbon\CarbonImmutable;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Http\LinkToken;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The builder's preview (site-builder.md, 3.2): a token (LinkToken, purpose
 * "site-preview", the academy as its record) good for two hours, that lets
 * the portal's /{locale}/site-preview read the draft through
 * GET v1/site/preview. The portal's server calls the API without the
 * admin's session, so the token is the proof; it only shows a draft of a
 * page that is meant to be public anyway. The builder renews it while open.
 */
final class PreviewLinks
{
    public const PURPOSE = 'site-preview';

    public const HOURS = 2;

    public static function issue(): string
    {
        return LinkToken::issue(self::PURPOSE, (string) app(TenantContext::class)->id(), CarbonImmutable::now()->addHours(self::HOURS));
    }

    /** Whether the token is this academy's preview, still good. */
    public static function valid(?string $token): bool
    {
        $id = LinkToken::read(self::PURPOSE, $token);

        return $id !== null && $id === (string) app(TenantContext::class)->id();
    }

    public static function expired(?string $token): bool
    {
        return LinkToken::expired(self::PURPOSE, $token);
    }

    /** Where the preview opens: SITE_PREVIEW_URL on a laptop, else the academy's own host. */
    public static function base(): string
    {
        $base = (string) config('site.preview_url');

        return rtrim($base !== '' ? $base : app(TenantDirectory::class)->baseUrl(app(TenantContext::class)->require()), '/');
    }

    /** The preview page for a draft revision, in a language, optionally at a block. */
    public static function url(string $token, int $revision, string $locale = 'ar', ?string $anchor = null): string
    {
        return self::base().'/'.($locale === 'en' ? 'en' : 'ar').'/site-preview?t='.rawurlencode($token).'&r='.$revision.($anchor !== null ? '#'.$anchor : '');
    }
}
