<?php

namespace Modules\Scheduling\Filament\Family;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\RefuseSubstitute;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\Scheduling\Queries\LessonList;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The learner's lessons: the next four weeks, and the last two. */
final class FamilyLessons extends Page
{
    protected string $view = 'scheduling::filament.family.lessons';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'lessons';

    public static function getNavigationLabel(): string
    {
        return __('scheduling::family.nav.'.app(FamilyView::class)->stage((string) auth()->id())->value);
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::family.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::family.subtitle', ['zone' => Timezones::city(app(ViewerTimezone::class)->effective())]);
    }

    /** @return array{upcoming: list<array<string, mixed>>, recent: list<array<string, mixed>>} */
    #[Computed]
    public function lessons(): array
    {
        $learner = app(FamilyView::class)->current((string) auth()->id());
        if ($learner === null) {
            return ['upcoming' => [], 'recent' => []];
        }
        $now = CarbonImmutable::now();
        $view = new FamilyLessonView($now, app(ViewerTimezone::class)->effective());
        $query = app(LessonList::class);
        $today = $now->setTimezone(app(ViewerTimezone::class)->effective())->startOfDay()->utc();

        return [
            'upcoming' => array_map($view->lesson(...), $query->forStudent($learner->id, $today, $now->addWeeks(4))),
            'recent' => array_map($view->lesson(...), $query->forStudent($learner->id, $now->subWeeks(2), $today, latestFirst: true)),
        ];
    }

    /**
     * Cancelling a lesson, under the academy's policy: what happens to the
     * lesson is said before it is confirmed (back to the balance, or counted).
     * Moving it to another time goes through the academy for now. A child
     * does not cancel on their own: their guardian does.
     */
    public function cancelAction(): Action
    {
        $l = 'scheduling::family.cancel.';

        return Action::make('cancel')
            ->label(__($l.'button'))->link()->color('danger')->size('sm')
            ->visible(fn () => app(FamilyView::class)->stage((string) auth()->id()) !== LearnerStage::Kids)
            ->modalHeading(__($l.'heading'))
            ->modalDescription(fn (array $arguments) => $this->outcome($this->ownLesson($arguments)))
            ->schema([
                Select::make('reason')->label(__($l.'reason'))->required()
                    ->options(collect(CancelSession::REASONS['student'])->mapWithKeys(fn (string $r) => [$r => __($l.'reasons.'.$r)])->all()),
                Textarea::make('note')->label(__($l.'note'))->rows(2)->maxLength(500),
            ])
            ->modalSubmitActionLabel(__($l.'confirm'))
            ->action(function (array $data, array $arguments) use ($l) {
                app(CancelSession::class)->handle($this->ownLesson($arguments), 'student', $data['reason'], $data['note'] ?? null, (string) auth()->id());
                unset($this->lessons);
                Notification::make()->success()->title(__($l.'done'))->send();
            });
    }

    /** Coming academy holidays (the next six weeks), said above the lessons. */
    #[Computed]
    public function holidays(): array
    {
        $now = CarbonImmutable::now();

        return AcademyHoliday::query()->where('ends_at', '>', $now)->where('starts_at', '<', $now->addWeeks(6))->orderBy('starts_at')->get()
            ->map(fn (AcademyHoliday $h) => ['name' => $h->name, 'period' => TimeOffView::holiday($h)])->all();
    }

    /**
     * Clock changes that move the learner's hour within two weeks (the DST
     * planner, as the family is told), said above the lessons on the
     * learner's clock; not where the academy kept their hour.
     *
     * @return list<string>
     */
    #[Computed]
    public function clockChanges(): array
    {
        $learner = app(FamilyView::class)->current((string) auth()->id());
        if ($learner === null) {
            return [];
        }
        $now = CarbonImmutable::now();
        $locale = app()->getLocale();
        $viewer = app(ViewerTimezone::class)->effective();
        $at = fn (int $weekday, string $time) => CarbonImmutable::parse('2026-01-05 '.substr($time, 0, 5))->addDays($weekday - 1)->locale($locale)->translatedFormat('l H:i');

        return ShiftNotice::query()->where('student_id', $learner->id)->where('decision', '!=', 'keep_student')
            ->where('changes_at', '>', $now)->where('changes_at', '<=', $now->addDays(SendDstNotices::EARLY_DAYS))
            ->orderBy('changes_at')->get()
            ->map(fn (ShiftNotice $n) => __('scheduling::dst.family.notice', [
                'date' => $n->changes_at->setTimezone($viewer)->locale($locale)->translatedFormat('l j F'),
                'before' => $at($n->before_weekday, $n->before_time),
                'after' => $at($n->after_weekday, $n->after_time),
                'zone' => Timezones::city($n->other_tz),
                'cause' => Timezones::city($n->cause_tz),
            ]))->all();
    }

    /**
     * Turning down the substitute for a teacher on leave (docs/architecture/01,
     * section 8): the lesson is cancelled as the academy's doing, and its
     * minutes come back as makeup credit to book with their own teacher.
     */
    public function refuseAction(): Action
    {
        $l = 'scheduling::timeoff.family.';

        return Action::make('refuse')
            ->label(__($l.'refuse'))->link()->color('warning')->size('sm')
            ->visible(fn () => app(FamilyView::class)->stage((string) auth()->id()) !== LearnerStage::Kids)
            ->modalHeading(__($l.'refuse_heading'))
            ->modalDescription(fn () => __($l.'refuse_text', ['days' => PolicyVersion::settingsInForce()->get('makeup_validity_days')]))
            ->modalSubmitActionLabel(__($l.'refuse_confirm'))
            ->requiresConfirmation()
            ->action(function (array $arguments) use ($l) {
                app(RefuseSubstitute::class)->handle($this->ownLesson($arguments), null, (string) auth()->id());
                unset($this->lessons);
                Notification::make()->success()->title(__($l.'refused'))->send();
            });
    }

    /** A lesson of the learner this person follows, still ahead and booked; anything else is refused. */
    private function ownLesson(array $arguments): Session
    {
        $session = Session::query()->with('participants')->find($arguments['session'] ?? null);
        $learner = app(FamilyView::class)->current((string) auth()->id());
        abort_unless($session !== null && $learner !== null
            && $session->participants->contains(fn ($p) => $p->role === ParticipantRole::Student && $p->user_id === $learner->id), 404);
        abort_unless($session->status === SessionStatus::Scheduled && $session->starts_at->isFuture(), 403);

        return $session;
    }

    /** The policy's answer in the family's words: no teacher pay, no scores. */
    private function outcome(Session $session): string
    {
        [$decision, $input, $version] = app(PolicyDecider::class)->decide($session, PolicyEvent::StudentCancel);
        $policy = $version->policy();
        $l = 'scheduling::family.cancel.outcomes.';

        return match (true) {
            $session->type === SessionType::Trial => __($l.'trial'),
            $session->type === SessionType::Makeup => __($l.'makeup'),
            $decision->credit === 'refund' => __($l.'refund', ['hours' => $policy->get('student_notice_hours')]),
            $input->noticeHours < $policy->get('student_notice_hours') => __($l.'late', ['hours' => $policy->get('student_notice_hours')]),
            default => __($l.'quota', ['quota' => $policy->get('quota_per_block')]),
        };
    }
}
