<?php

namespace Modules\Quran\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Quran\Filament\Pages\HifzOverview;
use Modules\Quran\Filament\Pages\HifzStudent;
use Modules\Quran\Filament\Pages\QuranCertificates;
use Modules\Quran\Filament\Pages\QuranExamResult;
use Modules\Quran\Filament\Pages\QuranExams;
use Modules\Quran\Filament\Pages\QuranHomePage;
use Modules\Quran\Filament\Pages\QuranProgramBuilder;
use Modules\Quran\Filament\Pages\QuranPrograms;
use Modules\Quran\Filament\Pages\QuranSettingsPage;
use Modules\Quran\Filament\Pages\QuranStudents;

/** The Quran in the academy back office (its own menu group, gone while the plan leaves the Quran out): its home, the students and their plans, the hifz board and a student's hifz, the programs and their builder, exams and their results, certificates, the settings. */
final class QuranPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'quran';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            QuranHomePage::class, QuranStudents::class, HifzOverview::class, HifzStudent::class, QuranPrograms::class, QuranProgramBuilder::class,
            QuranExams::class, QuranExamResult::class, QuranCertificates::class, QuranSettingsPage::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
