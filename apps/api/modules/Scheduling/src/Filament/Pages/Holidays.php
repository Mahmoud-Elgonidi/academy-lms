<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\Holidays\DeclareHoliday;
use Modules\Scheduling\Actions\Holidays\RemoveHoliday;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Timezones;
use UnitEnum;

/**
 * The academy's holidays (docs/architecture/02, rule 15): days on the
 * academy's clock whose lessons are cancelled with their minutes back and
 * the credit's validity extended. What a new holiday cancels is said before
 * it is added.
 */
final class Holidays extends Page
{
    protected string $view = 'scheduling::filament.time-off.holidays';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 84;

    protected static ?string $slug = 'settings/holidays';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageHolidays->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::timeoff.holidays.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::timeoff.holidays.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::timeoff.holidays.subtitle', ['zone' => Timezones::city(app(DeclareHoliday::class)->timezone())]);
    }

    /** @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>} */
    #[Computed]
    public function holidays(): array
    {
        $now = CarbonImmutable::now();
        $rows = AcademyHoliday::query()->where('ends_at', '>', $now->subDays(120))->orderBy('starts_at')->get();
        $map = fn (AcademyHoliday $h) => [
            'id' => $h->id,
            'name' => $h->name,
            'period' => TimeOffView::holiday($h),
            'days' => trans_choice('scheduling::timeoff.period.count', $h->days(), ['count' => $h->days()]),
            'cancelled' => $h->cancelled_count,
            'removable' => $h->starts_at->greaterThan($now),
        ];

        return [
            'upcoming' => $rows->filter(fn (AcademyHoliday $h) => $h->ends_at->greaterThan($now))->map($map)->values()->all(),
            'past' => $rows->filter(fn (AcademyHoliday $h) => $h->ends_at->lessThanOrEqualTo($now))->reverse()->map($map)->values()->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $l = 'scheduling::timeoff.holidays.';

        return [
            Action::make('add')->label(__($l.'add'))->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__($l.'add'))->modalWidth('lg')->modalSubmitActionLabel(__($l.'confirm'))
                ->schema([
                    TextInput::make('name')->label(__($l.'name'))->helperText(__($l.'name_hint'))->required()->maxLength(120),
                    DatePicker::make('first_day')->label(__($l.'first_day'))->required()->native(false)->live()
                        ->minDate(CarbonImmutable::now(app(DeclareHoliday::class)->timezone())->toDateString()),
                    DatePicker::make('last_day')->label(__($l.'last_day'))->required()->native(false)->live()->afterOrEqual('first_day'),
                    Callout::make(__($l.'impact'))->warning()
                        ->description(fn (Get $get) => self::impact($get('first_day'), $get('last_day')))
                        ->visible(fn (Get $get) => filled($get('first_day')) && filled($get('last_day'))),
                ])
                ->action(function (array $data) use ($l) {
                    $holiday = app(DeclareHoliday::class)->handle($data['name'], self::date($data['first_day']), self::date($data['last_day']), (string) auth()->id());
                    unset($this->holidays);
                    Notification::make()->success()->title(__($l.'added', ['lessons' => trans_choice('scheduling::timeoff.lessons', $holiday->cancelled_count, ['count' => $holiday->cancelled_count])]))->send();
                }),
        ];
    }

    public function removeAction(): Action
    {
        $l = 'scheduling::timeoff.holidays.';

        return Action::make('remove')->label(__($l.'remove'))->link()->color('danger')->size('sm')
            ->requiresConfirmation()->modalDescription(__($l.'remove_confirm'))
            ->action(function (array $arguments) use ($l) {
                app(RemoveHoliday::class)->handle(AcademyHoliday::query()->findOrFail($arguments['id'] ?? null));
                unset($this->holidays);
                Notification::make()->success()->title(__($l.'removed'))->send();
            });
    }

    public static function impact(?string $first, ?string $last): string
    {
        $l = 'scheduling::timeoff.holidays.';
        if (blank($first) || blank($last) || self::date($last) < self::date($first)) {
            return '';
        }
        $lessons = app(DeclareHoliday::class)->affected(self::date($first), self::date($last));
        if ($lessons->isEmpty()) {
            return __($l.'impact_none');
        }
        $students = DB::table('session_participants')->whereIn('session_id', $lessons->pluck('id'))->where('role', 'student')->distinct()->count('user_id');
        $days = (int) CarbonImmutable::parse($first)->diffInDays(CarbonImmutable::parse($last)) + 1;

        return __($l.'impact_some', [
            'lessons' => trans_choice('scheduling::timeoff.lessons', $lessons->count(), ['count' => $lessons->count()]),
            'students' => trans_choice($l.'students', $students, ['count' => $students]),
            'days' => trans_choice('scheduling::timeoff.period.count', $days, ['count' => $days]),
        ]);
    }

    private static function date(string $value): string
    {
        return CarbonImmutable::parse($value)->toDateString();
    }
}
