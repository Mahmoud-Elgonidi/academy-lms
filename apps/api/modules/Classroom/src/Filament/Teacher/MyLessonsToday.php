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
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The teacher's lessons today, on their clock, with the way into each
 * classroom once it opens; a group lesson with its badge, roster count and
 * every student seated by name. Updates when a lesson changes or a classroom
 * opens, not on a timer.
 */
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
        return $this->today['lessons'];
    }

    /**
     * Today's lessons, and the next moment the clock alone changes the page
     * (a classroom opens or closes, midnight). Changes to the lessons
     * themselves arrive as a signal on the teacher's own channel.
     *
     * @return array{lessons: list<array<string, mixed>>, now: int, next: ?int, channel: string}
     */
    #[Computed]
    public function today(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $day = CarbonImmutable::now($tz)->startOfDay();
        $now = CarbonImmutable::now();
        $before = config('classroom.opens_before_min');
        $after = config('classroom.closes_after_min');
        $cards = app(AttendanceApi::class)->lessonsOf((string) auth()->id(), $day->utc(), $day->addDay()->utc());

        $moments = [$day->addDay()];
        foreach ($cards as $l) {
            if ($l->isOpen()) {
                array_push($moments, $l->startsAt->subMinutes($before), $l->endsAt->addMinutes($after));
            }
        }
        $next = collect($moments)->filter(fn (CarbonImmutable $m) => $m->greaterThan($now))->min();

        $clock = fn (CarbonImmutable $at) => $at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i'));

        return [
            'lessons' => array_map(fn (LessonCard $l) => [
                'id' => $l->id,
                'time' => $clock($l->startsAt).' – '.$clock($l->endsAt),
                'student' => $l->studentName,
                // A group lesson: everyone seated by name, and the seats taken of the group's.
                'group' => $l->isGroup() ? [
                    'names' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', array_column($l->students, 'name')),
                    'badge' => __('classroom::classroom.today.group', ['taken' => count($l->students), 'capacity' => $l->capacity ?? count($l->students)]),
                ] : null,
                'subject' => $l->subject(),
                'status' => $l->status,
                'open' => $l->isOpen() && $now->greaterThanOrEqualTo($l->startsAt->subMinutes($before)) && $now->lessThanOrEqualTo($l->endsAt->addMinutes($after)),
                'opens' => $clock($l->startsAt->subMinutes($before)),
                'past' => $now->greaterThan($l->endsAt->addMinutes($after)) || ! $l->isOpen(),
                'url' => route('classroom.page', ['session' => $l->id]),
            ], $cards),
            'now' => $now->getTimestampMs(),
            'next' => $next?->getTimestampMs(),
            'channel' => PersonalChannel::name((string) auth()->id()),
        ];
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
