<?php

namespace Modules\Learning\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Learning in the family area: the journey on the roadmap, the week's challenges, the teacher's reports, homework to hand in. */
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
        $panel->pages([FamilyProgress::class, FamilyReports::class, FamilyHomework::class])->widgets([JourneyWidget::class, ChallengesWidget::class, LearningHomeWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
