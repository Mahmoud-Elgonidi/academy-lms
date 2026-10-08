<?php

namespace Modules\Classroom\Filament\Teacher;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Classroom\Filament\Concerns\PlaysRecordings;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Queries\RecordingViews;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * A teacher's own consent to their lessons being recorded, and the
 * recordings of their lessons to watch (never to download) when the academy
 * shares them.
 */
final class MyRecordings extends Page
{
    use PlaysRecordings;

    protected string $view = 'classroom::filament.teacher.recordings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'recordings';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess() && ClassroomSettings::current()->records();
    }

    public static function getNavigationLabel(): string
    {
        return __('classroom::recordings.teacher.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('classroom::recordings.teacher.title');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function screen(): array
    {
        $viewer = (string) auth()->id();
        $settings = ClassroomSettings::current();
        $tz = app(ViewerTimezone::class)->effective();
        $views = app(RecordingViews::class);

        return [
            'channel' => PersonalChannel::name($viewer),
            'records' => $settings->records(),
            'shared' => $settings->recording_teacher,
            'consent' => $views->consent($viewer, $viewer, $tz),
            'recordings' => $settings->recording_teacher ? array_map(fn (array $r) => $r + [
                'when' => $r['at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i'),
                'until' => $r['expires_at']?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F'),
            ], $views->recordings('teacher', $viewer)) : [],
        ];
    }

    protected function forgetConsents(): void
    {
        unset($this->screen);
    }
}
