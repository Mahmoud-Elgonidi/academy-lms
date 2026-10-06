<?php

namespace Modules\Classroom\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Classroom\Filament\Pages\ClassroomSettingsPage;

/** The classroom in the academy back office: where lessons happen. */
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
        $panel->pages([ClassroomSettingsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
