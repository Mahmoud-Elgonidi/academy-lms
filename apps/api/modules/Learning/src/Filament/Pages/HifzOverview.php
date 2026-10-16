<?php

namespace Modules\Learning\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Learning\Queries\Hifz\HifzBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * The academy's hifz for its supervisors (docs/architecture/02, section 22):
 * every course a hifz map follows, its student's map in numbers, the most in
 * need first; a row opens the student's map and mistakes notebook. Read when
 * opened; the families' and teachers' screens are where it changes.
 */
final class HifzOverview extends Page
{
    protected string $view = 'learning::filament.hifz.board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 42;

    protected static ?string $slug = 'hifz';

    #[Url]
    public string $search = '';

    #[Url]
    public string $teacher = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewReports->value);
    }

    /** In the menu once the platform has the Quran's text. */
    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess() && app(HifzBoard::class)->available();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('learning::reports.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::hifz.board.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::hifz.board.title');
    }

    /** @return array{available: bool, rows: list<array<string, mixed>>, teachers: array<string, string>} */
    #[Computed]
    public function board(): array
    {
        $query = app(HifzBoard::class);
        $now = CarbonImmutable::now();
        $tz = app(ViewerTimezone::class)->effective();
        $all = $query->rows($now, $this->search);
        $teachers = collect($all)->pluck('teacher', 'teacher_id')->sort()->all();
        $rows = $this->teacher === '' ? $all : array_values(array_filter($all, fn (array $r) => $r['teacher_id'] === $this->teacher));

        return [
            'available' => $query->available(),
            'teachers' => $teachers,
            'rows' => array_map(fn (array $r) => $r + [
                'url' => HifzStudent::getUrl(['enrollment' => $r['enrollment']]),
                'last' => $r['last_review']?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M'),
            ], $rows),
        ];
    }
}
