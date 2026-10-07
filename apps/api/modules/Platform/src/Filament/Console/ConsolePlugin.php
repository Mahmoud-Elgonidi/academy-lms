<?php

namespace Modules\Platform\Filament\Console;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Platform\Filament\Console\Pages\Academies;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\AuditLog;

/** The platform team's console: the academies, their use and status, and the audit log across them. */
final class ConsolePlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'platform-console';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Academies::class, AcademyOverview::class, AuditLog::class]);
    }

    public function boot(Panel $panel): void {}
}
