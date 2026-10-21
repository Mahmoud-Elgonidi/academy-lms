<?php

namespace Modules\People\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The teacher's own vetting documents, and their card on the academy's public page, in /teach. */
final class PeopleTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'people-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyDocuments::class, MyCard::class]);
    }

    public function boot(Panel $panel): void {}
}
