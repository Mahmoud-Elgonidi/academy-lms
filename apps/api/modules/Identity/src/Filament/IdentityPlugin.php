<?php

namespace Modules\Identity\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Modules\Identity\Filament\Pages\ActivityLog;
use Modules\Identity\Filament\Pages\MySettings;
use Modules\Identity\Filament\Pages\Team;
use Modules\Identity\Http\Middleware\WatchImpersonation;

/**
 * Identity's screens in the academy's panels: "My settings" everywhere, the
 * academy's activity log and its team in the back office, and, while the platform team
 * is signed in as someone, the bar that says so (with the time left and the
 * way out) and the watch that ends it on time.
 */
final class IdentityPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'identity';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->profile(MySettings::class, isSimple: false)
            ->defaultAvatarProvider(InitialsAvatar::class)
            ->middleware([WatchImpersonation::class], isPersistent: true)
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('identity::impersonation.banner'));

        if ($panel->getId() === 'academy') {
            $panel->pages([ActivityLog::class, Team::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
