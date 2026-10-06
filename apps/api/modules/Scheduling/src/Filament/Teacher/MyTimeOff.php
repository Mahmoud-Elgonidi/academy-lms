<?php

namespace Modules\Scheduling\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Actions\TimeOff\WithdrawTimeOff;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Filament\TimeOffForm;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Timezones;

/** The teacher's time off: asking for it, following the answer, and seeing how their lessons are covered. */
final class MyTimeOff extends Page
{
    protected string $view = 'scheduling::filament.teacher.time-off';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'time-off';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::RequestOwnTimeOff->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::timeoff.mine.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::timeoff.mine.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::timeoff.mine.subtitle', ['zone' => Timezones::city(app(RequestTimeOff::class)->timezone((string) auth()->id()))]);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function requests(): array
    {
        return TeacherTimeOff::query()->where('teacher_id', (string) auth()->id())
            ->where(fn ($q) => $q->where('ends_at', '>', CarbonImmutable::now()->subDays(60))->orWhere('status', 'pending'))
            ->orderByDesc('starts_at')->limit(30)->get()
            ->map(fn (TeacherTimeOff $t) => [
                'id' => $t->id,
                'period' => TimeOffView::period($t),
                'days' => $t->all_day ? trans_choice('scheduling::timeoff.period.count', $t->days(), ['count' => $t->days()]) : null,
                'reason' => __('scheduling::timeoff.reasons.'.$t->reason),
                'note' => $t->note,
                'status' => $t->status->value,
                'statusLabel' => __('scheduling::timeoff.statuses.'.$t->status->value),
                'decision' => $t->decision_note,
                'byAcademy' => $t->requested_by !== $t->teacher_id,
                'over' => $t->ends_at->isPast(),
                'cover' => $t->status === TimeOffStatus::Approved ? TimeOffView::summary($t) : null,
                'withdrawable' => $t->status === TimeOffStatus::Pending && ! $t->ends_at->isPast(),
            ])->all();
    }

    /** @return list<array{name: string, period: string}> */
    #[Computed]
    public function holidays(): array
    {
        return AcademyHoliday::query()->where('ends_at', '>', CarbonImmutable::now())->orderBy('starts_at')->limit(5)->get()
            ->map(fn (AcademyHoliday $h) => ['name' => $h->name, 'period' => TimeOffView::holiday($h)])->all();
    }

    protected function getHeaderActions(): array
    {
        $l = 'scheduling::timeoff.mine.';

        return [
            Action::make('ask')->label(__($l.'ask'))->icon(Heroicon::OutlinedPaperAirplane)
                ->modalHeading(__($l.'ask'))->modalWidth('lg')
                ->schema(TimeOffForm::fields(fn () => (string) auth()->id()))
                ->modalSubmitActionLabel(__($l.'ask'))
                ->action(function (array $data) use ($l) {
                    [$first, $last, $from, $to] = TimeOffForm::args($data);
                    app(RequestTimeOff::class)->handle((string) auth()->id(), $first, $last, $from, $to, $data['reason'], $data['note'] ?? null, (string) auth()->id());
                    unset($this->requests);
                    Notification::make()->success()->title(__($l.'sent'))->send();
                }),
        ];
    }

    public function withdrawAction(): Action
    {
        $l = 'scheduling::timeoff.mine.';

        return Action::make('withdraw')->label(__($l.'withdraw'))->link()->color('danger')->size('sm')
            ->requiresConfirmation()->modalHeading(__($l.'withdraw_confirm'))
            ->action(function (array $arguments) use ($l) {
                $timeOff = TeacherTimeOff::query()->where('teacher_id', (string) auth()->id())->findOrFail($arguments['id'] ?? null);
                app(WithdrawTimeOff::class)->handle($timeOff, (string) auth()->id());
                unset($this->requests);
                Notification::make()->success()->title(__($l.'withdrawn'))->send();
            });
    }
}
