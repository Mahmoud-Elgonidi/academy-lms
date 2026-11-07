<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Quran\Filament\Concerns\PlanActions;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Programs\JuzStrip;
use Modules\Quran\Queries\Board\QuranCourses;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Quran › Students (docs/architecture/02, section 26): every course of a
 * Quran subject that has not ended, its student on the strip of the
 * mushaf's parts (what they have memorized, where their plan is), their
 * pace and whether they keep it, a frozen plan, the next exam. The office
 * starts a plan (with where the learner stands) and adjusts it from a panel
 * at the side; a row opens the student's hifz map.
 */
final class QuranStudents extends Page
{
    use PlanActions;
    use QuranMenu;

    protected string $view = 'quran::filament.admin.students';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 401;

    protected static ?string $slug = 'quran/students';

    #[Url]
    public string $search = '';

    #[Url]
    public string $teacher = '';

    #[Url]
    public string $pace = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewQuranBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.students.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.students.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.students.subtitle');
    }

    /** @return array{rows: list<array<string, mixed>>, teachers: array<string, string>, parts: int, manage: bool} */
    #[Computed]
    public function board(): array
    {
        $query = app(QuranCourses::class);
        $rows = $query->rows(CarbonImmutable::now(), app(ViewerTimezone::class)->effective(), ['search' => $this->search, 'teacher' => $this->teacher, 'pace' => $this->pace]);

        return [
            'rows' => array_map(fn (array $r) => $r + ['url' => HifzOverview::canAccess() ? HifzStudent::getUrl(['enrollment' => $r['enrollment']]) : null], $rows),
            'teachers' => $query->teachers(),
            'parts' => app(JuzStrip::class)->count(),
            'manage' => Gate::allows(Ability::ManageQuran->value),
        ];
    }

    public function clearFilters(): void
    {
        $this->search = $this->teacher = $this->pace = '';
    }

    protected function refreshPlans(): void
    {
        unset($this->board);
    }

    public function startPlanAction(): Action
    {
        return $this->startPlan();
    }

    public function adjustPlanAction(): Action
    {
        return $this->adjustPlan(office: true);
    }
}
