<?php

namespace Modules\Platform\Filament\Console\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\SetAcademyStatus;
use Modules\Platform\Console\AcademyPeople;
use Modules\Platform\Console\AcademyUsage;
use Modules\Platform\Console\AuditFeed;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One academy for the platform team: its use, who runs it, its latest
 * sensitive actions, and switching it off or back on. Its people's data is
 * reached only by signing in as one of them (Identity's support sign-in).
 */
final class AcademyOverview extends Page
{
    protected string $view = 'platform::console.overview';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'academies/{academy}';

    public string $academy;

    public function mount(string $academy): void
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $academy) && Academy::query()->whereKey($academy)->exists(), 404);
        $this->academy = $academy;
    }

    #[Computed]
    public function record(): Academy
    {
        return Academy::query()->with('domains')->findOrFail($this->academy);
    }

    public function getTitle(): string|Htmlable
    {
        return ConsoleView::name($this->record);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::console.overview.subtitle', [
            'host' => $this->host(),
            'date' => ConsoleView::date($this->record->created_at, app(ViewerTimezone::class)->effective()),
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [Academies::getUrl() => __('platform::console.overview.back')];
    }

    protected function getHeaderActions(): array
    {
        $record = $this->record;
        $name = ConsoleView::name($record);
        $active = $record->status === AcademyStatus::Active;

        $actions = [];
        // Signing in as someone is Identity's (its console plugin adds the page).
        if ($active && Route::has('filament.console.pages.support-sign-in')) {
            $actions[] = Action::make('support')->label(__('platform::console.overview.support'))->icon(Heroicon::OutlinedLifebuoy)
                ->url(route('filament.console.pages.support-sign-in', ['academy' => $record->id]));
        }
        if ($active) {
            $actions[] = Action::make('open')->label(__('platform::console.overview.open_site'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(app(TenantDirectory::class)->baseUrl($record->toTenantRef()).'/admin', shouldOpenInNewTab: true);
        }
        $actions[] = $active
            ? Action::make('suspend')->label(__('platform::console.overview.suspend.label'))->icon(Heroicon::OutlinedPauseCircle)->color('danger')
                ->modalHeading(__('platform::console.overview.suspend.heading', ['name' => $name]))
                ->modalDescription(__('platform::console.overview.suspend.body'))
                ->modalSubmitActionLabel(__('platform::console.overview.suspend.confirm'))
                ->schema([
                    Textarea::make('reason')->label(__('platform::console.overview.suspend.reason'))
                        ->helperText(__('platform::console.overview.suspend.reason_hint'))->required()->minLength(5)->maxLength(500)->rows(3),
                ])
                ->action(function (array $data) {
                    app(SetAcademyStatus::class)->suspend($this->academy, (string) $data['reason']);
                    Notification::make()->title(__('platform::console.overview.suspend.done'))->success()->send();
                    $this->reloadPage();
                })
            : Action::make('reactivate')->label(__('platform::console.overview.reactivate.label'))->icon(Heroicon::OutlinedPlayCircle)->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('platform::console.overview.reactivate.heading', ['name' => $name]))
                ->modalDescription(__('platform::console.overview.reactivate.body'))
                ->modalSubmitActionLabel(__('platform::console.overview.reactivate.confirm'))
                ->action(function () {
                    app(SetAcademyStatus::class)->reactivate($this->academy);
                    Notification::make()->title(__('platform::console.overview.reactivate.done'))->success()->send();
                    $this->reloadPage();
                });

        return $actions;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function usage(): array
    {
        $u = app(AcademyUsage::class)->forAcademies([$this->academy])[$this->academy];

        return $u + ['hours' => ConsoleView::hours($u['minutes']), 'money' => ConsoleView::money($u['received']), 'last' => ConsoleView::ago($u['last_lesson'])];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function managers(): array
    {
        return app(AcademyPeople::class)->managers($this->academy);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function activity(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $feed = app(AuditFeed::class)->entries($this->academy, limit: 12);

        return array_map(fn (object $row) => AuditEntries::describe($row, $tz, $feed['names']) + ['id' => $row->id], $feed['rows']);
    }

    /** @return list<array{host: string, verified: bool}> */
    #[Computed]
    public function hosts(): array
    {
        return $this->record->domains->sortBy(fn ($d) => $d->kind === DomainKind::Subdomain ? 0 : 1)
            ->map(fn ($d) => ['host' => $d->host, 'verified' => $d->verified_at !== null])->values()->all();
    }

    private function host(): string
    {
        return $this->hosts[0]['host'] ?? $this->record->slug;
    }

    /** The header's actions flip with the status, so the page loads again. */
    private function reloadPage(): void
    {
        $this->redirect(self::getUrl(['academy' => $this->academy]), navigate: true);
    }
}
