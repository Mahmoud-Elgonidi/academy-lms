<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Console\AcademyUsage;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * Every academy on the platform (docs/architecture/01, section 1: "لوحة
 * Super Admin: الأكاديميات، والاستخدام"): who is in it, its lessons and
 * money in the last 30 days, and whether it is on. A card opens the academy.
 */
final class Academies extends Page
{
    protected string $view = 'platform::console.academies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'academies';

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = 'all';

    public static function getNavigationLabel(): string
    {
        return __('platform::console.nav.academies');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::console.academies.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::console.academies.subtitle').' '.ZoneHint::yours();
    }

    protected function getHeaderActions(): array
    {
        // Making an academy with its owner is Identity's (its console plugin adds the page).
        return Route::has('filament.console.pages.new-academy') ? [
            Action::make('new')->label(__('platform::console.academies.new'))->icon(Heroicon::Plus)->url(route('filament.console.pages.new-academy')),
        ] : [];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function academies(): array
    {
        $query = Academy::query()->with('domains')->orderBy('name_'.(app()->getLocale() === 'ar' ? 'ar' : 'en'));
        if (in_array($this->status, ['active', 'suspended'], true)) {
            $query->where('status', $this->status);
        }
        $term = trim($this->q);
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q->where('name_ar', 'ilike', $like)->orWhere('name_en', 'ilike', $like)->orWhere('slug', 'ilike', $like));
        }
        $academies = $query->get();
        $ids = $academies->pluck('id')->all();
        $usage = app(AcademyUsage::class)->forAcademies($ids);
        $plans = $this->plans($ids);
        $tz = app(ViewerTimezone::class)->effective();

        return $academies->map(function (Academy $academy) use ($usage, $plans, $tz) {
            $u = $usage[$academy->id];
            $plan = $plans[$academy->id] ?? null;

            return [
                'id' => $academy->id,
                'name' => ConsoleView::name($academy),
                'host' => $academy->domains->first(fn ($d) => $d->kind === DomainKind::Subdomain)?->host ?? $academy->slug,
                'active' => $academy->status === AcademyStatus::Active,
                'since' => ConsoleView::date($academy->created_at, $tz),
                'country' => $academy->country,
                'usage' => $u,
                'hours' => ConsoleView::hours($u['minutes']),
                'received' => ConsoleView::money($u['received']),
                'last' => ConsoleView::ago($u['last_lesson']),
                'url' => AcademyOverview::getUrl(['academy' => $academy->id]),
                'plan' => $plan['name'] ?? null,
                'plan_state' => $plan['state'] ?? 'ok',
            ];
        })->all();
    }

    /**
     * Each academy's plan and how close its fullest limit was at the last count.
     *
     * @param  list<string>  $ids
     * @return array<string, array{name: string, state: string}>
     */
    private function plans(array $ids): array
    {
        $db = DB::connection('pgsql_platform');
        $names = $db->table('saas_subscriptions as s')->join('saas_plans as p', 'p.id', '=', 's.plan_id')
            ->whereNull('s.ended_at')->whereIn('s.academy_id', $ids)->get(['s.academy_id', 'p.name_ar', 'p.name_en']);
        $fullest = $db->table('usage_counters as u')->whereIn('u.academy_id', $ids)->where('u.max', '>', 0)
            ->whereRaw('u.period = (select max(period) from usage_counters l where l.academy_id = u.academy_id)')
            ->groupBy('u.academy_id')->selectRaw('u.academy_id, max(u.value::float / u.max) as ratio')->pluck('ratio', 'academy_id');
        $out = [];
        foreach ($names as $row) {
            $ratio = (float) ($fullest[$row->academy_id] ?? 0);
            $out[$row->academy_id] = [
                'name' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                'state' => $ratio >= 1 ? 'full' : ($ratio >= 0.8 ? 'near' : 'ok'),
            ];
        }

        return $out;
    }

    /** @return array{active: int, suspended: int, students: int, lessons: int, hours: string} across every academy, whatever the filter */
    #[Computed]
    public function tiles(): array
    {
        $all = Academy::query()->get(['id', 'status']);
        $usage = app(AcademyUsage::class)->forAcademies($all->pluck('id')->all());

        return [
            'active' => $all->where('status', AcademyStatus::Active)->count(),
            'suspended' => $all->where('status', AcademyStatus::Suspended)->count(),
            'students' => array_sum(array_column($usage, 'students')),
            'lessons' => array_sum(array_column($usage, 'lessons')),
            'hours' => ConsoleView::hours(array_sum(array_column($usage, 'minutes'))),
        ];
    }

    public function filter(string $status): void
    {
        $this->status = in_array($status, ['active', 'suspended'], true) ? $status : 'all';
    }
}
