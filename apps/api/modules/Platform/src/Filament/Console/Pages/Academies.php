<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Console\AcademyUsage;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Time\ViewerTimezone;

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
        return __('platform::console.academies.subtitle');
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
        $usage = app(AcademyUsage::class)->forAcademies($academies->pluck('id')->all());
        $tz = app(ViewerTimezone::class)->effective();

        return $academies->map(function (Academy $academy) use ($usage, $tz) {
            $u = $usage[$academy->id];

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
            ];
        })->all();
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
