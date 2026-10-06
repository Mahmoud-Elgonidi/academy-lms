<?php

namespace Modules\Scheduling\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Scheduling in the teacher area: the week ahead, and proposing their hours. */
final class SchedulingTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'scheduling-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyWeek::class, MyAvailability::class]);
    }

    public function boot(Panel $panel): void {}
}
