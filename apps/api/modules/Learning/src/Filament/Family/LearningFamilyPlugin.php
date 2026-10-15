<?php

namespace Modules\Learning\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Learning in the family area: the journey (a student's home, the journey page of guardians and adults), the roadmap, the teacher's reports, homework to hand in. */
final class LearningFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'learning-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyJourney::class, FamilyProgress::class, FamilyReports::class, FamilyHomework::class, FamilyHifz::class])->widgets([JourneyWidget::class, LearningHomeWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
