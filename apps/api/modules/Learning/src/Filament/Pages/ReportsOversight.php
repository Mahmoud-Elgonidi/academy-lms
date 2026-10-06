<?php

namespace Modules\Learning\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Modules\Learning\Actions\ReviewSessionReport;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/** Report follow-up for supervisors: overdue, still in time, waiting for review, reviewed. */
final class ReportsOversight extends Page implements HasTable
{
    use InteractsWithTable;

    public const TABS = ['overdue', 'due', 'submitted', 'reviewed'];

    protected string $view = 'learning::filament.reports-oversight';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'reports';

    #[Url]
    public string $tab = 'overdue';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewReports->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('learning::reports.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::reports.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SessionReport::query()->where('status', 'overdue')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::reports.oversight.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('learning::reports.oversight.subtitle', ['hours' => LearningSettings::current()->report_due_hours]);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return SessionReport::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    public function updatedTab(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'overdue';
        $tz = app(ViewerTimezone::class)->effective();
        $at = fn (?CarbonImmutable $t) => $t?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i');

        return $table
            ->query(SessionReport::query()
                ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 'session_reports.academy_id')->on('t.id', '=', 'session_reports.teacher_id'))
                ->join('users as s', fn ($j) => $j->on('s.academy_id', '=', 'session_reports.academy_id')->on('s.id', '=', 'session_reports.student_id'))
                ->where('session_reports.status', $tab)
                ->select('session_reports.*', 't.name as teacher_name', 's.name as student_name'))
            ->defaultSort(fn ($query) => $query->orderBy('session_reports.'.($tab === 'reviewed' ? 'reviewed_at' : ($tab === 'submitted' ? 'submitted_at' : 'due_at')), $tab === 'reviewed' ? 'desc' : 'asc'))
            ->columns([
                TextColumn::make('teacher_name')->label(__('learning::reports.oversight.teacher'))->weight('semibold')->searchable('t.name'),
                TextColumn::make('student_name')->label(__('learning::reports.oversight.student'))->searchable('s.name')
                    ->description(fn (SessionReport $r) => __('learning::reports.kinds.'.$r->kind)),
                TextColumn::make('session_starts_at')->label(__('learning::reports.oversight.lesson'))->state(fn (SessionReport $r) => $at($r->session_starts_at)),
                TextColumn::make('due_at')->label(__('learning::reports.oversight.due'))
                    ->state(fn (SessionReport $r) => match (true) {
                        $r->status === 'overdue' => __('learning::reports.oversight.late_by', ['time' => $r->due_at->locale(app()->getLocale())->diffForHumans(syntax: true, parts: 2)]),
                        $r->status === 'due' => __('learning::reports.oversight.left', ['time' => $r->due_at->locale(app()->getLocale())->diffForHumans(syntax: true, parts: 2)]),
                        default => $at($r->submitted_at),
                    })
                    ->color(fn (SessionReport $r) => $r->status === 'overdue' || $r->late ? 'danger' : null),
                TextColumn::make('overall')->label(__('learning::reports.oversight.overall'))
                    ->state(fn (SessionReport $r) => $r->overall ? "{$r->overall}/5" : '—')->visible(in_array($tab, ['submitted', 'reviewed'], true)),
            ])
            ->recordActions([
                Action::make('review')
                    ->label(fn (SessionReport $r) => $r->status === 'submitted' ? __('learning::reports.oversight.review') : __('learning::reports.oversight.open'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (SessionReport $r) => in_array($r->status, ['submitted', 'reviewed'], true))
                    ->modalHeading(fn (SessionReport $r) => $r->getAttribute('student_name').' · '.$at($r->session_starts_at))
                    ->modalContent(fn (SessionReport $r) => view('learning::filament.report-detail', [
                        'report' => $r, 'items' => app(ReportFormQuery::class)->items($r), 'homework' => $r->homework()->first(), 'internal' => true,
                    ]))
                    ->schema(fn (SessionReport $r) => $r->status === 'submitted' ? [Textarea::make('note')->label(__('learning::reports.oversight.review_note'))->rows(2)] : [])
                    ->modalSubmitAction(fn (SessionReport $r, $action) => $r->status === 'submitted' ? $action : false)
                    ->modalSubmitActionLabel(__('learning::reports.oversight.review'))
                    ->modalCancelActionLabel(__('filament-actions::modal.actions.close.label'))
                    ->action(function (SessionReport $r, array $data) {
                        app(ReviewSessionReport::class)->handle(SessionReport::query()->findOrFail($r->id), (string) auth()->id(), $data['note'] ?? null);
                        Notification::make()->success()->title(__('learning::reports.oversight.reviewed'))->send();
                    }),
            ])
            ->emptyStateHeading(__('learning::reports.oversight.empty'))
            ->paginated([25, 50]);
    }
}
