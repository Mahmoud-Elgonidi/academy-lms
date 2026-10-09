<?php

namespace Modules\Safeguarding\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Safeguarding\Filament\Pages\Safeguarding;

/** Safeguarding in the back office: the leads' concerns page. */
final class SafeguardingPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'safeguarding';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Safeguarding::class]);
    }

    public function boot(Panel $panel): void {}
}
