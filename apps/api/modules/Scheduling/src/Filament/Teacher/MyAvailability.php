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
use Modules\Scheduling\Domain\Shifts\ProposalRules;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The teacher's hours (docs/architecture/02, "مصدر الشفتات"), one thing at a
 * time, as the portal and the app show them: on top the one thing that
 * matters now (the open proposal with its step and its one action, else an
 * approved week that starts later, else a proposal turned down lately); then
 * one card with the agreed week (a switch between now and the later one);
 * "propose a change" under it while nothing is open; the history folded away.
 * The same Actions as the teacher API and the back office.
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

    /** The proposal form is open (a new proposal, or the open one being edited). */
    public bool $editing = false;

    /** A proposal turned down this recently still shows on top. */
    private const REJECTED_SHOWS_DAYS = 14;

    /** The bar of a day starts at 06:00 (or earlier on the hour when a span does) and ends at midnight. */
    private const MORNING = 360;

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

    public function saveDraft(): void
    {
        $this->store(submit: false);
    }

    public function startEditing(): void
    {
        $this->editing = true;
    }

    public function stopEditing(): void
    {
        $this->editing = false;
    }

    /** The academy's counter-offer, taken as it is. */
    public function acceptAction(): Action
    {
        $l = 'scheduling::teacher.availability.';

        return Action::make('accept')->label(__($l.'accept'))->color('primary')->icon(Heroicon::OutlinedCheck)->requiresConfirmation()
            ->visible(fn () => $this->proposal?->status === ShiftProposalStatus::Countered)
            ->action(function () use ($l) {
                app(ApproveShiftProposal::class)->handle($this->proposal, (string) auth()->id(), 'teacher');
                $this->done(__($l.'accepted'));
            });
    }

    /** Withdraw a sent proposal (a draft is only finished and sent). */
    public function withdrawAction(): Action
    {
        $l = 'scheduling::teacher.availability.';

        return Action::make('withdraw')->label(__($l.'withdraw'))->color('danger')->link()->requiresConfirmation()
            ->visible(fn () => $this->proposal !== null && $this->proposal->status !== ShiftProposalStatus::Draft
                && $this->proposal->status->canTransitionTo(ShiftProposalStatus::Withdrawn))
            ->action(function () use ($l) {
                app(WithdrawShiftProposal::class)->handle($this->proposal, (string) auth()->id());
                $this->done(__($l.'withdrawn'));
            });
    }

    public function comment(): void
    {
        $this->validate(['reply' => ['required', 'string', 'max:2000']]);
        abort_unless($this->proposal !== null, 404);
        app(CommentOnShiftProposal::class)->handle($this->proposal, (string) auth()->id(), 'teacher', $this->reply);
        $this->reply = '';
        unset($this->proposal, $this->hero);
    }

    #[Computed]
    public function proposal(): ?ShiftProposal
    {
        return app(ShiftProposals::class)->openOrDraft((string) auth()->id())?->load('rules', 'comments');
    }

    /**
     * The one thing on top: the open proposal (its step, what it means, its one
     * action), else an approved week that starts later, else a proposal turned
     * down in the last two weeks; null when there is nothing to say.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function hero(): ?array
    {
        $l = 'scheduling::teacher.availability.hero.';
        $p = $this->proposal;
        if ($p !== null) {
            $stage = $p->status->value;
            $countered = $p->status === ShiftProposalStatus::Countered;
            $before = $countered && $p->revision > 1 ? $p->shifts($p->revision - 1) : $this->latest();

            return [
                'kind' => 'proposal',
                'stage' => $stage,
                'title' => __($l.$stage.'.title'),
                'text' => __($l.$stage.'.text'),
                'from' => $this->day($p->effective_from),
                'hours' => ProposalRules::hours($p->shifts()->weeklyMinutes()),
                'week' => $this->rows($p->shifts(), $before),
                'open' => $countered,
                'messages' => $p->comments->where('author_role', '!=', 'system')->count(),
            ];
        }

        $upcoming = $this->upcomingSchedule();
        if ($upcoming !== null) {
            $now = $this->current()->weeklyMinutes();
            $then = $upcoming->shifts()->weeklyMinutes();
            $days = (int) round(CarbonImmutable::parse($this->today()->toDateString())->diffInDays(CarbonImmutable::parse($upcoming->effectiveFrom()->toDateString())));

            return [
                'kind' => 'upcoming',
                'title' => __($l.'upcoming', ['date' => $this->day($upcoming->effectiveFrom())]),
                'when' => trans_choice($l.'in_days', max(1, $days), ['count' => max(1, $days)]),
                'before' => $this->current()->blocks === [] || $now === $then ? null : ProposalRules::hours($now),
                'after' => ProposalRules::hours($then),
            ];
        }

        $last = $this->history()[0] ?? null;
        if ($last !== null && $last->status === ShiftProposalStatus::Rejected && $last->decided_at?->greaterThan(CarbonImmutable::now()->subDays(self::REJECTED_SHOWS_DAYS))) {
            return ['kind' => 'rejected', 'title' => __($l.'rejected.title'), 'text' => __($l.'rejected.text'), 'reason' => $last->decision_note];
        }

        return null;
    }

    /**
     * The agreed week, one card: the week in force and, when one is approved to
     * start later, that one too (marked against today's); with no hours today,
     * only the later week and a quiet line about the days before it.
     *
     * @return array{tabs: list<array{key: string, tab: string, hours: string, rows: list<array<string, mixed>>}>, quiet: ?string}
     */
    public function weeks(): array
    {
        $l = 'scheduling::teacher.availability.';
        $current = $this->current();
        $upcoming = $this->upcomingSchedule();
        $hours = fn (WeeklyShifts $w) => __($l.'hours', ['h' => ProposalRules::hours($w->weeklyMinutes())]);
        $now = ['key' => 'now', 'tab' => __($l.'now'), 'hours' => $hours($current), 'rows' => $this->rows($current)];

        if ($upcoming === null) {
            return ['tabs' => $current->blocks === [] ? [] : [$now], 'quiet' => null];
        }
        $short = $upcoming->effectiveFrom()->locale(app()->getLocale())->translatedFormat('j F');
        if ($current->blocks === []) {
            return [
                'tabs' => [['key' => 'upcoming', 'tab' => __($l.'from_day', ['date' => $short]), 'hours' => $hours($upcoming->shifts()), 'rows' => $this->rows($upcoming->shifts())]],
                'quiet' => __($l.'no_hours_before', ['date' => $short]),
            ];
        }

        return ['tabs' => [
            $now,
            ['key' => 'upcoming', 'tab' => __($l.'from_day', ['date' => $short]), 'hours' => $hours($upcoming->shifts()), 'rows' => $this->rows($upcoming->shifts(), $current)],
        ], 'quiet' => null];
    }

    /** Under the week, while no proposal is open: what a new one starts from. */
    public function builtOn(): ?string
    {
        $upcoming = $this->upcomingSchedule();

        return $upcoming === null ? null : __('scheduling::teacher.availability.built_on', [
            'date' => $upcoming->effectiveFrom()->locale(app()->getLocale())->translatedFormat('j F'),
        ]);
    }

    /**
     * The last three decided proposals, newest first, as the teacher API gives them.
     *
     * @return list<ShiftProposal>
     */
    public function history(): array
    {
        return ShiftProposal::query()
            ->where('teacher_id', (string) auth()->id())
            ->whereIn('status', [ShiftProposalStatus::Approved->value, ShiftProposalStatus::Superseded->value, ShiftProposalStatus::Rejected->value])
            ->orderByDesc('decided_at')->orderByDesc('updated_at')
            ->limit(3)
            ->get()->all();
    }

    /** A decision's moment on the viewer's clock. */
    public function decidedAt(ShiftProposal $p): ?string
    {
        return $p->decided_at?->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M · H:i'));
    }

    public function day(CarbonImmutable $date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat('l j F');
    }

    /**
     * A week as rows: each day's spans written out and placed on a line from
     * the morning to midnight; with a week before, each day marked (unchanged,
     * new, changed, removed) and the time taken out written struck through.
     *
     * @return list<array{day: int, label: string, times: list<string>, removed: list<string>, spans: list<array{kind: string, style: string}>, mark: string}>
     */
    private function rows(WeeklyShifts $week, ?WeeklyShifts $before = null): array
    {
        $all = [...$week->blocks, ...($before?->blocks ?? [])];
        $start = min([self::MORNING, ...array_map(fn (ShiftBlock $b) => intdiv($b->startMin, 60) * 60, $all)]);
        $span = ShiftBlock::DAY_MIN - $start;
        $label = fn (int $from, int $to) => Clock::wall(ShiftBlock::clock($from)).' – '.Clock::wall(ShiftBlock::clock($to));
        $days = app()->getLocale() === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7];
        $slots = function (array $blocks): array {
            $on = array_fill(0, ShiftBlock::DAY_MIN / 5, false);
            foreach ($blocks as $b) {
                for ($m = $b->startMin; $m < min($b->endMin(), ShiftBlock::DAY_MIN); $m += 5) {
                    $on[intdiv($m, 5)] = true;
                }
            }

            return $on;
        };
        $key = fn (array $blocks) => implode(',', array_map(fn (ShiftBlock $b) => $b->startMin.'-'.$b->endMin(), $blocks));

        $rows = [];
        foreach ($days as $day) {
            $now = $this->sorted($week->on($day));
            $was = $before === null ? null : $this->sorted($before->on($day));
            $parts = [];
            if ($was === null) {
                $parts = array_map(fn (ShiftBlock $b) => ['kind' => 'kept', 'from' => $b->startMin, 'to' => min($b->endMin(), ShiftBlock::DAY_MIN)], $now);
            } else {
                $then = $slots($was);
                foreach ($slots($now) as $i => $on) {
                    $kind = $on && $then[$i] ? 'kept' : ($on ? 'added' : ($then[$i] ? 'removed' : null));
                    if ($kind === null) {
                        continue;
                    }
                    $last = array_key_last($parts);
                    if ($last !== null && $parts[$last]['kind'] === $kind && $parts[$last]['to'] === $i * 5) {
                        $parts[$last]['to'] += 5;
                    } else {
                        $parts[] = ['kind' => $kind, 'from' => $i * 5, 'to' => $i * 5 + 5];
                    }
                }
            }
            $mark = match (true) {
                $was === null || $key($now) === $key($was) => 'same',
                $was === [] => 'added',
                $now === [] => 'removed',
                default => 'changed',
            };

            $rows[] = [
                'day' => $day,
                'label' => __('scheduling::shifts.days.'.$day),
                'times' => array_map(fn (ShiftBlock $b) => $label($b->startMin, $b->endMin()), $now),
                'removed' => array_values(array_map(fn (array $p) => $label($p['from'], $p['to']), array_filter($parts, fn (array $p) => $p['kind'] === 'removed'))),
                'spans' => array_map(function (array $p) use ($start, $span) {
                    $from = max($p['from'], $start);

                    return ['kind' => $p['kind'], 'style' => sprintf('inset-inline-start: %s%%; width: %s%%', round(($from - $start) / $span * 100, 2), round(max($p['to'] - $from, 0) / $span * 100, 2))];
                }, $parts),
                'mark' => $mark,
            ];
        }

        return $rows;
    }

    /** @param list<ShiftBlock> $blocks @return list<ShiftBlock> */
    private function sorted(array $blocks): array
    {
        usort($blocks, fn (ShiftBlock $a, ShiftBlock $b) => $a->startMin <=> $b->startMin);

        return $blocks;
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
        $this->editing = false;
        unset($this->proposal, $this->hero);
        Notification::make()->success()->title($message)->send();
    }

    private function latest(): WeeklyShifts
    {
        return app(ShiftProposals::class)->latestShifts((string) auth()->id(), $this->today(), $this->timezone());
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
