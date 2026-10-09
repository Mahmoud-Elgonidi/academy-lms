<?php

namespace Modules\People\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\People\Filament\Directory\Guardians\GuardianResource;
use Modules\People\Filament\Directory\Students\StudentResource;
use Modules\People\Filament\Directory\Teachers\TeacherResource;
use Modules\People\Filament\Pages\GuardianConsents;
use Modules\People\Filament\Pages\TeacherChecks;

/** People in the back office: teachers, students and guardians, guardians' consent to children's accounts, and teachers' vetting. */
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
        $panel->pages([GuardianConsents::class, TeacherChecks::class]);
    }

    public function boot(Panel $panel): void {}
}
