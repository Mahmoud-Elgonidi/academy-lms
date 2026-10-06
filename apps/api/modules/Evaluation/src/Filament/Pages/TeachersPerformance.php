<?php

namespace Modules\Evaluation\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Evaluation\Domain\Kpi;
use Modules\Evaluation\Filament\KpiView;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Every teacher's KPIs side by side (docs/architecture/01, section 12:
 * punctuality, absence and reports on time, and the rest we can measure),
 * those furthest from their targets first. A row opens the teacher.
 */
final class TeachersPerformance extends Page
{
    protected string $view = 'evaluation::filament.teachers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 42;

    protected static ?string $slug = 'teachers/performance';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewPerformance->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('evaluation::kpis.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('evaluation::kpis.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('evaluation::kpis.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('evaluation::kpis.subtitle');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $teachers = DB::table('users as u')
            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', 'teacher')->where('u.status', 'active')->orderBy('u.name')->get(['u.id', 'u.name']);
        $kpis = app(TeacherKpis::class)->forTeachers($teachers->pluck('id')->all());

        return $teachers->map(function ($t) use ($kpis) {
            $cards = KpiView::cards($kpis[$t->id] ?? []);
            $judged = array_filter($cards, fn (array $c) => $c['state'] !== 'none');

            return [
                'id' => $t->id,
                'name' => $t->name,
                'cards' => $cards,
                'good' => count(array_filter($judged, fn (array $c) => $c['state'] === 'good')),
                'judged' => count($judged),
                'bad' => count(array_filter($judged, fn (array $c) => $c['state'] === 'bad')),
                'url' => TeacherPerformance::getUrl(['teacher' => $t->id]),
            ];
        })->sortBy([['bad', 'desc'], ['name', 'asc']])->values()->all();
    }

    /** @return list<string> the columns, by KPI */
    public function columns(): array
    {
        return array_map(fn (Kpi $k) => $k->key, Kpi::all());
    }
}
