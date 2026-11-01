<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Queries\Board\QuranHome;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The Quran's home in the back office (docs/architecture/02, section 26),
 * first in its menu group: this month in numbers, who keeps their plan's
 * pace and who is behind, the exams waiting and booked, and the latest
 * certificates; each card leads to its screen. For who follows the Quran's
 * teachers (quran.board).
 */
final class QuranHomePage extends Page
{
    use QuranMenu;

    protected string $view = 'quran::filament.admin.home';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'quran';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewQuranBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.home.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.home.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.home.subtitle');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function home(): array
    {
        return app(QuranHome::class)->read(CarbonImmutable::now(), app(ViewerTimezone::class)->effective());
    }

    /** @return array<string, ?string> where each card leads, when the viewer may open it */
    public function links(): array
    {
        return [
            'students' => QuranStudents::canAccess() ? QuranStudents::getUrl() : null,
            'programs' => QuranPrograms::canAccess() ? QuranPrograms::getUrl() : null,
            'exams' => QuranExams::canAccess() ? QuranExams::getUrl() : null,
            'certificates' => QuranCertificates::canAccess() ? QuranCertificates::getUrl() : null,
            'hifz' => HifzOverview::canAccess() ? HifzOverview::getUrl() : null,
        ];
    }
}
