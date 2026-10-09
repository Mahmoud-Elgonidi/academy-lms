<?php

namespace Modules\Platform\Filament\Console;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Platform\Filament\Console\Pages\Academies;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\AuditLog;
use Modules\Platform\Filament\Console\Pages\Health;
use Modules\Platform\Filament\Console\Pages\Plans;

/** The platform team's console: the academies, their use, plans and status, the plans on sale, the audit log across them, and the stack's health. */
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
        $panel->pages([Academies::class, AcademyOverview::class, Plans::class, AuditLog::class, Health::class]);
    }

    public function boot(Panel $panel): void {}
}
