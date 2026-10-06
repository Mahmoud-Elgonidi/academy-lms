<?php

namespace Modules\Scheduling\Filament;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\SharedKernel\Time\Timezones;

/**
 * The time off form, shared by the teacher ("ask") and the back office
 * ("record"): whole days or some hours in one day, on the teacher's clock,
 * with the lessons booked in that time counted before it is sent.
 */
final class TimeOffForm
{
    /**
     * @param  \Closure(Get): ?string  $teacher  whose clock and lessons
     * @return list<mixed>
     */
    public static function fields(\Closure $teacher, bool $pickTeacher = false): array
    {
        $l = 'scheduling::timeoff.form.';
        $hours = fn (Get $get) => $get('kind') === 'hours';

        return array_values(array_filter([
            $pickTeacher ? Select::make('teacher_id')->label(__($l.'teacher'))->required()->searchable()->live()
                ->options(fn () => DB::table('users as u')
                    ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
                    ->where('r.role', 'teacher')->where('u.status', 'active')->orderBy('u.name')->pluck('u.name', 'u.id')) : null,
            ToggleButtons::make('kind')->label(__($l.'kind'))->inline()->required()->live()->default('days')
                ->options(__($l.'kinds')),
            DatePicker::make('first_day')->label(fn (Get $get) => __($hours($get) ? $l.'day' : $l.'first_day'))->required()->native(false)->live()
                ->minDate(fn (Get $get) => $pickTeacher ? null : self::today($teacher($get)))
                ->helperText(fn (Get $get) => $teacher($get) ? __($l.'zone', ['zone' => Timezones::city(app(RequestTimeOff::class)->timezone($teacher($get)))]) : null),
            DatePicker::make('last_day')->label(__($l.'last_day'))->native(false)->live()
                ->visible(fn (Get $get) => ! $hours($get))->required(fn (Get $get) => ! $hours($get))->afterOrEqual('first_day'),
            TimePicker::make('from')->label(__($l.'from'))->seconds(false)->format('H:i')->minutesStep(15)->live()
                ->visible($hours)->required($hours),
            TimePicker::make('to')->label(__($l.'to'))->seconds(false)->format('H:i')->minutesStep(15)->live()
                ->visible($hours)->required($hours)->after('from'),
            Select::make('reason')->label(__($l.'reason'))->required()->native(false)
                ->options(collect(TeacherTimeOff::REASONS)->mapWithKeys(fn (string $r) => [$r => __('scheduling::timeoff.reasons.'.$r)])->all()),
            Textarea::make('note')->label(__($l.'note'))->rows(2)->maxLength(500),
            Callout::make(__($l.'impact'))->info()
                ->description(fn (Get $get) => self::impact($teacher($get), $get('kind'), $get('first_day'), $get('last_day'), $get('from'), $get('to')))
                ->visible(fn (Get $get) => $teacher($get) !== null && filled($get('first_day'))),
        ]));
    }

    /** @return array{0: string, 1: string, 2: ?string, 3: ?string} the Action's days and hours from the form */
    public static function args(array $data): array
    {
        $first = CarbonImmutable::parse($data['first_day'])->toDateString();
        if (($data['kind'] ?? 'days') === 'hours') {
            return [$first, $first, substr((string) $data['from'], 0, 5), substr((string) $data['to'], 0, 5)];
        }

        return [$first, CarbonImmutable::parse($data['last_day'] ?? $data['first_day'])->toDateString(), null, null];
    }

    public static function impact(?string $teacherId, ?string $kind, ?string $first, ?string $last, ?string $from, ?string $to): string
    {
        $l = 'scheduling::timeoff.form.';
        if ($teacherId === null || blank($first) || ($kind === 'hours' && (blank($from) || blank($to)))) {
            return '';
        }
        [$first, $last, $from, $to] = self::args(['kind' => $kind, 'first_day' => $first, 'last_day' => $last, 'from' => $from, 'to' => $to]);
        if ($last < $first) {
            return '';
        }
        $request = app(RequestTimeOff::class);
        [$startsAt, $endsAt] = $request->bounds($request->timezone($teacherId), $first, $last, $from, $to);
        $count = Session::query()->where('teacher_id', $teacherId)->where('status', 'scheduled')
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt->max(CarbonImmutable::now()))->count();

        return $count === 0 ? __($l.'impact_none') : __($l.'impact_some', ['lessons' => trans_choice('scheduling::timeoff.lessons', $count, ['count' => $count])]);
    }

    private static function today(?string $teacherId): ?string
    {
        return $teacherId ? CarbonImmutable::now(app(RequestTimeOff::class)->timezone($teacherId))->toDateString() : null;
    }
}
