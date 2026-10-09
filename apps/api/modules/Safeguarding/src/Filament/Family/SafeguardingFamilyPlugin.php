<?php

namespace Modules\Safeguarding\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Safeguarding in the family area (/my): "Tell us", raising a concern about the learner on screen. */
final class SafeguardingFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'safeguarding-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([TellUs::class]);
    }

    public function boot(Panel $panel): void {}
}
