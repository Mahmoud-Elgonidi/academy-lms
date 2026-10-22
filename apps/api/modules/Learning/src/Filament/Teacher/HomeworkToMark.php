<?php

namespace Modules\Learning\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
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
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Assist\RecitationTarget;
use Modules\Learning\Http\V1\Resources\RecitationPresenter;
use Modules\Learning\Models\Homework;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Homework the teacher set: handed in and waiting for marking first; recordings
 * play in the page, each with its check word by word when the homework says
 * what to recite (for the teacher alone). Follows the teacher's `homework` signal.
 */
final class HomeworkToMark extends Page implements HasTable
{
    use InteractsWithTable;

    public const TABS = ['submitted', 'assigned', 'missed', 'reviewed'];

    protected string $view = 'learning::filament.teacher.homework';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'homework';

    #[Url]
    public string $tab = 'submitted';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::teacher.nav_homework');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Homework::query()->where('teacher_id', auth()->id())->where('status', 'submitted')->count();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::teacher.homework.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('learning::teacher.homework.subtitle');
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return Homework::query()->where('teacher_id', auth()->id())->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /** The teacher's own channel, for the page to follow. */
    public function liveChannel(): string
    {
        return PersonalChannel::name((string) auth()->id());
    }

    public function updatedTab(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'submitted';
        $tz = app(ViewerTimezone::class)->effective();
        $at = fn (?CarbonImmutable $t) => $t?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('D j M · H:i'));

        return $table
            ->query(Homework::query()->with('submissions')
                ->join('users as s', fn ($j) => $j->on('s.academy_id', '=', 'homework.academy_id')->on('s.id', '=', 'homework.student_id'))
                ->where('homework.teacher_id', auth()->id())->where('homework.status', $tab)
                ->select('homework.*', 's.name as student_name'))
            ->defaultSort('homework.due_at', $tab === 'reviewed' ? 'desc' : 'asc')
            ->columns([
                TextColumn::make('title')->label(__('learning::teacher.write.homework_title'))->weight('semibold')
                    ->description(fn (Homework $h) => __('learning::homework.accepts.'.$h->accepts)),
                TextColumn::make('student_name')->label(__('learning::teacher.homework.student')),
                TextColumn::make('due_at')->label(__('learning::teacher.homework.due'))->state(fn (Homework $h) => $at($h->due_at)),
                TextColumn::make('handed')->label(__('learning::teacher.homework.handed'))
                    ->state(fn (Homework $h) => $at($h->submissions->last()?->submitted_at) ?? '—')
                    ->color(fn (Homework $h) => $h->submissions->last()?->submitted_at?->greaterThan($h->due_at) ? 'danger' : null),
                TextColumn::make('grade')->label(__('learning::teacher.homework.grade'))->state(fn (Homework $h) => $h->grade ? "{$h->grade}/5" : '—')
                    ->visible($tab === 'reviewed'),
            ])
            ->recordActions([
                Action::make('mark')
                    ->label(fn (Homework $h) => $h->status === 'submitted' ? __('learning::teacher.homework.mark') : __('learning::teacher.homework.open'))
                    ->button()->size('sm')->color(fn (Homework $h) => $h->status === 'submitted' ? 'primary' : 'gray')
                    ->modalHeading(fn (Homework $h) => $h->title.' · '.$h->getAttribute('student_name'))
                    ->modalContent(fn (Homework $h) => view('learning::filament.teacher.submissions', [
                        'homework' => $h, 'at' => $at,
                        'expected' => app(RecitationTarget::class)->present($h->expectedSource()),
                        'checks' => app(RecitationPresenter::class)->checks($h),
                    ]))
                    ->schema(fn (Homework $h) => $h->status === 'submitted' ? [
                        ToggleButtons::make('grade')->label(__('learning::teacher.homework.grade'))->inline()->options(array_combine(range(1, 5), array_map('strval', range(1, 5)))),
                        Textarea::make('feedback')->label(__('learning::teacher.homework.feedback'))->rows(3),
                    ] : [])
                    ->modalSubmitAction(fn (Homework $h, $action) => $h->status === 'submitted' ? $action : false)
                    ->modalSubmitActionLabel(__('learning::teacher.homework.mark'))
                    ->action(function (Homework $h, array $data) {
                        app(ReviewHomework::class)->handle(Homework::query()->findOrFail($h->id), (string) auth()->id(), isset($data['grade']) ? (int) $data['grade'] : null, $data['feedback'] ?? null);
                        Notification::make()->success()->title(__('learning::teacher.homework.marked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('learning::teacher.homework.empty'))
            ->paginated([25, 50]);
    }
}
