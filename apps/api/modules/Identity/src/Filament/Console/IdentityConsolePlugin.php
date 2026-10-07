<?php

namespace Modules\Identity\Filament\Console;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Identity\Filament\Console\Pages\NewAcademy;
use Modules\Identity\Filament\Console\Pages\SupportSignIn;

/** Identity's part of the platform console: a new academy with its owner, and signing in as someone for support. */
final class IdentityConsolePlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'identity-console';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([NewAcademy::class, SupportSignIn::class]);
    }

    public function boot(Panel $panel): void {}
}
