<?php

namespace App\Providers\Filament;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Identity\Filament\Console\IdentityConsolePlugin;
use Modules\Identity\Filament\InitialsAvatar;
use Modules\Identity\TwoFactor\AuthenticatorApp;
use Modules\Platform\Filament\Console\ConsolePlugin;
use Modules\Platform\Http\Middleware\ConsoleContext;
use Modules\Platform\Http\Middleware\ConsoleEcho;
use Modules\Platform\Http\Middleware\ConsoleHost;
use Modules\Platform\Http\Middleware\EndLockedConsoleSession;

/**
 * The platform team's console at /console (or its own host, PLATFORM_CONSOLE_DOMAIN).
 *
 * No academy is resolved here: the console works across academies on the
 * platform connection, and reaches an academy's people only by signing in as
 * one of them. Its own guard and table (platform_admins), and an
 * authenticator app is required (docs/architecture/01: "2FA إجباري للـ Super Admin").
 */
final class ConsolePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('console')
            ->path('console')
            ->authGuard('platform')
            ->login()
            ->multiFactorAuthentication([AuthenticatorApp::make()->recoverable()], isRequired: true)
            ->brandName(fn (): string => __('platform::console.brand'))
            ->colors(['primary' => Color::Indigo, 'gray' => Color::Slate])
            ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatar::class)
            // Live screens (the games studio follows its drafts): one socket per page, authorised for the platform team.
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::live'))
            // The studio's preview opens games through the shared player (sandboxed frames).
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::games.player'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('shared::motion.styles'))
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::motion.script'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('shared::chrome.styles'))
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::chrome.script'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('shared::chrome.greeting'))
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->middleware([
                // On its own host only, before a session is started (PLATFORM_CONSOLE_DOMAIN).
                ConsoleHost::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                EndLockedConsoleSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                ConsoleContext::class,
                ConsoleEcho::class,
            ], isPersistent: true)
            ->authMiddleware([Authenticate::class], isPersistent: true)
            ->plugins([
                ConsolePlugin::make(),
                IdentityConsolePlugin::make(),
            ]);

        $domain = config('platform.console_domain');

        return is_string($domain) && $domain !== '' ? $panel->domain($domain) : $panel;
    }
}
