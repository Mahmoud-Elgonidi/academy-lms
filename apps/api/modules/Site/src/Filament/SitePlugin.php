<?php

namespace Modules\Site\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Site\Filament\Pages\SiteBuilder;
use Modules\Site\Filament\Pages\SiteRequests;
use Modules\Site\Filament\Pages\SiteSettings;

/** The academy's public page in the back office: its builder (what it says and shows), its settings, and the requests it brings. Teachers write their cards in /teach (People). */
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
        $panel->pages([SiteBuilder::class, SiteSettings::class, SiteRequests::class]);
    }

    public function boot(Panel $panel): void {}
}
