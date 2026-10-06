<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Filament\TimeOffForm;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\Scheduling\Queries\TimeOffCover;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Teachers' time off in the back office (docs/architecture/01, flow "ز"):
 * requests waiting for an answer, leave whose lessons still need cover, what
 * is coming, and the last month. The badge counts both kinds of waiting.
 */
final class TimeOffRequests extends Page
{
    protected string $view = 'scheduling::filament.time-off.list';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?int $navigationSort = -4;

    protected static ?string $slug = 'time-off';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageTimeOff->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::timeoff.admin.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TeacherTimeOff::query()->where('status', TimeOffStatus::Pending->value)->where('ends_at', '>', CarbonImmutable::now())->count() + TimeOffCover::uncovered();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return TimeOffCover::uncovered() > 0 ? 'danger' : 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::timeoff.admin.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::timeoff.admin.subtitle');
    }

    /** @return array{pending: list<array<string, mixed>>, cover: list<array<string, mixed>>, ahead: list<array<string, mixed>>, past: list<array<string, mixed>>} */
    #[Computed]
    public function groups(): array
    {
        $now = CarbonImmutable::now();
        $rows = TeacherTimeOff::query()
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'teacher_time_off.academy_id')->on('u.id', '=', 'teacher_time_off.teacher_id'))
            ->where(fn ($q) => $q->where('teacher_time_off.ends_at', '>', $now->subDays(30)))
            ->orderBy('teacher_time_off.starts_at')
            ->get(['teacher_time_off.*', 'u.name as teacher_name']);

        $groups = ['pending' => [], 'cover' => [], 'ahead' => [], 'past' => []];
        foreach ($rows as $t) {
            /** @var TeacherTimeOff $t */
            $over = $t->ends_at->lessThanOrEqualTo($now);
            $cover = $t->status === TimeOffStatus::Approved ? TimeOffView::summary($t) : null;
            $group = match (true) {
                $t->status === TimeOffStatus::Pending && ! $over => 'pending',
                $t->status === TimeOffStatus::Approved && ! $over && $cover['left'] > 0 => 'cover',
                $t->status === TimeOffStatus::Approved && ! $over => 'ahead',
                default => 'past',
            };
            $groups[$group][] = [
                'id' => $t->id,
                'teacher' => $t->getAttribute('teacher_name'),
                'period' => TimeOffView::period($t),
                'reason' => __('scheduling::timeoff.reasons.'.$t->reason),
                'asked' => $t->requested_by === $t->teacher_id
                    ? __('scheduling::timeoff.admin.asked', ['ago' => $t->created_at->locale(app()->getLocale())->diffForHumans()])
                    : __('scheduling::timeoff.review.requested_by_academy'),
                'status' => $t->status->value,
                'statusLabel' => __('scheduling::timeoff.statuses.'.$t->status->value),
                'cover' => $cover,
                'lessons' => $cover['total'] ?? Session::query()->where('teacher_id', $t->teacher_id)->where('status', 'scheduled')
                    ->where('starts_at', '<', $t->ends_at)->where('ends_at', '>', $t->starts_at)->count(),
                'url' => TimeOffReview::getUrl(['timeOff' => $t->id]),
            ];
        }
        $groups['past'] = array_reverse($groups['past']);

        return $groups;
    }

    protected function getHeaderActions(): array
    {
        $l = 'scheduling::timeoff.admin.';

        return [
            Action::make('record')->label(__($l.'record'))->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__($l.'record'))->modalWidth('lg')
                ->schema(TimeOffForm::fields(fn (Get $get) => $get('teacher_id'), pickTeacher: true))
                ->action(function (array $data) use ($l) {
                    [$first, $last, $from, $to] = TimeOffForm::args($data);
                    $timeOff = app(RequestTimeOff::class)->handle($data['teacher_id'], $first, $last, $from, $to, $data['reason'], $data['note'] ?? null, (string) auth()->id(), grant: true);
                    Notification::make()->success()->title(__($l.'recorded'))->send();
                    $this->redirect(TimeOffReview::getUrl(['timeOff' => $timeOff->id]), navigate: true);
                }),
        ];
    }
}
