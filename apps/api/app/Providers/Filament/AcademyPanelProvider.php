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
use Modules\Billing\Filament\BillingPlugin;
use Modules\Classroom\Filament\ClassroomPlugin;
use Modules\Communication\Filament\CommunicationPlugin;
use Modules\Curriculum\Filament\CurriculumPlugin;
use Modules\Identity\Filament\IdentityPlugin;
use Modules\Learning\Filament\LearningPlugin;
use Modules\Platform\Filament\PlatformPlugin;
use Modules\Scheduling\Filament\SchedulingPlugin;
use Modules\SharedKernel\Realtime\EnableBrowserEcho;

/**
 * The academy back office at {slug}.{platform domain}/admin.
 *
 * Filament's own tenancy is not used: ResolveTenant picks the academy from the
 * host before the session user is loaded, exactly as for the API, and runs
 * again on every Livewire request (persistent middleware). Each module adds
 * its screens through a plugin.
 */
final class AcademyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('academy')
            // Live screens: one socket per page (Reverb, EnableBrowserEcho below) for the bell and any live part, and the helper they share.
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::live'))
            // Motion: pages slide in, cards follow, bars fill, numbers count up; none for reduced motion.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('shared::motion.styles'))
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn () => view('shared::motion.script'))
            ->path('admin')
            ->login()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
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
                SchedulingPlugin::make(),
                BillingPlugin::make(),
                CurriculumPlugin::make(),
                LearningPlugin::make(),
                CommunicationPlugin::make(),
                ClassroomPlugin::make(),
                PlatformPlugin::make(),
                IdentityPlugin::make(),
            ]);
    }
}
