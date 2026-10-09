<?php

namespace Modules\Privacy\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** "My data" in /my. */
final class PrivacyFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'privacy-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyData::class]);
    }

    public function boot(Panel $panel): void {}
}
