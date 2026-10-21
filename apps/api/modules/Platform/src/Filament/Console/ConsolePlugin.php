<?php

namespace Modules\Platform\Filament\Console;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Modules\Platform\Filament\Console\Pages\Academies;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\AuditLog;
use Modules\Platform\Filament\Console\Pages\GameDraftPage;
use Modules\Platform\Filament\Console\Pages\GameEditor;
use Modules\Platform\Filament\Console\Pages\GameStudio;
use Modules\Platform\Filament\Console\Pages\Health;
use Modules\Platform\Filament\Console\Pages\Plans;
use Modules\Platform\Filament\Console\Pages\PlatformSettingsPage;
use Modules\Platform\Filament\Console\Pages\SignupRequests;
use Modules\Platform\Filament\Console\Pages\SitePage;
use Modules\Platform\Http\Controllers\GameDraftPageController;

/** The platform team's console: the academies, their use, plans and status, the plans on sale, the games studio, the audit log across them, the stack's health, and (owners) the platform's settings. */
final class ConsolePlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'platform-console';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Academies::class, AcademyOverview::class, Plans::class, GameStudio::class, GameDraftPage::class, GameEditor::class, AuditLog::class, Health::class, PlatformSettingsPage::class, SitePage::class, SignupRequests::class]);
        // A draft game's page for the studio's preview (sandboxed, never cached).
        $panel->authenticatedRoutes(fn () => Route::get('games/drafts/{draft}/page', GameDraftPageController::class)->name('games.draft-page'));
    }

    public function boot(Panel $panel): void {}
}
