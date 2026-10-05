<?php

namespace Modules\Identity\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Identity\Filament\Pages\MySettings;

/** Identity's screens in the academy back office. */
final class IdentityPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'identity';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->profile(MySettings::class, isSimple: false)
            ->defaultAvatarProvider(InitialsAvatar::class);
    }

    public function boot(Panel $panel): void {}
}
