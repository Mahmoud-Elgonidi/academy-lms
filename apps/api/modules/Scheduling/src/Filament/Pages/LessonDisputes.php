<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Scheduling\Actions\Disputes\DecideDispute;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Domain\Disputes\DisputeRules;
use Modules\Scheduling\Domain\Models\SessionDispute;
use Modules\Scheduling\Live\BoardChanged;
use Modules\Scheduling\Queries\Disputes\DisputeList;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Disputes over lessons' outcomes (docs/architecture/01, section 8): the
 * ones waiting, oldest first, with what each side said and how long each
 * was in the classroom; deciding one says what the lesson was after all
 * (credit and pay follow) and answers whoever raised it. Decided ones stay
 * a month. Follows the board's signal.
 */
final class LessonDisputes extends Page
{
    protected string $view = 'scheduling::filament.disputes.list';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = -4;

    protected static ?string $slug = 'disputes';

    /** The dispute a notification pointed at: shown first, marked. */
    #[Url]
    public ?string $dispute = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewLiveBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::disputes.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DisputeList::waiting();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::disputes.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::disputes.page.subtitle', ['hours' => DisputeRules::HOURS]);
    }

    /** @return array{open: list<array<string, mixed>>, decided: list<array<string, mixed>>, channel: string} */
    #[Computed]
    public function groups(): array
    {
        $list = app(DisputeList::class);
        $tz = app(ViewerTimezone::class)->effective();
        $open = array_map(fn (object $r) => $this->card($r, $tz), $list->open());
        // The one a notification pointed at comes first.
        usort($open, fn (array $a, array $b) => ($b['id'] === $this->dispute) <=> ($a['id'] === $this->dispute));

        return [
            'open' => $open,
            'decided' => array_map(fn (object $r) => $this->card($r, $tz), $list->decided()),
            'channel' => BoardChanged::channel((string) app(TenantContext::class)->id()),
        ];
    }

    /** @return array<string, mixed> */
    private function card(object $r, string $tz): array
    {
        $l = 'scheduling::disputes.';
        $locale = app()->getLocale();
        $start = $r->starts_at->setTimezone($tz);
        $booked = (int) round($r->starts_at->diffInMinutes($r->ends_at));
        $presence = function (string $role) use ($r, $tz, $booked, $l): array {
            $first = $r->{$role.'_first'};
            $late = $first === null ? 0 : max(0, (int) floor($r->starts_at->diffInMinutes($first, false)));

            return [
                'label' => $first === null ? __($l.'page.never_came')
                    : __($l.'page.was_there', ['minutes' => $r->{$role.'_minutes'}, 'of' => $booked, 'at' => $first->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i'))])
                        .($late > 0 ? __($l.'page.late', ['late' => $late]) : ''),
                'full' => $r->{$role.'_minutes'} >= $booked - 2,
            ];
        };

        return [
            'id' => $r->id,
            'focused' => $r->id === $this->dispute,
            'subject' => $locale === 'ar' ? $r->subject_ar : $r->subject_en,
            'student' => $r->student,
            'teacher' => $r->teacher,
            'when' => $start->locale($locale)->translatedFormat(Clock::pattern('l j F · H:i')),
            'side' => $r->side,
            'sideLabel' => __($l.'sides.'.$r->side),
            'raisedBy' => $r->raised_by_name,
            'raisedAgo' => $r->created_at->locale($locale)->diffForHumans(),
            'reason' => __("{$l}reasons.{$r->side}.{$r->reason}"),
            'note' => $r->note,
            'before' => __($l.'outcomes.'.$r->outcome_before),
            'after' => $r->outcome_after ? __($l.'outcomes.'.$r->outcome_after) : null,
            'status' => $r->status,
            'statusLabel' => __($l.'statuses.'.$r->status),
            'answer' => $r->decision_note,
            'decidedBy' => $r->decided_by_name,
            'decidedAt' => $r->decided_at?->setTimezone($tz)->locale($locale)->translatedFormat(Clock::pattern('j F · H:i')),
            'short' => (int) ($r->credit_short ?? 0),
            'teacherPresence' => $presence('teacher'),
            'studentPresence' => $presence('student'),
            'calendarUrl' => AcademyCalendar::getUrl(['unit' => 'day', 'date' => $start->toDateString(), 'student' => $r->student_id]),
        ];
    }

    /** The academy's decision: what the lesson was after all, and the answer whoever disputed reads. */
    public function decideAction(): Action
    {
        $l = 'scheduling::disputes.decide.';

        return Action::make('decide')
            ->authorize(fn () => Gate::allows(Ability::ManageSessions->value))
            ->label(__($l.'action'))->icon(Heroicon::OutlinedScale)
            ->modalHeading(fn (array $arguments) => __($l.'heading', ['student' => $this->row($arguments)?->student ?? '']))
            ->modalDescription(fn (array $arguments) => ($r = $this->row($arguments)) === null ? null
                : __($l.'context', ['side' => __('scheduling::disputes.sides.'.$r->side), 'reason' => __("scheduling::disputes.reasons.{$r->side}.{$r->reason}"), 'before' => __('scheduling::disputes.outcomes.'.$r->outcome_before)]))
            ->modalWidth('lg')
            ->fillForm(fn (array $arguments) => ['outcome' => $this->row($arguments)?->outcome_before])
            ->schema([
                Radio::make('outcome')->label(__($l.'outcome'))->required()
                    ->options(collect(ResolveReview::OUTCOMES)->mapWithKeys(fn (string $o) => [$o => __('scheduling::board.resolve.outcomes.'.$o)])->all())
                    ->descriptions(collect(ResolveReview::OUTCOMES)->mapWithKeys(fn (string $o) => [$o => __($l.'effects.'.$o)])->all()),
                Textarea::make('answer')->label(__($l.'answer'))->helperText(__($l.'answer_hint'))->required()->rows(3)->maxLength(DisputeLesson::NOTE_MAX),
            ])
            ->modalSubmitActionLabel(__($l.'submit'))
            ->action(function (array $data, array $arguments) use ($l) {
                $dispute = SessionDispute::query()->findOrFail($arguments['id'] ?? null);
                $decided = app(DecideDispute::class)->handle($dispute, $data['outcome'], (string) $data['answer'], (string) auth()->id());
                $short = (int) (app(DisputeList::class)->find($decided->id)?->credit_short ?? 0);
                Notification::make()->success()->title(__($l.'done.'.$decided->status))
                    ->body($short > 0 ? __($l.'short', ['minutes' => $short]) : null)->send();
                unset($this->groups);
            });
    }

    /** @param  array<string, mixed>  $arguments */
    private function row(array $arguments): ?object
    {
        $id = $arguments['id'] ?? null;

        return is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id) ? app(DisputeList::class)->find($id) : null;
    }
}
