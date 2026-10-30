<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CommentOnShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\ResolveShiftConflict;
use Modules\Scheduling\Actions\Shifts\ReviewShiftProposal as Review;
use Modules\Scheduling\Actions\Shifts\ShiftProposals as Proposals;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\ShiftBlocksField;
use Modules\Scheduling\Filament\ShiftProposalView;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * Teachers' shifts (docs/architecture/02, section 2): proposals grouped by
 * status, what waits on the admins first; each opens in a slide-over with the
 * two weeks side by side, the booked students it leaves out, the conversation
 * and one clear next step. A second tab holds every teacher's schedule, in
 * force and approved to come, with "Edit" starting from the newest.
 */
final class ShiftProposals extends Page
{
    protected string $view = 'scheduling::filament.shift-proposals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = -4;

    protected static ?string $slug = 'shifts/proposals';

    #[Url]
    public string $tab = 'proposals';

    public string $reply = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewShifts->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::review.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ShiftProposal::query()->where('status', ShiftProposalStatus::Submitted->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::review.list_title');
    }

    /** Shifts are kept on each teacher's own clock: the page says so, and names the zone beside each teacher. */
    public function getSubheading(): string|Htmlable|null
    {
        return ZoneHint::teachers();
    }

    public function mount(): void
    {
        // Links from notifications and from the old review address open the slide-over in place.
        $uuid = fn (mixed $v) => is_string($v) && preg_match('/^[0-9a-f-]{36}$/i', $v) ? $v : null;
        if ($id = $uuid(request()->query('proposal'))) {
            $this->defaultAction = 'open';
            $this->defaultActionArguments = ['proposal' => $id];
        } elseif ($id = $uuid(request()->query('teacher'))) {
            $this->tab = 'teachers';
            $this->defaultAction = 'teacher';
            $this->defaultActionArguments = ['teacher' => $id];
        }
        if (! in_array($this->tab, ['proposals', 'teachers'], true)) {
            $this->tab = 'proposals';
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    #[Computed]
    public function groups(): array
    {
        return app(ShiftProposalView::class)->groups();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function teachers(): array
    {
        return app(ShiftProposalView::class)->teachers();
    }

    public function resolve(string $proposalId, string $key, ?string $kind): void
    {
        $this->authorizeReview();
        app(ResolveShiftConflict::class)->handle(ShiftProposal::query()->findOrFail($proposalId), $key, $kind, (string) auth()->id());
        unset($this->groups);
    }

    public function sendReply(string $proposalId): void
    {
        $this->authorizeReview();
        try {
            app(CommentOnShiftProposal::class)->handle(ShiftProposal::query()->findOrFail($proposalId), (string) auth()->id(), 'admin', $this->reply);
        } catch (ValidationException $e) {
            // The composer is this page's own field: the refusal shows under it.
            throw ValidationException::withMessages(['reply' => collect($e->errors())->flatten()->all()]);
        }
        $this->reply = '';
    }

    protected function getHeaderActions(): array
    {
        return [$this->editor(Action::make('editTeacher'))];
    }

    /** One proposal in a slide-over; its next steps sit in the footer. */
    public function openAction(): Action
    {
        return Action::make('open')
            ->authorize(fn () => Gate::allows(Ability::ReviewShifts->value))
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (array $arguments) => $this->proposalView($arguments)['teacher'] ?? __('scheduling::review.missing'))
            ->modalDescription(fn (array $arguments) => ($r = $this->proposalView($arguments))
                ? __('scheduling::review.slide_hint', ['status' => __('scheduling::shifts.statuses.'.$r['status']->value), 'date' => $r['from']])
                : null)
            ->modalContent(fn (array $arguments) => view('scheduling::filament.shift-proposal', ['r' => $this->proposalView($arguments)]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('scheduling::review.close'))
            ->extraModalFooterActions(fn (array $arguments) => is_string($arguments['proposal'] ?? null) ? $this->nextSteps($arguments['proposal']) : []);
    }

    /** A teacher's schedule now and approved to come, with "Edit" starting from the newest. */
    public function teacherAction(): Action
    {
        return Action::make('teacher')
            ->authorize(fn () => Gate::allows(Ability::ReviewShifts->value))
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (array $arguments) => __('scheduling::review.schedule_of', ['teacher' => $this->teacherView($arguments)['name'] ?? '—']))
            ->modalContent(fn (array $arguments) => view('scheduling::filament.shift-teacher', ['t' => $this->teacherView($arguments)]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('scheduling::review.close'))
            ->extraModalFooterActions(function (array $arguments): array {
                $t = $this->teacherView($arguments);
                if ($t === null) {
                    return [];
                }

                return array_values(array_filter([
                    $this->editor(Action::make('edit'), $t['id'])->label(__('scheduling::review.edit'))->color('primary'),
                    $t['open'] ? Action::make('proposal')->label(__('scheduling::review.open_proposal'))->color('gray')
                        ->url(self::getUrl(['proposal' => $t['open']['id']])) : null,
                ]));
            });
    }

    /**
     * The proposal's next steps: one primary for its state (approve while it
     * waits on the admins, edit the counter-offer while it is with the
     * teacher), the rest in "More".
     *
     * @return list<Action|ActionGroup>
     */
    private function nextSteps(string $id): array
    {
        $p = fn () => ShiftProposal::query()->with('rules')->findOrFail($id);
        $status = fn (): ?ShiftProposalStatus => ShiftProposal::query()->whereKey($id)->first(['id', 'status'])?->status;
        $submitted = fn () => $status() === ShiftProposalStatus::Submitted;
        $withTeacher = fn () => in_array($status(), [ShiftProposalStatus::Countered, ShiftProposalStatus::ChangesRequested], true);
        $view = fn () => $this->proposalView(['proposal' => $id]);
        $unresolved = fn () => $view()['unresolved'] ?? 0;

        $counter = fn (string $name) => Action::make($name)
            ->label(__('scheduling::review.actions.counter'))->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('scheduling::review.actions.counter'))
            ->modalDescription(fn () => __('scheduling::review.counter_hint', ['city' => $view()['city'] ?? '']))
            ->fillForm(fn () => ['blocks' => ShiftBlocksField::fill($p()->shifts()), 'effective_from' => $p()->effective_from->toDateString()])
            ->schema([
                ShiftBlocksField::make(),
                DatePicker::make('effective_from')->label(__('scheduling::review.effective_from'))->required()->native(false)
                    ->minDate(fn () => CarbonImmutable::now()->subDay()->toDateString())->helperText(__('scheduling::review.effective_from_hint')),
                Textarea::make('note')->label(__('scheduling::review.note_to_teacher'))->rows(2),
            ])
            ->action(fn (array $data, Action $action) => $this->decide(fn () => ShiftBlocksField::refusalsOnBlocks($action, fn () => app(CounterShiftProposal::class)->handle(
                $p()->teacher_id, WeeklyShifts::fromArray($data['blocks'] ?? [], $p()->timezone), CarbonImmutable::parse($data['effective_from']), $data['note'] ?? null, (string) auth()->id(),
            )), 'countered'));

        return [
            Action::make('approve')->label(__('scheduling::review.actions.approve'))->icon(Heroicon::OutlinedCheck)->color('primary')
                ->visible($submitted)
                ->disabled(fn () => $unresolved() > 0)
                ->tooltip(fn () => $unresolved() > 0 ? __('scheduling::review.resolve_first', ['left' => $unresolved(), 'total' => count($view()['conflicts'] ?? [])]) : null)
                ->requiresConfirmation()
                ->modalDescription(fn () => __('scheduling::review.approve_hint', ['date' => $view()['from'] ?? '', 'teacher' => $view()['teacher'] ?? '']))
                ->action(fn () => $this->decide(fn () => app(ApproveShiftProposal::class)->handle($p(), (string) auth()->id(), 'admin'), 'approved')),

            $counter('counter')->color('primary')->visible($withTeacher),

            ActionGroup::make([
                $counter('counterOffer')->visible($submitted),

                Action::make('requestChanges')->label(__('scheduling::review.actions.request_changes'))->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->visible($submitted)
                    ->schema([Textarea::make('note')->label(__('scheduling::review.what_to_change'))->required()->rows(3)])
                    ->action(fn (array $data) => $this->decide(fn () => app(Review::class)->requestChanges($p(), $data['note'], (string) auth()->id()), 'changes_requested')),

                Action::make('override')->label(__('scheduling::review.actions.override'))->icon(Heroicon::OutlinedShieldExclamation)->color('warning')
                    ->visible(fn () => ($submitted() && $unresolved() > 0) || $status() === ShiftProposalStatus::Countered)
                    ->modalDescription(__('scheduling::review.override_hint'))
                    ->schema([Textarea::make('reason')->label(__('scheduling::review.reason'))->required()->rows(3)])
                    ->action(fn (array $data) => $this->decide(fn () => app(ApproveShiftProposal::class)->handle($p(), (string) auth()->id(), 'admin', $data['reason']), 'approved')),

                Action::make('reject')->label(__('scheduling::review.actions.reject'))->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible($submitted)
                    ->schema([Textarea::make('reason')->label(__('scheduling::review.reason'))->required()->rows(3)])
                    ->action(fn (array $data) => $this->decide(fn () => app(Review::class)->reject($p(), $data['reason'], (string) auth()->id()), 'rejected')),

                Action::make('schedule')->label(__('scheduling::review.teacher_schedule'))->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn () => self::getUrl(['teacher' => ShiftProposal::query()->whereKey($id)->value('teacher_id')])),
            ])->label(__('scheduling::review.more'))->icon(Heroicon::OutlinedEllipsisHorizontal)->button()->color('gray'),
        ];
    }

    /**
     * The schedule editor: blocks start from the teacher's newest approved
     * schedule (one approved to start later, else the one in force), and the
     * start date from that later schedule's day, else today. Sent as a
     * counter-offer (applied at once in an "admin only" academy).
     */
    private function editor(Action $action, ?string $teacherId = null): Action
    {
        return $action
            ->label(__('scheduling::review.edit_teacher'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->authorize(fn () => Gate::allows(Ability::ReviewShifts->value))
            ->modalHeading(__('scheduling::review.edit_teacher'))
            ->modalDescription(__('scheduling::review.edit_teacher_hint'))
            ->modalSubmitActionLabel(__('scheduling::review.send_schedule'))
            ->fillForm(fn () => $teacherId !== null ? ['teacher_id' => $teacherId] + $this->startingPoint($teacherId) : ['effective_from' => CarbonImmutable::now()->toDateString()])
            ->schema([
                Select::make('teacher_id')->label(__('scheduling::review.teacher'))
                    ->options(fn () => DB::table('users as u')
                        ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
                        ->where('r.role', 'teacher')->orderBy('u.name')->pluck('u.name', 'u.id'))
                    ->searchable()->required()->live()
                    ->hidden($teacherId !== null)
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if ($state) {
                            $start = $this->startingPoint($state);
                            $set('blocks', $start['blocks']);
                            $set('effective_from', $start['effective_from']);
                        }
                    }),
                ShiftBlocksField::make(),
                DatePicker::make('effective_from')->label(__('scheduling::review.effective_from'))->required()->native(false)
                    ->minDate(fn () => CarbonImmutable::now()->subDay()->toDateString())->helperText(__('scheduling::review.effective_from_hint')),
                Textarea::make('note')->label(__('scheduling::review.note_to_teacher'))->rows(2),
            ])
            ->action(function (array $data, Action $action) use ($teacherId) {
                $this->authorizeReview();
                $teacher = $teacherId ?? (string) $data['teacher_id'];
                $tz = app(Proposals::class)->teacherTimezone($teacher);
                $proposal = ShiftBlocksField::refusalsOnBlocks($action, fn () => app(CounterShiftProposal::class)->handle(
                    $teacher,
                    WeeklyShifts::fromArray($data['blocks'] ?? [], $tz),
                    CarbonImmutable::parse($data['effective_from']),
                    $data['note'] ?? null,
                    (string) auth()->id(),
                ));
                Notification::make()->success()->title(__('scheduling::review.done.'.($proposal->status === ShiftProposalStatus::Approved ? 'approved' : 'countered')))->send();
                $this->redirect(self::getUrl(['proposal' => $proposal->id]));
            });
    }

    /** @return array{blocks: list<array{weekday: int, start: string, end: string}>, effective_from: string} */
    public function startingPoint(string $teacherId): array
    {
        $proposals = app(Proposals::class);
        $tz = $proposals->teacherTimezone($teacherId);
        $today = CarbonImmutable::now($tz)->startOfDay();
        $upcoming = $proposals->upcoming($teacherId, $today);

        return [
            'blocks' => ShiftBlocksField::fill($proposals->latestShifts($teacherId, $today, $tz)),
            'effective_from' => ($upcoming?->effectiveFrom() ?? $today)->toDateString(),
        ];
    }

    /** @param  array<string, mixed>  $arguments @return array<string, mixed>|null */
    private function proposalView(array $arguments): ?array
    {
        $id = $arguments['proposal'] ?? null;

        return is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id) ? app(ShiftProposalView::class)->proposal($id) : null;
    }

    /** @param  array<string, mixed>  $arguments @return array<string, mixed>|null */
    private function teacherView(array $arguments): ?array
    {
        $id = $arguments['teacher'] ?? null;
        $isTeacher = is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id) && DB::table('user_roles')->where('user_id', $id)->where('role', 'teacher')->exists();

        return $isTeacher ? app(ShiftProposalView::class)->teacher($id) : null;
    }

    private function decide(callable $action, string $outcome): void
    {
        $this->authorizeReview();
        $action();
        unset($this->groups, $this->teachers);
        Notification::make()->success()->title(__('scheduling::review.done.'.$outcome))->send();
    }

    private function authorizeReview(): void
    {
        abort_unless(Gate::allows(Ability::ReviewShifts->value), 403);
    }
}
