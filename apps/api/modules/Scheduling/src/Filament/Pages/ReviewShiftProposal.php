<?php

namespace Modules\Scheduling\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CommentOnShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\ResolveShiftConflict;
use Modules\Scheduling\Actions\Shifts\ReviewShiftProposal as Review;
use Modules\Scheduling\Actions\Shifts\ShiftProposals;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Models\ShiftProposalComment;
use Modules\Scheduling\Domain\Shifts\ProposalRules;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\LiveBoardView;
use Modules\Scheduling\Filament\Pages\ShiftProposals as ShiftProposalsPage;
use Modules\Scheduling\Filament\ShiftBlocksField;
use Modules\Scheduling\Queries\ShiftImpactQuery;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Review one shift proposal (docs/architecture/02, section 2, "شاشة
 * المراجعة"): the current week against the proposed one, hours and notice,
 * booked students left outside the new shifts with a resolution for each,
 * the conversation, and the decision.
 */
final class ReviewShiftProposal extends Page
{
    protected string $view = 'scheduling::filament.review-shift-proposal';

    protected static ?string $slug = 'shifts/proposals/{proposal}';

    protected static bool $shouldRegisterNavigation = false;

    public string $proposalId;

    public string $reply = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ReviewShifts->value);
    }

    public function mount(string $proposal): void
    {
        $this->proposalId = ShiftProposal::query()->findOrFail($proposal)->id;
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::review.title', ['teacher' => $this->review()['teacher']]);
    }

    public function getBreadcrumbs(): array
    {
        return [ShiftProposalsPage::getUrl() => __('scheduling::review.nav'), __('scheduling::review.review')];
    }

    public function proposal(): ShiftProposal
    {
        return ShiftProposal::query()->with(['rules', 'comments'])->findOrFail($this->proposalId);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function review(): array
    {
        $p = $this->proposal();
        $helper = app(ShiftProposals::class);
        $teacher = DB::table('users')->where('id', $p->teacher_id)->first(['name', 'timezone']);
        $subjects = DB::table('teacher_subjects as ts')->join('subjects as s', 's.id', '=', 'ts.subject_id')
            ->where('ts.teacher_id', $p->teacher_id)->pluck(app()->getLocale() === 'ar' ? 's.name_ar' : 's.name_en')->implode('، ');

        $proposed = $p->shifts();
        $current = $helper->scheduleOn($p->teacher_id, $p->effective_from, $p->timezone);
        $today = CarbonImmutable::now($p->timezone)->startOfDay();
        $earliest = $helper->rules()->earliestStart($proposed, $current, $today);
        [, $removed] = $proposed->addedAndRemovedMinutes($current);

        $conflicts = array_map(function (array $c) use ($p) {
            $first = $c['first'];

            return $c + [
                'resolution' => $p->resolutions[$c['key']]['kind'] ?? null,
                'subject' => app()->getLocale() === 'ar' ? $c['subject_ar'] : $c['subject_en'],
                'teacher_label' => __('scheduling::shifts.days.'.$c['weekday']).' '.$c['start'].' '.LiveBoardView::city($p->timezone),
                'student_label' => $first->setTimezone($c['student_tz'])->format('H:i').' '.LiveBoardView::city($c['student_tz']),
                'range' => $first->locale(app()->getLocale())->translatedFormat('j M').' → '.$c['last']->locale(app()->getLocale())->translatedFormat('j M'),
                // Moving the student to another teacher happens on their account; the conflict then goes away.
                'move_url' => $c['enrollment_id'] ? route('filament.academy.resources.enrollments.view', ['record' => $c['enrollment_id'], 'reassign' => $p->effective_from->toDateString()]) : null,
            ];
        }, $p->status->isOpen() ? app(ShiftImpactQuery::class)->conflicts($p->teacher_id, $proposed, $p->effective_from) : []);

        $spans = $proposed->compareTo($current);
        [$from, $to] = $this->hourRange($proposed, $current);

        return [
            'teacher' => $teacher->name ?? '—',
            'subjects' => $subjects,
            'tz' => $p->timezone,
            'city' => LiveBoardView::city($p->timezone),
            'status' => $p->status,
            'author' => $p->rules->where('revision', $p->revision)->first()?->author_role ?? $p->author_role,
            'revision' => $p->revision,
            'sent' => ($p->submitted_at ?? $p->created_at)?->setTimezone(app(ViewerTimezone::class)->effective())
                ->locale(app()->getLocale())->translatedFormat('l j F · H:i'),
            'from' => $p->effective_from->locale(app()->getLocale())->translatedFormat('l j F'),
            'hours_before' => ProposalRules::hours($current->weeklyMinutes()),
            'hours_after' => ProposalRules::hours($proposed->weeklyMinutes()),
            'delta' => $proposed->weeklyMinutes() - $current->weeklyMinutes(),
            'reduces' => $removed > 0,
            'notice_days' => $helper->settings()->reduction_notice_days,
            'notice_ok' => ! $p->effective_from->lessThan($earliest),
            'lead_days' => (int) $today->diffInDays($p->effective_from, false),
            'days' => app()->getLocale() === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7],
            'spans' => $spans,
            'day_hours' => collect(range(1, 7))->mapWithKeys(fn (int $d) => [$d => [
                ProposalRules::hours(array_sum(array_map(fn ($b) => $b->durationMin, $current->on($d)))),
                ProposalRules::hours(array_sum(array_map(fn ($b) => $b->durationMin, $proposed->on($d)))),
            ]])->all(),
            'range' => [$from, $to],
            'conflicts' => $conflicts,
            'unresolved' => count(array_filter($conflicts, fn ($c) => $c['resolution'] === null)),
            'comments' => $p->comments->map(fn (ShiftProposalComment $c) => [
                'role' => $c->author_role,
                'author' => $c->author_role === 'teacher' ? ($teacher->name ?? '') : ($c->author_id ? DB::table('users')->where('id', $c->author_id)->value('name') : __('scheduling::shifts.roles.system')),
                'body' => $c->body,
                'ago' => $c->created_at->locale(app()->getLocale())->diffForHumans(),
            ])->all(),
        ];
    }

    public function resolve(string $key, ?string $kind): void
    {
        $this->authorizeReview();
        app(ResolveShiftConflict::class)->handle($this->proposal(), $key, $kind, (string) auth()->id());
        unset($this->review);
    }

    public function sendReply(): void
    {
        $this->authorizeReview();
        app(CommentOnShiftProposal::class)->handle($this->proposal(), (string) auth()->id(), 'admin', $this->reply);
        $this->reply = '';
        unset($this->review);
    }

    protected function getHeaderActions(): array
    {
        // Closures, not values: header actions are built once, and resolving a
        // conflict or a decision changes what is allowed without a reload.
        $p = fn () => $this->proposal();
        $submitted = fn () => $this->review['status'] === ShiftProposalStatus::Submitted;
        $countered = fn () => $this->review['status'] === ShiftProposalStatus::Countered;
        $unresolved = fn () => $this->review['unresolved'];

        return [
            Action::make('reject')->label(__('scheduling::review.actions.reject'))->color('danger')->outlined()
                ->visible($submitted)
                ->schema([Textarea::make('reason')->label(__('scheduling::review.reason'))->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(fn () => app(Review::class)->reject($this->proposal(), $data['reason'], (string) auth()->id()), 'rejected')),

            Action::make('counter')->label(__('scheduling::review.actions.counter'))->color('gray')->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn () => $this->review['status']->isOpen())
                ->modalHeading(__('scheduling::review.actions.counter'))
                ->modalDescription(fn () => __('scheduling::review.counter_hint', ['city' => $this->review['city']]))
                ->fillForm(fn () => ['blocks' => ShiftBlocksField::fill($this->proposal()->shifts()), 'effective_from' => $this->proposal()->effective_from->toDateString()])
                ->schema([
                    ShiftBlocksField::make(),
                    DatePicker::make('effective_from')->label(__('scheduling::review.effective_from'))->required()->native(false),
                    Textarea::make('note')->label(__('scheduling::review.note_to_teacher'))->rows(2),
                ])
                ->action(fn (array $data) => $this->decide(fn () => app(CounterShiftProposal::class)->handle(
                    $p()->teacher_id, WeeklyShifts::fromArray($data['blocks'] ?? [], $p()->timezone), CarbonImmutable::parse($data['effective_from']), $data['note'] ?? null, (string) auth()->id(),
                ), 'countered')),

            Action::make('requestChanges')->label(__('scheduling::review.actions.request_changes'))->color('gray')
                ->visible($submitted)
                ->schema([Textarea::make('note')->label(__('scheduling::review.what_to_change'))->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(fn () => app(Review::class)->requestChanges($this->proposal(), $data['note'], (string) auth()->id()), 'changes_requested')),

            Action::make('override')->label(__('scheduling::review.actions.override'))->color('warning')->outlined()
                ->visible(fn () => ($submitted() && $unresolved() > 0) || $countered())
                ->modalDescription(__('scheduling::review.override_hint'))
                ->schema([Textarea::make('reason')->label(__('scheduling::review.reason'))->required()->rows(3)])
                ->action(fn (array $data) => $this->decide(fn () => app(ApproveShiftProposal::class)->handle($this->proposal(), (string) auth()->id(), 'admin', $data['reason']), 'approved')),

            Action::make('approve')->label(__('scheduling::review.actions.approve'))->icon(Heroicon::OutlinedCheck)
                ->visible($submitted)
                ->disabled(fn () => $unresolved() > 0)
                ->tooltip(fn () => $unresolved() > 0 ? __('scheduling::review.resolve_first', ['left' => $unresolved(), 'total' => count($this->review['conflicts'])]) : null)
                ->requiresConfirmation()
                ->modalDescription(fn () => __('scheduling::review.approve_hint', ['date' => $this->review['from'], 'teacher' => $this->review['teacher']]))
                ->action(fn () => $this->decide(fn () => app(ApproveShiftProposal::class)->handle($this->proposal(), (string) auth()->id(), 'admin'), 'approved')),
        ];
    }

    private function decide(callable $action, string $outcome): void
    {
        $this->authorizeReview();
        $action();
        unset($this->review);
        Notification::make()->success()->title(__('scheduling::review.done.'.$outcome))->send();
        $this->redirect(self::getUrl(['proposal' => $this->proposalId]));
    }

    private function authorizeReview(): void
    {
        abort_unless(Gate::allows(Ability::ReviewShifts->value), 403);
    }

    /** Hours shown on the grid: at least 12:00–24:00, wider if the blocks need it. */
    private function hourRange(WeeklyShifts ...$weeks): array
    {
        $from = 12;
        $to = 24;
        foreach ($weeks as $week) {
            foreach ($week->blocks as $block) {
                $from = min($from, intdiv($block->startMin, 60));
                $to = max($to, (int) ceil($block->endMin() / 60));
            }
        }

        return [$from, min(24, $to)];
    }

    /** Position of a span on the grid, as percentages of the visible hours. */
    public function place(string $start, string $end): string
    {
        [$from, $to] = $this->review['range'];
        $range = ($to - $from) * 60;
        $s = ShiftBlock::minutes($start) - $from * 60;
        $e = ($end === '24:00' ? ShiftBlock::DAY_MIN : ShiftBlock::minutes($end)) - $from * 60;

        return sprintf('inset-inline-start: %.3f%%; width: %.3f%%', 100 * $s / $range, 100 * ($e - $s) / $range);
    }
}
