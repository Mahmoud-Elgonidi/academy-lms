<?php

namespace Modules\Classroom\Filament\Family;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Classroom\Filament\Concerns\PlaysRecordings;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Queries\RecordingViews;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Lesson recordings for the family (docs/architecture/01, section 9): the
 * consent of the learner on screen (a guardian agrees for a child, an adult
 * student for themself, and either can take it back), and that learner's
 * recordings to watch when the academy shares them. Children leave it to
 * their guardian.
 */
final class FamilyRecordings extends Page
{
    use PlaysRecordings;

    protected string $view = 'classroom::filament.family.recordings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?int $navigationSort = 75;

    protected static ?string $slug = 'recordings';

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->current((string) auth()->id()) !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess() && ClassroomSettings::current()->records() && self::stage() !== LearnerStage::Kids;
    }

    public static function getNavigationLabel(): string
    {
        return __('classroom::recordings.family.nav.'.self::stage()->value);
    }

    public function getTitle(): string|Htmlable
    {
        return __('classroom::recordings.family.title');
    }

    private static function stage(): LearnerStage
    {
        return app(FamilyView::class)->stage((string) auth()->id());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function screen(): array
    {
        $viewer = (string) auth()->id();
        $learner = app(FamilyView::class)->current($viewer);
        $settings = ClassroomSettings::current();
        $tz = app(ViewerTimezone::class)->effective();
        $views = app(RecordingViews::class);

        return [
            'channel' => PersonalChannel::name($viewer),
            'learner' => $learner?->name,
            'records' => $settings->records(),
            'shared' => $settings->recording_family,
            'consent' => $learner ? $views->consent($viewer, $learner->id, $tz) : null,
            'recordings' => $learner && $settings->recording_family ? array_map(fn (array $r) => $r + [
                'when' => $r['at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i'),
                'until' => $r['expires_at']?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F'),
            ], $views->recordings('student', $learner->id)) : [],
        ];
    }

    protected function forgetConsents(): void
    {
        unset($this->screen);
    }
}
