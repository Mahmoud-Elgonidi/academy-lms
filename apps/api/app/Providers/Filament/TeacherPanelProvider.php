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
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Classroom\Filament\Teacher\ClassroomTeacherPlugin;
use Modules\Identity\Filament\IdentityPlugin;
use Modules\Learning\Filament\Teacher\LearningTeacherPlugin;
use Modules\Platform\Filament\PlatformPlugin;
use Modules\Scheduling\Filament\Teacher\SchedulingTeacherPlugin;
use Modules\SharedKernel\Realtime\EnableBrowserEcho;

/**
 * The teacher area at {slug}.{platform domain}/teach: today's lessons and the
 * week, their students and reports, homework to mark, and their hours, in
 * the academy's look. The same Actions as the teacher API for the mobile
 * app; tenancy works exactly as in the back office.
 */
final class TeacherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teach')
            // Live screens: one socket per page (Reverb, EnableBrowserEcho below) for the bell and any live part, and the helper they share.
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::live'))
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
                EnableBrowserEcho::class,
            ], isPersistent: true)
            ->authMiddleware([Authenticate::class], isPersistent: true)
            ->plugins([
                ClassroomTeacherPlugin::make(),
                SchedulingTeacherPlugin::make(),
                LearningTeacherPlugin::make(),
                PlatformPlugin::make(),
                IdentityPlugin::make(),
            ]);
    }
}
