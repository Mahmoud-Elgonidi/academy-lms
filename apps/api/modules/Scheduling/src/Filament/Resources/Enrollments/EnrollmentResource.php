<?php

namespace Modules\Scheduling\Filament\Resources\Enrollments;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Queries\EnrollmentAccountQuery;
use Modules\Scheduling\Queries\Models\EnrolledStudent;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/** Enrolled students: what they study, when, and how much credit is left. */
final class EnrollmentResource extends Resource
{
    protected static ?string $model = EnrolledStudent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = -3;

    protected static ?string $slug = 'enrollments';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewEnrollments->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::enrollments.nav');
    }

    public static function getModelLabel(): string
    {
        return __('scheduling::enrollments.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('scheduling::enrollments.nav');
    }

    /** Enrollments with names, credit and plan, for the list and its search. */
    public static function withAccount(Builder $query): Builder
    {
        $on = fn (string $alias, string $key) => fn ($j) => $j->on("{$alias}.academy_id", '=', 'enrollments.academy_id')->on("{$alias}.id", '=', $key);

        return $query
            ->select('enrollments.*', 'st.name as student_name', 'st.timezone as student_tz', 'te.name as teacher_name',
                'su.name_ar as subject_ar', 'su.name_en as subject_en', 'ca.available_min', 'ca.reserved_min',
                'pl.session_minutes', 'pl.sessions_per_block')
            ->selectRaw("coalesce((select sum(cl.remaining_min) from credit_lots cl where cl.account_id = ca.id and cl.source = 'makeup'), 0) as makeup_min")
            ->join('users as st', $on('st', 'enrollments.student_id'))
            ->join('users as te', $on('te', 'enrollments.teacher_id'))
            ->join('subjects as su', $on('su', 'enrollments.subject_id'))
            ->leftJoin('credit_accounts as ca', $on('ca', 'enrollments.credit_account_id'))
            ->leftJoin('subscriptions as sb', fn ($j) => $j->on('sb.academy_id', '=', 'enrollments.academy_id')->on('sb.enrollment_id', '=', 'enrollments.id')->where('sb.status', '<>', 'cancelled'))
            ->leftJoin('plans as pl', fn ($j) => $j->on('pl.academy_id', '=', 'sb.academy_id')->on('pl.id', '=', 'sb.plan_id'));
    }

    public static function table(Table $table): Table
    {
        $tz = app(ViewerTimezone::class)->effective();
        $query = app(EnrollmentAccountQuery::class);
        $days = fn (EnrolledStudent $e) => collect($query->weeklyTimes($e->id, $e->getAttribute('student_tz') ?? $tz))
            ->map(fn ($t) => __('shared::time.days.'.$t['student_day']).' '.$t['student_time'])->implode(' · ');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::withAccount($query))
            ->defaultSort('enrollments.created_at', 'desc')
            ->recordUrl(fn (EnrolledStudent $record) => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('student_name')->label(__('scheduling::enrollments.fields.student'))->weight('semibold')
                    ->description(fn (EnrolledStudent $e) => (app()->getLocale() === 'ar' ? $e->getAttribute('subject_ar') : $e->getAttribute('subject_en')).' · '.$e->getAttribute('teacher_name'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($w) => $w->where('st.name', 'ilike', "%{$search}%")->orWhere('te.name', 'ilike', "%{$search}%"))),
                TextColumn::make('weekly')->label(__('scheduling::enrollments.fields.weekly'))->state($days)
                    ->description(fn (EnrolledStudent $e) => __('scheduling::enrollments.student_clock')),
                TextColumn::make('balance')->label(__('scheduling::enrollments.fields.balance'))
                    ->state(fn (EnrolledStudent $e) => self::balanceLine($e))
                    ->color(fn (EnrolledStudent $e) => $e->status === EnrollmentStatus::Active && self::sessionsLeft($e) <= 2 ? 'danger' : 'gray'),
                TextColumn::make('status')->label(__('scheduling::enrollments.fields.status'))->badge()
                    ->formatStateUsing(fn (EnrollmentStatus $state) => __('scheduling::enrollments.statuses.'.$state->value))
                    ->color(fn (EnrollmentStatus $state) => match ($state) {
                        EnrollmentStatus::Active => 'success', EnrollmentStatus::PendingPayment => 'warning', EnrollmentStatus::Paused => 'info', default => 'gray',
                    })
                    // A pause's last day, or the end coming.
                    ->description(fn (EnrolledStudent $e) => self::daysLine($e)),
                TextColumn::make('next')->label(__('scheduling::enrollments.fields.next'))
                    ->state(function (EnrolledStudent $e) use ($tz) {
                        $next = DB::table('sessions')->where('enrollment_id', $e->id)->where('status', 'scheduled')->where('starts_at', '>', CarbonImmutable::now())->orderBy('starts_at')->first(['starts_at', 'funding_status']);

                        return $next ? CarbonImmutable::parse($next->starts_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i') : '—';
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('scheduling::enrollments.fields.status'))
                    ->options(collect(EnrollmentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => __('scheduling::enrollments.statuses.'.$s->value)])->all())
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->where('enrollments.status', $data['value']) : $query),
            ])
            ->recordActions([ViewAction::make()->label(__('scheduling::enrollments.open'))])
            ->emptyStateHeading(__('scheduling::enrollments.empty.title'))
            ->emptyStateDescription(__('scheduling::enrollments.empty.hint'));
    }

    /** "until 3 Nov" for a paused course, "from 1 Nov" for one ending (or ended), on the academy's calendar. */
    public static function daysLine(EnrolledStudent $e): ?string
    {
        $day = fn ($d) => CarbonImmutable::parse($d)->locale(app()->getLocale())->translatedFormat('j M');
        $l = 'scheduling::courses.state.';

        return match (true) {
            $e->getAttribute('ends_on') !== null => __($l.'from', ['on' => $day($e->getAttribute('ends_on'))]),
            $e->status === EnrollmentStatus::Paused && $e->getAttribute('paused_until') !== null => __($l.'until', ['until' => $day($e->getAttribute('paused_until'))]),
            default => null,
        };
    }

    public static function sessionsLeft(EnrolledStudent $e): int
    {
        $minutes = (int) $e->getAttribute('session_minutes');

        $paid = (int) $e->getAttribute('available_min') + (int) $e->getAttribute('reserved_min') - (int) $e->getAttribute('makeup_min');

        return $minutes > 0 ? intdiv(max(0, $paid), $minutes) : 0;
    }

    public static function balanceLine(EnrolledStudent $e): string
    {
        if (! $e->getAttribute('session_minutes')) {
            return '—';
        }

        return __('scheduling::enrollments.balance', ['left' => self::sessionsLeft($e), 'block' => $e->getAttribute('sessions_per_block')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnrollments::route('/'),
            'view' => ViewEnrollment::route('/{record}'),
        ];
    }
}
