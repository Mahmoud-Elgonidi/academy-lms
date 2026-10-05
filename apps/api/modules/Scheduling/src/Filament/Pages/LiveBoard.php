<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Scheduling\Actions\AllowUnfundedSession;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\ExtendSession;
use Modules\Scheduling\Actions\FlagForReview;
use Modules\Scheduling\Actions\LogContactAttempt;
use Modules\Scheduling\Actions\MarkStudentAbsent;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\LiveBoard\BoardClassifier;
use Modules\Scheduling\Domain\LiveBoard\BoardItem;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\LiveBoardView;
use Modules\Scheduling\Queries\LiveBoardQuery;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The live board: sessions now and in the next hour, what needs someone,
 * and the actions to take. Two layouts, chosen per user here or on
 * "My settings": "table" (everything in one list) and "focus" (problems
 * first, details in a side panel).
 */
final class LiveBoard extends Page
{
    public const LAYOUT_PREFERENCE = 'live_board.layout';

    public const LAYOUTS = ['table', 'focus'];

    protected string $view = 'scheduling::filament.live-board.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'live';

    public string $boardLayout = 'table';

    public string $viewTz = 'UTC';

    public ?string $selected = null;

    public string $filter = 'all';

    public bool $showDone = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewLiveBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::board.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::board.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function mount(): void
    {
        $this->boardLayout = app(UserPreferences::class)->get((string) auth()->id(), self::LAYOUT_PREFERENCE);
        $this->viewTz = app(ViewerTimezone::class)->effective();
    }

    /** The on-screen switch; saved for this user, same as on "My settings". */
    public function setLayout(string $layout): void
    {
        if (! in_array($layout, self::LAYOUTS, true)) {
            return;
        }

        app(UserPreferences::class)->set((string) auth()->id(), self::LAYOUT_PREFERENCE, $layout);
        $this->boardLayout = $layout;
    }

    /** Shows the board in another timezone for now; the profile timezone does not change. */
    public function setViewTimezone(string $timezone): void
    {
        if (Timezones::isValid($timezone)) {
            $this->viewTz = $timezone;
        }
    }

