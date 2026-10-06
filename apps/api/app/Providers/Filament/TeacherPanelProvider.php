<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetPanelLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Identity\Filament\IdentityPlugin;
use Modules\Learning\Filament\Teacher\LearningTeacherPlugin;
use Modules\Platform\Filament\PlatformPlugin;

/**
 * The teacher area at {slug}.{platform domain}/teach: reports and homework,
 * in the academy's look. An interim web home for teachers until the mobile
 * app covers the same API; tenancy works exactly as in the back office.
 */
final class TeacherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teach')
            ->path('teach')
            ->login()
            ->spa()
            ->maxContentWidth('full')
            ->topNavigation()
            ->middleware([
                ResolveTenant::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetPanelLocale::class,
            ], isPersistent: true)
            ->authMiddleware([Authenticate::class], isPersistent: true)
            ->plugins([
                LearningTeacherPlugin::make(),
                PlatformPlugin::make(),
                IdentityPlugin::make(),
            ]);
    }
}
