<?php

namespace Modules\Classroom\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\MeetingLink;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The teacher's lessons today, on their clock, with the way into each classroom once it opens. */
final class MyLessonsToday extends Page
{
    protected string $view = 'classroom::filament.today';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'today';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationLabel(): string
    {
        return __('classroom::classroom.today.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('classroom::classroom.today.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('classroom::classroom.today.subtitle', ['minutes' => config('classroom.opens_before_min')]);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function lessons(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $day = CarbonImmutable::now($tz)->startOfDay();
        $now = CarbonImmutable::now();
        $before = config('classroom.opens_before_min');
        $after = config('classroom.closes_after_min');

        return array_map(fn (LessonCard $l) => [
            'id' => $l->id,
            'time' => $l->startsAt->setTimezone($tz)->format('H:i').' – '.$l->endsAt->setTimezone($tz)->format('H:i'),
            'student' => $l->studentName,
            'subject' => $l->subject(),
            'status' => $l->status,
            'open' => $l->isOpen() && $now->greaterThanOrEqualTo($l->startsAt->subMinutes($before)) && $now->lessThanOrEqualTo($l->endsAt->addMinutes($after)),
            'opens' => $l->startsAt->subMinutes($before)->setTimezone($tz)->format('H:i'),
            'past' => $now->greaterThan($l->endsAt->addMinutes($after)) || ! $l->isOpen(),
            'url' => route('classroom.page', ['session' => $l->id]),
        ], app(AttendanceApi::class)->lessonsOf((string) auth()->id(), $day->utc(), $day->addDay()->utc()));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('link')
                ->label(__('classroom::classroom.today.link'))->icon(Heroicon::OutlinedLink)->color('gray')
                ->fillForm(fn () => ['url' => MeetingLink::query()->where('teacher_id', auth()->id())->value('url')])
                ->schema([
                    TextInput::make('url')->label(__('classroom::classroom.link.label'))->helperText(__('classroom::classroom.link.hint'))
                        ->url()->rule('url:https')->maxLength(500)->placeholder('https://meet.google.com/…'),
                ])
                ->action(function (array $data) {
                    app(SaveMeetingLink::class)->handle((string) auth()->id(), $data['url'] ?? null);
                    Notification::make()->success()->title(__('classroom::classroom.link.saved'))->send();
                }),
        ];
    }
}
