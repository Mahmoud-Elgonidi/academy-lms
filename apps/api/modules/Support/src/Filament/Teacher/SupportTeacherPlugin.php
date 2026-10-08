<?php

namespace Modules\Support\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Support in the teacher's area: writing to the academy and following the answers. */
final class SupportTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'support-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MySupport::class]);
    }

    public function boot(Panel $panel): void {}
}
