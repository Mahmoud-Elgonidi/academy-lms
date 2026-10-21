<?php

namespace Modules\Site\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Site\Filament\Pages\SiteRequests;
use Modules\Site\Filament\Pages\SiteSettings;

/** The academy's public page in the back office: what it says and shows, and the requests it brings. Teachers write their cards in /teach (People). */
final class SitePlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'site';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([SiteSettings::class, SiteRequests::class]);
    }

    public function boot(Panel $panel): void {}
}
