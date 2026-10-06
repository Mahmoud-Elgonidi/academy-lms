<?php

namespace Modules\Crm\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The CRM in the teacher area: their trial lessons, and assessing them. */
final class CrmTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'crm-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyTrials::class]);
    }

    public function boot(Panel $panel): void {}
}
