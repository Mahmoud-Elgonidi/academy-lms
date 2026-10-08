<?php

namespace Modules\Classroom\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The classroom in the teacher area: today's lessons and the way in. */
final class ClassroomTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'classroom-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyLessonsToday::class, MyRecordings::class]);
    }

    public function boot(Panel $panel): void {}
}
