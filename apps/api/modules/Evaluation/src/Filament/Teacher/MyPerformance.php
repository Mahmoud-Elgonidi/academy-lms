<?php

namespace Modules\Evaluation\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Evaluation\Filament\KpiView;
use Modules\Evaluation\Filament\Pages\TeacherPerformance;
use Modules\Evaluation\Queries\PerformanceEvents;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The teacher's own KPIs against the academy's targets, what moved them
 * lately, and what families said, without their names
 * (docs/architecture/01, permissions: "مؤشراته").
 */
final class MyPerformance extends Page
{
    protected string $view = 'evaluation::filament.teacher.performance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'performance';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewOwnPerformance->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('evaluation::kpis.mine.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('evaluation::kpis.mine.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('evaluation::kpis.mine.subtitle');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function cards(): array
    {
        $me = (string) auth()->id();

        return KpiView::cards(app(TeacherKpis::class)->forTeachers([$me])[$me] ?? []);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function events(): array
    {
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (array $e) => KpiView::event($e, $tz, anonymousRatings: true), app(PerformanceEvents::class)->forTeacher((string) auth()->id()));
    }

    /** @return array{avg: ?string, count: int, list: list<array<string, mixed>>} */
    #[Computed]
    public function ratings(): array
    {
        return TeacherPerformance::ratingsOf((string) auth()->id(), names: false);
    }
}
