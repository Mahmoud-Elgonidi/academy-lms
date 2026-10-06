<?php

namespace Modules\Scheduling\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Scheduling in the family area: the lessons page, and the next lessons on the home page. */
final class SchedulingFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'scheduling-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyLessons::class])->widgets([NextLessonsWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
