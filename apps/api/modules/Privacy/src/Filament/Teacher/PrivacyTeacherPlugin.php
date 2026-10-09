<?php

namespace Modules\Privacy\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** "My data" in /teach. */
final class PrivacyTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'privacy-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyData::class]);
    }

    public function boot(Panel $panel): void {}
}
