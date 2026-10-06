<?php

namespace Modules\Learning\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The teacher's screens in the interim teacher area (/teach), until the mobile app covers them. */
final class LearningTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'learning-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyReports::class, WriteReport::class, HomeworkToMark::class]);
    }

    public function boot(Panel $panel): void {}
}