    public function select(string $id): void
    {
        $this->selected = ($this->boardLayout === 'table' && $this->selected === $id) ? null : $id;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'live', 'problems', 'upcoming', 'done'], true) ? $filter : 'all';
    }

    public function toggleDone(): void
    {
        $this->showDone = ! $this->showDone;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function board(): array
    {
        $now = CarbonImmutable::now();
        $tenant = app(TenantContext::class)->require();
        $query = app(LiveBoardQuery::class);
        $groups = (new BoardClassifier(app(AttendancePolicy::class)))
            ->classify($query->around($now, BoardClassifier::UPCOMING_WINDOW_MIN, $tenant->defaultTimezone), $now);

        $view = new LiveBoardView($now, $this->viewTz);
        $items = array_map(fn (array $group) => array_map(fn (BoardItem $i) => $view->item($i), $group), $groups);

        $dayStart = $now->setTimezone($this->viewTz)->startOfDay();
        $counts = $query->countsBetween($dayStart->utc(), $dayStart->addDay()->utc());
        $nextDeadline = collect($groups['attention'])->map(fn (BoardItem $i) => $i->deadline)->filter()->sort()->first();

        $selected = collect($items)->flatten(1)->firstWhere('id', $this->selected)
            ?? ($items['attention'][0] ?? $items['fine'][0] ?? $items['upcoming'][0] ?? null);

        return [
            'now' => $view->hm($now),
            'today' => $now->setTimezone($this->viewTz)->locale(app()->getLocale())->translatedFormat('l j F'),
            'city' => LiveBoardView::city($this->viewTz),
            'windowFrom' => $view->hm($now->subHour()),
            'windowTo' => $view->hm($now->addHour()),
            'groups' => $items,
            'all' => array_merge($items['attention'], $items['fine'], $items['upcoming'], $items['done']),
            'selected' => $selected,
            'kpis' => [
                ['label' => __('scheduling::board.kpis.today'), 'value' => array_sum($counts), 'tone' => 'default',
                    'hint' => __('scheduling::board.kpis.today_hint', ['done' => $counts[SessionStatus::Completed->value] ?? 0, 'left' => $counts[SessionStatus::Scheduled->value] ?? 0])],
                ['label' => __('scheduling::board.kpis.live'), 'value' => count($items['fine']) + count(array_filter($items['attention'], fn ($i) => $i['issue'] === 'weak_connection')), 'tone' => 'primary',
                    'hint' => __('scheduling::board.kpis.live_hint', ['weak' => count(array_filter($items['attention'], fn ($i) => $i['issue'] === 'weak_connection'))])],
                ['label' => __('scheduling::board.kpis.attention'), 'value' => count($items['attention']), 'tone' => count($items['attention']) > 0 ? 'danger' : 'default',
                    'hint' => $nextDeadline ? __('scheduling::board.kpis.attention_hint', ['time' => $view->hm($nextDeadline)]) : __('scheduling::board.kpis.attention_none')],
                ['label' => __('scheduling::board.kpis.absences'), 'value' => ($counts[SessionStatus::TeacherAbsent->value] ?? 0) + ($counts[SessionStatus::StudentAbsent->value] ?? 0), 'tone' => 'warning',
                    'hint' => __('scheduling::board.kpis.absences_hint', ['teacher' => $counts[SessionStatus::TeacherAbsent->value] ?? 0, 'student' => $counts[SessionStatus::StudentAbsent->value] ?? 0])],
            ],
            'zones' => $this->zones(),
        ];
    }

    /** @return list<array{id: string, label: string}> */
    private function zones(): array
    {
        $viewer = app(ViewerTimezone::class);
        $zones = array_unique(array_filter([$viewer->profile(), $viewer->academy(), 'Asia/Riyadh', 'Africa/Cairo', 'Asia/Dubai', 'Europe/London', 'America/New_York', $this->viewTz]));

        return array_values(array_map(fn (string $tz) => [
            'id' => $tz,
            'label' => LiveBoardView::city($tz).($tz === $viewer->profile() ? ' · '.__('scheduling::board.zone_mine') : ''),
        ], $zones));
    }

    // ---- Actions. Each one checks the ability and calls the same Action class the API will. ----

    public function contactAction(): Action
    {
        return $this->sessionAction('contact')
            ->label(fn (array $arguments) => __('scheduling::board.actions.'.match ($arguments['target'] ?? 'teacher') {
                'guardian' => 'call_guardian', 'student' => 'call_student', default => 'call_teacher'
            }))
            ->modalHeading(fn (array $arguments) => __('scheduling::board.contact.heading', ['who' => $this->contactName($arguments)]))
            ->modalDescription(fn (array $arguments) => $this->contactPhones($arguments))
            ->schema([
                Radio::make('outcome')->label(__('scheduling::board.contact.outcome'))
                    ->options(collect(LogContactAttempt::OUTCOMES)->mapWithKeys(fn ($o) => [$o => __('scheduling::board.outcomes.'.$o)])->all())
                    ->required(),
                Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500),
            ])
            ->modalSubmitActionLabel(__('scheduling::board.contact.save'))
            ->action(function (array $data, array $arguments) {
                app(LogContactAttempt::class)->handle($this->session($arguments), $arguments['target'] ?? 'teacher', $data['outcome'], $data['note'] ?? null, (string) auth()->id());
                $this->done(__('scheduling::board.done.contact'));
            });
    }

    public function cancelAction(): Action
    {
        return $this->sessionAction('cancel')
            ->label(fn (array $arguments) => __('scheduling::board.actions.'.(($arguments['makeup'] ?? false) ? 'cancel_makeup' : 'cancel')))
            ->color('danger')
            ->modalHeading(__('scheduling::board.cancel.heading'))
            ->fillForm(fn (array $arguments) => ['reason' => $arguments['reason'] ?? 'other', 'makeup' => (bool) ($arguments['makeup'] ?? false)])
            ->schema([
                Select::make('reason')->label(__('scheduling::board.cancel.reason'))
                    ->options(collect(CancelSession::REASONS)->mapWithKeys(fn ($r) => [$r => __('scheduling::board.cancel_reasons.'.$r)])->all())
                    ->required()->native(false),
                Toggle::make('makeup')->label(__('scheduling::board.cancel.makeup'))->helperText(__('scheduling::board.cancel.makeup_hint')),
                Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500),
            ])
            ->modalSubmitActionLabel(__('scheduling::board.cancel.submit'))
            ->action(function (array $data, array $arguments) {
                app(CancelSession::class)->handle($this->session($arguments), $data['reason'], (bool) $data['makeup'], $data['note'] ?? null, (string) auth()->id());
                $this->done(__('scheduling::board.done.cancelled'));
            });
    }

    public function markStudentAbsentAction(): Action
    {
        return $this->sessionAction('markStudentAbsent')
            ->label(__('scheduling::board.actions.mark_student_absent'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('scheduling::board.hints.mark_student_absent'))
            ->action(function (array $arguments) {
                app(MarkStudentAbsent::class)->handle($this->session($arguments), (string) auth()->id());
                $this->done(__('scheduling::board.done.student_absent'));
            });
    }

    public function extendAction(): Action
    {
        return $this->sessionAction('extend')
            ->label(__('scheduling::board.actions.extend'))
            ->schema([
                Radio::make('minutes')->label(__('scheduling::board.extend.minutes'))
                    ->options(collect(ExtendSession::ALLOWED)->mapWithKeys(fn ($m) => [$m => __('scheduling::board.extend.option', ['min' => $m])])->all())
                    ->default(5)->inline()->required(),
            ])
            ->action(function (array $data, array $arguments) {
                app(ExtendSession::class)->handle($this->session($arguments), (int) $data['minutes'], (string) auth()->id());
                $this->done(__('scheduling::board.done.extended', ['min' => $data['minutes']]));
            });
    }

    public function flagTechnicalAction(): Action
    {
        return $this->sessionAction('flagTechnical')
            ->label(__('scheduling::board.actions.flag_technical'))
            ->requiresConfirmation()
            ->modalDescription(__('scheduling::board.hints.flag_technical'))
            ->schema([Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500)])
            ->action(function (array $data, array $arguments) {
                app(FlagForReview::class)->handle($this->session($arguments), 'technical', $data['note'] ?? null, (string) auth()->id());
                $this->done(__('scheduling::board.done.flagged'));
            });
    }

    public function disputeAction(): Action
    {
        return $this->sessionAction('dispute')
            ->label(__('scheduling::board.actions.dispute'))
            ->requiresConfirmation()
            ->modalDescription(__('scheduling::board.hints.dispute'))
            ->schema([Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500)->required()])
            ->action(function (array $data, array $arguments) {
                app(FlagForReview::class)->handle($this->session($arguments), 'dispute', $data['note'], (string) auth()->id());
                $this->done(__('scheduling::board.done.flagged'));
            });
    }

    public function allowUnfundedAction(): Action
    {
        return $this->sessionAction('allowUnfunded')
            ->label(__('scheduling::board.actions.allow_unfunded'))
            ->requiresConfirmation()
            ->modalDescription(__('scheduling::board.hints.allow_unfunded'))
            ->action(function (array $arguments) {
                app(AllowUnfundedSession::class)->handle($this->session($arguments), (string) auth()->id());
                $this->done(__('scheduling::board.done.allowed'));
            });
    }

    public function decideAction(): Action
    {
        return $this->sessionAction('decide')
            ->label(__('scheduling::board.actions.resolve'))
            ->schema([
                Radio::make('outcome')->label(__('scheduling::board.resolve.outcome'))
                    ->options(collect(ResolveReview::OUTCOMES)->mapWithKeys(fn ($o) => [$o => __('scheduling::board.resolve.outcomes.'.$o)])->all())
                    ->required(),
                Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500),
            ])
            ->action(function (array $data, array $arguments) {
                app(ResolveReview::class)->handle($this->session($arguments), $data['outcome'], $data['note'] ?? null, (string) auth()->id());
                $this->done(__('scheduling::board.done.resolved'));
            });
    }

    /** Placeholder for actions whose module is not built yet (messages, billing, classroom, substitutes). */
    public function soonAction(): Action
    {
        return Action::make('soon')
            ->button()
            ->size('sm')
            ->color('gray')
            ->label(fn (array $arguments) => __('scheduling::board.actions.'.($arguments['what'] ?? 'soon')))
            ->disabled()
            ->tooltip(fn (array $arguments) => __('scheduling::board.hints.soon_'.match ($arguments['what'] ?? '') {
                'payment_link' => 'billing', 'observe' => 'classroom', 'substitute' => 'substitute', default => 'messages',
            }));
    }

    /** @param  list<array{name: string, args: array<string, mixed>}>  $actions */
    public function moreActions(array $actions): ActionGroup
    {
        return ActionGroup::make(array_map(fn (array $a) => ($this->{$a['name'].'Action'})($a['args']), $actions))
            ->label(__('scheduling::board.more'))
            ->icon(Heroicon::EllipsisHorizontal)
            ->iconButton()
            ->color('gray')
            ->size('sm');
    }

    private function sessionAction(string $name): Action
    {
        return Action::make($name)
            ->authorize(fn () => Gate::allows(Ability::ManageSessions->value))
            ->button()
            ->size('sm')
            ->color(fn (array $arguments) => ($arguments['primary'] ?? false) ? 'primary' : 'gray');
    }

    /** @param  array<string, mixed>  $arguments */
    private function session(array $arguments): Session
    {
        return Session::query()->with('participants')->findOrFail($arguments['session'] ?? null);
    }

    private function done(string $message): void
    {
        unset($this->board);
        Notification::make()->success()->title($message)->send();
    }

    /** @param  array<string, mixed>  $arguments */
    private function contactName(array $arguments): string
    {
        $item = collect($this->board['all'])->firstWhere('id', $arguments['session'] ?? null);

        return match ($arguments['target'] ?? 'teacher') {
            'guardian' => $item['guardians'][0]['name'] ?? __('scheduling::board.targets.guardian'),
            'student' => $item['student'] ?? '',
            default => $item['teacher'] ?? '',
        };
    }

    /** @param  array<string, mixed>  $arguments */
    private function contactPhones(array $arguments): string
    {
        $item = collect($this->board['all'])->firstWhere('id', $arguments['session'] ?? null);
        $none = __('scheduling::board.contact.no_phone');
        // Phone numbers stay left-to-right inside Arabic text.
        $ltr = fn (?string $phone) => $phone === null ? $none : "\u{2066}{$phone}\u{2069}";

        return match ($arguments['target'] ?? 'teacher') {
            'guardian' => implode(' · ', array_map(fn (array $g) => $g['name'].': '.$ltr($g['phone']), $item['guardians'] ?? [])) ?: $none,
            'student' => $ltr($item['studentPhone'] ?? null),
            default => $ltr($item['teacherPhone'] ?? null),
        };
    }
}
