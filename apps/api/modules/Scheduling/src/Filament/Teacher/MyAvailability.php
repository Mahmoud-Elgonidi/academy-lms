<?php

namespace Modules\Scheduling\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CommentOnShiftProposal;
use Modules\Scheduling\Actions\Shifts\SaveShiftProposal;
use Modules\Scheduling\Actions\Shifts\ShiftProposals;
use Modules\Scheduling\Actions\Shifts\WithdrawShiftProposal;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftProposalComment;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\ShiftProposalView;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The teacher's hours: the week in force, and a proposal for a new one
 * that goes to the academy for approval (docs/architecture/02, "مصدر
 * الشفتات"). The same Actions as the teacher API and the back office.
 *
 * @property-read Schema $form
 */
final class MyAvailability extends Page
{
    protected string $view = 'scheduling::filament.teacher.availability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'availability';

    public ?array $data = [];

    public string $reply = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ProposeOwnShifts->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::teacher.availability.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::teacher.availability.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::teacher.availability.subtitle', ['zone' => Timezones::city($this->timezone())]);
    }

    public function mount(): void
    {
        $proposal = $this->proposal;
        // A new proposal starts from the latest approved week (one that starts later, else today's), and not before it begins.
        $shifts = $proposal && $proposal->status !== ShiftProposalStatus::Submitted
            ? $proposal->shifts()
            : app(ShiftProposals::class)->latestShifts((string) auth()->id(), $this->today(), $this->timezone());
        $tomorrow = $this->today()->addDay()->toDateString();
        $later = $this->upcomingSchedule()?->effectiveFrom()->toDateString();

        $this->form->fill([
            'blocks' => array_map(fn (ShiftBlock $b) => ['weekday' => $b->weekday, 'start' => $b->start(), 'end' => $b->end() === '24:00' ? '23:55' : $b->end()], $shifts->blocks),
            'effective_from' => $proposal?->effective_from->toDateString() ?? ($later !== null && $later > $tomorrow ? $later : $tomorrow),
            'note' => $proposal?->note,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $l = 'scheduling::teacher.availability.';

        return $schema->statePath('data')->components([
            Repeater::make('blocks')->label(__($l.'blocks'))->addActionLabel(__($l.'add'))->reorderable(false)->defaultItems(0)
                ->table([TableColumn::make(__($l.'day')), TableColumn::make(__($l.'from')), TableColumn::make(__($l.'to'))])
                ->schema([
                    Select::make('weekday')->label(__($l.'day'))->options(__('scheduling::shifts.days'))->required(),
                    TimePicker::make('start')->label(__($l.'from'))->seconds(false)->format('H:i')->native(false)->displayFormat(Clock::pattern('H:i'))->minutesStep(5)->required(),
                    TimePicker::make('end')->label(__($l.'to'))->seconds(false)->format('H:i')->native(false)->displayFormat(Clock::pattern('H:i'))->minutesStep(5)->required()->after('start'),
                ]),
            DatePicker::make('effective_from')->label(__($l.'from_date'))->required()->minDate($this->today()->addDay())
                ->helperText(__($l.'from_hint', ['days' => app(ShiftProposals::class)->settings()->reduction_notice_days])),
            Textarea::make('note')->label(__($l.'note'))->rows(2)->maxLength(1000),
        ]);
    }

    public function send(): void
    {
        $this->store(submit: true);
    }

    protected function getHeaderActions(): array
    {
        $l = 'scheduling::teacher.availability.';

        return [
            Action::make('draft')->label(__($l.'save_draft'))->color('gray')->action(fn () => $this->store(submit: false)),
            Action::make('accept')->label(__($l.'accept'))->color('success')->requiresConfirmation()
                ->visible(fn () => $this->proposal?->status === ShiftProposalStatus::Countered)
                ->action(function () use ($l) {
                    app(ApproveShiftProposal::class)->handle($this->proposal, (string) auth()->id(), 'teacher');
                    $this->done(__($l.'accepted'));
                }),
            Action::make('withdraw')->label(__($l.'withdraw'))->color('danger')->requiresConfirmation()
                ->visible(fn () => $this->proposal?->status->canTransitionTo(ShiftProposalStatus::Withdrawn) ?? false)
                ->action(function () use ($l) {
                    app(WithdrawShiftProposal::class)->handle($this->proposal, (string) auth()->id());
                    $this->done(__($l.'withdrawn'));
                }),
        ];
    }

    public function comment(): void
    {
        $this->validate(['reply' => ['required', 'string', 'max:2000']]);
        abort_unless($this->proposal !== null, 404);
        app(CommentOnShiftProposal::class)->handle($this->proposal, (string) auth()->id(), 'teacher', $this->reply);
        $this->reply = '';
        unset($this->proposal, $this->upcoming);
    }

    #[Computed]
    public function proposal(): ?ShiftProposal
    {
        return app(ShiftProposals::class)->openOrDraft((string) auth()->id())?->load('rules', 'comments');
    }

    /**
     * The week in force, as the admins' day grid shows it.
     *
     * @return list<array<string, mixed>>
     */
    public function currentGrid(): array
    {
        return app(ShiftProposalView::class)->compare($this->current(), $this->current());
    }

    /**
     * An approved schedule that starts later: its first day, the hours before and after, and both weeks side by side.
     *
     * @return array{from: string, summary: array<string, mixed>, week: list<array<string, mixed>>}|null
     */
    #[Computed]
    public function upcoming(): ?array
    {
        $upcoming = $this->upcomingSchedule();
        if ($upcoming === null) {
            return null;
        }
        $view = app(ShiftProposalView::class);

        return [
            'from' => $upcoming->effectiveFrom()->locale(app()->getLocale())->translatedFormat('l j F'),
            'summary' => $view->summary($this->current(), $upcoming->shifts()),
            'week' => $view->compare($this->current(), $upcoming->shifts()),
        ];
    }

    /**
     * Where the proposal stands: four steps (draft, sent, in review, the academy's answer) and the one it is on.
     *
     * @return array{steps: list<array{label: string, state: string}>, tone: string}|null
     */
    public function steps(): ?array
    {
        $status = $this->proposal?->status;
        if ($status === null) {
            return null;
        }
        $l = 'scheduling::teacher.availability.steps.';
        $at = match ($status) {
            ShiftProposalStatus::Draft => 0,
            ShiftProposalStatus::Submitted => 2,
            default => 3,
        };
        $answer = match ($status) {
            ShiftProposalStatus::Countered => __($l.'countered'),
            ShiftProposalStatus::ChangesRequested => __($l.'changes'),
            default => __($l.'decision'),
        };
        $labels = [__($l.'draft'), __($l.'sent'), __($l.'review'), $answer];

        return [
            'steps' => array_map(fn (int $i) => ['label' => $labels[$i], 'state' => $i < $at ? 'done' : ($i === $at ? 'now' : 'next')], array_keys($labels)),
            'tone' => $at === 3 ? 'warning' : 'primary',
        ];
    }

    /**
     * The conversation in time order: the teacher's words and the academy's as bubbles, the proposal's steps as small lines.
     *
     * @return list<array<string, mixed>>
     */
    public function thread(): array
    {
        $p = $this->proposal;
        if ($p === null) {
            return [];
        }
        $zone = app(ViewerTimezone::class)->effective();
        $when = fn (CarbonImmutable $at) => $at->setTimezone($zone)->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M · H:i'));
        $items = [];
        if ($p->submitted_at !== null) {
            $items[] = ['type' => 'event', 'text' => __('scheduling::teacher.availability.sent_event'), 'at' => $when($p->submitted_at), 'ts' => $p->submitted_at->getTimestamp()];
        }
        foreach ($p->comments as $c) {
            /** @var ShiftProposalComment $c */
            $items[] = $c->author_role === 'system'
                ? ['type' => 'event', 'text' => $c->body, 'at' => $when($c->created_at), 'ts' => $c->created_at->getTimestamp()]
                : ['type' => 'message', 'mine' => $c->author_id === $p->teacher_id, 'who' => __('scheduling::shifts.roles.'.$c->author_role), 'body' => $c->body, 'at' => $when($c->created_at), 'ts' => $c->created_at->getTimestamp()];
        }
        usort($items, fn (array $a, array $b) => $a['ts'] <=> $b['ts']);

        return $items;
    }

    public function current(): WeeklyShifts
    {
        return app(ShiftProposals::class)->scheduleOn((string) auth()->id(), $this->today(), $this->timezone());
    }

    private function store(bool $submit): void
    {
        $data = $this->form->getState();
        try {
            $shifts = WeeklyShifts::fromArray(array_map(fn (array $b) => [
                'weekday' => (int) $b['weekday'], 'start' => substr((string) $b['start'], 0, 5), 'end' => substr((string) $b['end'], 0, 5),
            ], $data['blocks'] ?? []), $this->timezone());
            app(SaveShiftProposal::class)->handle((string) auth()->id(), $shifts, CarbonImmutable::parse($data['effective_from'], $this->timezone()), $data['note'] ?? null, submit: $submit);
        } catch (InvalidArgumentException) {
            $this->problem([__('scheduling::shifts.problems.empty')]);

            return;
        } catch (ValidationException $e) {
            $this->problem(array_merge(...array_values($e->errors())));

            return;
        }
        $this->done(__('scheduling::teacher.availability.'.($submit ? 'sent' : 'saved')));
    }

    /** @param  list<string>  $messages */
    private function problem(array $messages): void
    {
        Notification::make()->danger()->title(__('scheduling::teacher.availability.not_sent'))->body(implode("\n", $messages))->persistent()->send();
    }

    private function done(string $message): void
    {
        unset($this->proposal, $this->upcoming);
        Notification::make()->success()->title($message)->send();
    }

    private function upcomingSchedule(): ?ShiftSchedule
    {
        return app(ShiftProposals::class)->upcoming((string) auth()->id(), $this->today());
    }

    private function timezone(): string
    {
        return app(ShiftProposals::class)->teacherTimezone((string) auth()->id());
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }
}
