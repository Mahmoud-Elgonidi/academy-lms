<?php

namespace Modules\Scheduling\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\DisputeAction;
use Modules\Scheduling\Filament\Family\FamilyLessonView;
use Modules\Scheduling\Queries\LessonList;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The teacher's lessons week by week, on their clock, from today; a lesson just past can be disputed. */
final class MyWeek extends Page
{
    protected string $view = 'scheduling::filament.teacher.week';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'week';

    /** 0 = this week (from today), -1 = the week before, 1 = the next one */
    public int $offset = 0;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::teacher.week.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::teacher.week.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::family.subtitle', ['zone' => Timezones::city(app(ViewerTimezone::class)->effective())]);
    }

    public function move(int $by): void
    {
        $this->offset = max(-4, min(8, $this->offset + $by));
    }

    /** Disputing what one of my lessons was recorded as, within 48 hours of its end (docs/architecture/01, section 8). */
    public function disputeAction(): Action
    {
        return DisputeAction::make('teacher', function (array $arguments): Session {
            $session = Session::query()->where('teacher_id', (string) auth()->id())->find($arguments['session'] ?? null);
            abort_if($session === null, 404);

            return $session;
        }, function () {
            unset($this->week);
        });
    }

    /** @return array{days: list<array{label: string, lessons: list<array<string, mixed>>}>, from: string, to: string, minutes: int, count: int} */
    #[Computed]
    public function week(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $now = CarbonImmutable::now();
        $from = $now->setTimezone($tz)->startOfDay()->addWeeks($this->offset);
        $view = new FamilyLessonView($now, $tz, counterpart: 'student');
        $rows = app(LessonList::class)->forTeacher((string) auth()->id(), $from->utc(), $from->addWeek()->utc());

        $days = [];
        foreach ($rows as $row) {
            $key = $row->starts_at->setTimezone($tz)->toDateString();
            $days[$key] ??= ['label' => $row->starts_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F'), 'lessons' => []];
            $days[$key]['lessons'][] = $view->lesson($row);
        }
        $taught = array_filter($rows, fn (object $r) => $r->status !== 'cancelled');

        return [
            'days' => array_values($days),
            'from' => $from->locale(app()->getLocale())->translatedFormat('j F'),
            'to' => $from->addDays(6)->locale(app()->getLocale())->translatedFormat('j F'),
            'minutes' => (int) array_sum(array_map(fn (object $r) => $r->starts_at->diffInMinutes($r->ends_at), $taught)),
            'count' => count($taught),
        ];
    }
}
