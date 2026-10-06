<?php

namespace Modules\Learning\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\SessionReport;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The teacher's reports: due and overdue first, then the last two weeks handed in. */
final class MyReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'learning::filament.teacher.my-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'reports';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::teacher.nav_reports');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SessionReport::query()->where('teacher_id', auth()->id())->whereIn('status', SessionReport::OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return SessionReport::query()->where('teacher_id', auth()->id())->where('status', 'overdue')->exists() ? 'danger' : 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::teacher.reports.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('learning::teacher.reports.subtitle', ['hours' => LearningSettings::current()->report_due_hours]);
    }

    public function table(Table $table): Table
    {
        $tz = app(ViewerTimezone::class)->effective();
        $at = fn (?CarbonImmutable $t) => $t?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i');

        return $table
            ->query(SessionReport::query()
                ->join('users as s', fn ($j) => $j->on('s.academy_id', '=', 'session_reports.academy_id')->on('s.id', '=', 'session_reports.student_id'))
                ->where('session_reports.teacher_id', auth()->id())
                ->where(fn ($q) => $q->whereIn('session_reports.status', SessionReport::OPEN)->orWhere('session_reports.submitted_at', '>=', CarbonImmutable::now()->subDays(14)))
                ->select('session_reports.*', 's.name as student_name'))
            ->defaultSort(fn ($query) => $query->orderByRaw("case when session_reports.status in ('due', 'overdue') then 0 else 1 end")
                ->orderByRaw("case when session_reports.status in ('due', 'overdue') then session_reports.due_at end asc")
                ->orderByDesc('session_reports.session_starts_at'))
            ->recordUrl(fn (SessionReport $r) => WriteReport::getUrl(['report' => $r->id]))
            ->columns([
                TextColumn::make('student_name')->label(__('learning::teacher.reports.student'))->weight('semibold')
                    ->description(fn (SessionReport $r) => __('learning::reports.kinds.'.$r->kind)),
                TextColumn::make('session_starts_at')->label(__('learning::teacher.reports.lesson'))->state(fn (SessionReport $r) => $at($r->session_starts_at)),
                TextColumn::make('status')->badge()->label('')
                    ->formatStateUsing(fn (string $state) => __('learning::reports.statuses.'.$state))
                    ->color(fn (string $state) => ['due' => 'warning', 'overdue' => 'danger', 'submitted' => 'info', 'reviewed' => 'success'][$state]),
                TextColumn::make('due_at')->label(__('learning::teacher.reports.due'))
                    ->state(fn (SessionReport $r) => $r->isOpen() ? $r->due_at->locale(app()->getLocale())->diffForHumans() : $at($r->submitted_at))
                    ->color(fn (SessionReport $r) => $r->status === 'overdue' || $r->late ? 'danger' : null),
            ])
            ->recordActions([
                Action::make('write')
                    ->label(fn (SessionReport $r) => match ($r->status) {
                        'due', 'overdue' => __('learning::teacher.reports.write'),
                        'submitted' => __('learning::teacher.reports.edit'),
                        default => __('learning::teacher.reports.view'),
                    })
                    ->button()->size('sm')
                    ->color(fn (SessionReport $r) => $r->isOpen() ? 'primary' : 'gray')
                    ->url(fn (SessionReport $r) => WriteReport::getUrl(['report' => $r->id])),
            ])
            ->emptyStateHeading(__('learning::teacher.reports.empty'))
            ->paginated([25, 50]);
    }
}
