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
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\Timezones;

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
        $shifts = $proposal && $proposal->status !== ShiftProposalStatus::Submitted ? $proposal->shifts() : $this->current();

        $this->form->fill([
            'blocks' => array_map(fn (ShiftBlock $b) => ['weekday' => $b->weekday, 'start' => $b->start(), 'end' => $b->end() === '24:00' ? '23:55' : $b->end()], $shifts->blocks),
            'effective_from' => $proposal?->effective_from->toDateString() ?? $this->today()->addDay()->toDateString(),
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
        unset($this->proposal);
    }

    #[Computed]
    public function proposal(): ?ShiftProposal
    {
        return app(ShiftProposals::class)->openOrDraft((string) auth()->id())?->load('rules', 'comments');
    }

    /** @return array<int, list<string>> weekday => "from – to" in force today */
    public function week(?WeeklyShifts $shifts = null): array
    {
        $out = [];
        foreach (($shifts ?? $this->current())->blocks as $b) {
            $out[$b->weekday][] = Clock::wall($b->start()).' – '.Clock::wall($b->end());
        }
        ksort($out);

        return $out;
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
        unset($this->proposal);
        Notification::make()->success()->title($message)->send();
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
