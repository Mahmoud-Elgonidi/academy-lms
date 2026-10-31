<?php

namespace Modules\Quran\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The Quran in the teachers' area: a student's hifz map, from the student's page. */
final class QuranTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'quran-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([StudentHifz::class]);
    }

    public function boot(Panel $panel): void {}
}
