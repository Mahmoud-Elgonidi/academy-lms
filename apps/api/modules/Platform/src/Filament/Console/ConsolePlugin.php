<?php

namespace Modules\Platform\Filament\Console;

use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Modules\Platform\Actions\SavePlatformAdminTimezone;
use Modules\Platform\Filament\Console\Pages\Academies;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\AiEnginesPage;
use Modules\Platform\Filament\Console\Pages\AiRoutesPage;
use Modules\Platform\Filament\Console\Pages\AuditLog;
use Modules\Platform\Filament\Console\Pages\GameDraftPage;
use Modules\Platform\Filament\Console\Pages\GameEditor;
use Modules\Platform\Filament\Console\Pages\GameStudio;
use Modules\Platform\Filament\Console\Pages\Health;
use Modules\Platform\Filament\Console\Pages\PlanRequests;
use Modules\Platform\Filament\Console\Pages\Plans;
use Modules\Platform\Filament\Console\Pages\PlatformSettingsPage;
use Modules\Platform\Filament\Console\Pages\QuranConsole;
use Modules\Platform\Filament\Console\Pages\SignupRequests;
use Modules\Platform\Filament\Console\Pages\SitePage;
use Modules\Platform\Http\Controllers\GameDraftPageController;
use Modules\SharedKernel\Filament\ZoneChip;

/** The platform team's console: the academies, their use, plans and status, the plans on sale and what academies asked about theirs, the games studio, the audit log across them, the stack's health, the Quran's text and who has it, and (owners) the platform's settings, its AI engines and where each capability's requests go. */
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
        $panel->pages([Academies::class, AcademyOverview::class, Plans::class, PlanRequests::class, GameStudio::class, GameDraftPage::class, GameEditor::class, AuditLog::class, Health::class, PlatformSettingsPage::class, AiEnginesPage::class, AiRoutesPage::class, SitePage::class, SignupRequests::class, QuranConsole::class]);
        // A draft game's page for the studio's preview (sandboxed, never cached).
        $panel->authenticatedRoutes(fn () => Route::get('games/drafts/{draft}/page', GameDraftPageController::class)->name('games.draft-page'));
        // The console has no settings page: a zone chip hidden from its card comes back from the account menu.
        $panel->userMenuItems(['zone-chip' => self::showZoneChip()]);
    }

    private static function showZoneChip(): Action
    {
        $saver = fn (): SavePlatformAdminTimezone => app(SavePlatformAdminTimezone::class);

        return Action::make('showZoneChip')
            ->label(fn (): string => __('shared::chrome.zone.show'))
            ->icon(Heroicon::OutlinedClock)
            ->visible(fn (): bool => ($admin = Filament::auth()->user()) !== null && ! $saver()->chipShown($admin))
            ->action(function (Component $livewire) use ($saver): void {
                $admin = Filament::auth()->user();
                if ($admin === null) {
                    return;
                }
                $saver()->setChipShown($admin, true);
                Notification::make()->title(__('shared::chrome.zone.shown_again'))->success()->send();
                $livewire->dispatch(ZoneChip::RECONSIDER);
            });
    }

    public function boot(Panel $panel): void {}
}
