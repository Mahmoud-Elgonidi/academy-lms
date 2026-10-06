<?php

namespace Modules\Scheduling\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Scheduling in the teacher area: the week ahead, proposing their hours, and asking for time off. */
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
        $panel->pages([MyWeek::class, MyAvailability::class, MyTimeOff::class]);
    }

    public function boot(Panel $panel): void {}
}
