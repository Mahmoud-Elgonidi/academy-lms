<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Actions\Shifts\ShiftProposals as Proposals;
use Modules\Scheduling\Domain\Enums\ShiftProposalStatus;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\ShiftProposal;
use Modules\Scheduling\Domain\Shifts\ProposalRules;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\ShiftBlocksField;
use Modules\Scheduling\Queries\ShiftImpactQuery;
use Modules\SharedKernel\Authorization\Ability;

/** Shift proposals waiting on the admins, waiting on teachers, and recent decisions. */
final class ShiftProposals extends Page
{
    protected string $view = 'scheduling::filament.shift-proposals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = -4;

    protected static ?string $slug = 'shifts/proposals';

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

    /** @return array{admin: list<array<string, mixed>>, teacher: list<array<string, mixed>>, done: list<array<string, mixed>>} */
    #[Computed]
    public function groups(): array
    {
        $replyHours = SchedulingSettings::current()->proposal_reply_hours;
        $proposals = ShiftProposal::query()
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'shift_proposals.academy_id')->on('u.id', '=', 'shift_proposals.teacher_id'))
            ->where(fn ($q) => $q->whereIn('shift_proposals.status', ['submitted', 'changes_requested', 'countered'])
                ->orWhere('shift_proposals.updated_at', '>=', CarbonImmutable::now()->subDays(30)))
            ->where('shift_proposals.status', '!=', 'draft')
            ->orderByDesc('shift_proposals.updated_at')
            ->select('shift_proposals.*', 'u.name as teacher_name')
            ->with('rules')
            ->get();

        $impact = app(ShiftImpactQuery::class);
        $rows = ['admin' => [], 'teacher' => [], 'done' => []];
        foreach ($proposals as $p) {
            $proposed = $p->shifts();
            $before = app(Proposals::class)->scheduleOn($p->teacher_id, $p->effective_from, $p->timezone);
            $open = $p->status->isOpen();
            $conflicts = $open ? $impact->conflicts($p->teacher_id, $proposed, $p->effective_from) : [];

            $rows[match ($p->status) {
                ShiftProposalStatus::Submitted => 'admin',
                ShiftProposalStatus::ChangesRequested, ShiftProposalStatus::Countered => 'teacher',
                default => 'done',
            }][] = [
                'id' => $p->id,
                'teacher' => $p->getAttribute('teacher_name'),
                'status' => $p->status->value,
                'status_label' => __('scheduling::shifts.statuses.'.$p->status->value),
                'sent' => ($p->submitted_at ?? $p->created_at)->locale(app()->getLocale())->diffForHumans(),
                'overdue' => $p->status === ShiftProposalStatus::Submitted && $p->submitted_at?->addHours($replyHours)->isPast(),
                'from' => $p->effective_from->locale(app()->getLocale())->translatedFormat('l j F'),
                'before' => ProposalRules::hours($before->weeklyMinutes()),
                'after' => ProposalRules::hours($proposed->weeklyMinutes()),
                'conflicts' => count($conflicts),
                'unresolved' => count(array_filter($conflicts, fn ($c) => ! isset($p->resolutions[$c['key']]))),
                'url' => ReviewShiftProposal::getUrl(['proposal' => $p->id]),
            ];
        }

        return $rows;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editTeacher')
                ->label(__('scheduling::review.edit_teacher'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->modalHeading(__('scheduling::review.edit_teacher'))
                ->modalDescription(__('scheduling::review.edit_teacher_hint'))
                ->schema([
                    Select::make('teacher_id')->label(__('scheduling::review.teacher'))
                        ->options(fn () => DB::table('users as u')
                            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
                            ->where('r.role', 'teacher')->orderBy('u.name')->pluck('u.name', 'u.id'))
                        ->searchable()->required()->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if ($state) {
                                $tz = app(Proposals::class)->teacherTimezone($state);
                                $set('blocks', ShiftBlocksField::fill(app(Proposals::class)->scheduleOn($state, CarbonImmutable::now($tz), $tz)));
                            }
                        }),
                    ShiftBlocksField::make(),
                    DatePicker::make('effective_from')->label(__('scheduling::review.effective_from'))->required()->native(false)
                        ->default(CarbonImmutable::now()->addDay()->toDateString()),
                    Textarea::make('note')->label(__('scheduling::review.note_to_teacher'))->rows(2),
                ])
                ->action(function (array $data) {
                    $tz = app(Proposals::class)->teacherTimezone($data['teacher_id']);
                    $proposal = app(CounterShiftProposal::class)->handle(
                        $data['teacher_id'],
                        WeeklyShifts::fromArray($data['blocks'] ?? [], $tz),
                        CarbonImmutable::parse($data['effective_from']),
                        $data['note'] ?? null,
                        (string) auth()->id(),
                    );
                    $this->redirect(ReviewShiftProposal::getUrl(['proposal' => $proposal->id]));
                }),
        ];
    }
}
