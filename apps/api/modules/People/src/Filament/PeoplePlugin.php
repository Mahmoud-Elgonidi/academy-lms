<?php

namespace Modules\People\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\People\Filament\Directory\Guardians\GuardianResource;
use Modules\People\Filament\Directory\Students\StudentResource;
use Modules\People\Filament\Directory\Teachers\TeacherResource;
use Modules\People\Filament\Pages\GuardianConsents;

/** People in the back office: teachers, students and guardians, and guardians' consent to children's accounts. */
final class PeoplePlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'people';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([TeacherResource::class, StudentResource::class, GuardianResource::class]);
        $panel->pages([GuardianConsents::class]);
    }

    public function boot(Panel $panel): void {}
}
