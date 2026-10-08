<?php

namespace Modules\Classroom\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Classroom\Filament\Pages\ClassroomSettingsPage;
use Modules\Classroom\Filament\Pages\RecordingsPage;

/** The classroom in the academy back office: where lessons happen, and their recordings. */
final class ClassroomPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'classroom';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ClassroomSettingsPage::class, RecordingsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
