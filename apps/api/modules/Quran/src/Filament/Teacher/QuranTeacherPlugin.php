<?php

namespace Modules\Quran\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Quran\Filament\Pages\QuranExamResult;

/** The Quran in the teachers' area: their Quran lessons and «حصة قرآن», a student's plan and hifz map, the result of an exam they examine. */
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
        $panel->pages([QuranLessons::class, QuranLesson::class, StudentHifz::class, QuranExamResult::class]);
    }

    public function boot(Panel $panel): void {}
}
