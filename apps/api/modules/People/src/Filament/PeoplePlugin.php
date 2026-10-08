<?php

namespace Modules\People\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\People\Filament\Pages\GuardianConsents;

/** People in the back office: guardians' consent to children's accounts. */
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
        $panel->pages([GuardianConsents::class]);
    }

    public function boot(Panel $panel): void {}
}
