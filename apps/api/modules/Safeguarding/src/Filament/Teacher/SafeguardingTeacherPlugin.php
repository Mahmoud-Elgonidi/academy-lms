<?php

namespace Modules\Safeguarding\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Safeguarding in the teachers' area (/teach): "Raise a concern". */
final class SafeguardingTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'safeguarding-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([RaiseConcern::class]);
    }

    public function boot(Panel $panel): void {}
}
