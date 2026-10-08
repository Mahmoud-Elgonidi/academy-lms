<?php

namespace Modules\Platform\Filament;

use Filament\Contracts\Plugin;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\DTOs\Branding;
use Modules\Platform\Filament\Pages\AcademyPlanPage;
use Modules\Platform\Filament\Pages\BrandingSettings;
use Modules\Platform\Plans\PlanWarning;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Dresses the academy back office in the academy's own look: name, logo,
 * favicon, colours, fonts and corners, all read per request from BrandingApi.
 */
final class PlatformPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'platform';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([BrandingSettings::class, AcademyPlanPage::class])
            ->brandName(fn (): string => self::branding()?->name() ?? config('app.name'))
            ->brandLogo(fn () => self::branding()?->logoUrl)
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => self::branding()?->faviconUrl)
            ->font(
                fn (): string => self::branding()?->tokens['body_font'] ?? 'IBM Plex Sans Arabic',
                url: fn (): ?string => self::branding()?->fontsUrl,
                provider: GoogleFontProvider::class,
            )
            // Filament registers panel colours before ResolveTenant runs, so the
            // academy's palette is registered again once the academy is known.
            ->middleware([ApplyAcademyColours::class], isPersistent: true)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => self::headStyles());

        // The back office tells its owner and admins when a limit of the plan is close to full, except on the plan page itself.
        if ($panel->getId() === 'academy') {
            $panel->renderHook(PanelsRenderHook::PAGE_START, function (array $scopes = []): string {
                $warning = in_array(AcademyPlanPage::class, $scopes, true) ? null : app(PlanWarning::class)->forViewer();

                return $warning === null ? '' : view('platform::plans.banner', $warning + ['url' => AcademyPlanPage::getUrl(panel: 'academy')])->render();
            });
        }
    }

    public function boot(Panel $panel): void {}

    /** @return array<string, array<int|string, string|int>> Filament palettes from the academy's tokens. */
    public static function colours(): array
    {
        $tokens = self::branding()?->tokens;
        if ($tokens === null) {
            return [];
        }

        return [
            'primary' => BrandPalette::from($tokens['primary']),
            'warning' => BrandPalette::from($tokens['attention']),
            'danger' => BrandPalette::from($tokens['danger']),
            'info' => BrandPalette::from($tokens['info']),
            'success' => BrandPalette::from('#15803D'),
            'gray' => match ($tokens['neutral']) {
                'slate' => Color::Slate,
                'gray' => Color::Gray,
                'zinc' => Color::Zinc,
                'neutral' => Color::Neutral,
                default => Color::Stone,
            },
        ];
    }

    private static function branding(): ?Branding
    {
        return app(TenantContext::class)->check() ? app(BrandingApi::class)->current() : null;
    }

    /** Heading font and corner radii as CSS variables; screens built on them follow the academy's look. */
    private static function headStyles(): HtmlString
    {
        $branding = self::branding();
        if ($branding === null) {
            return new HtmlString('');
        }

        $t = $branding->tokens;
        $r = $branding->radii;
        $heading = e($t['heading_font']);
        $body = e($t['body_font']);

        return new HtmlString(<<<HTML
            <style>
                :root {
                    --lms-font-heading: '{$heading}', '{$body}', system-ui, sans-serif;
                    --lms-primary: {$t['primary']};
                    --lms-attention: {$t['attention']};
                    --lms-danger: {$t['danger']};
                    --lms-info: {$t['info']};
                    --radius-sm: {$r['sm']};
                    --radius-md: {$r['md']};
                    --radius-lg: {$r['lg']};
                    --radius-xl: {$r['xl']};
                }
                .fi-header-heading, .fi-section-header-heading, .fi-modal-heading, .fi-logo, .fi-simple-header-heading,
                .lms-heading { font-family: var(--lms-font-heading); }
            </style>
            HTML);
    }
}
