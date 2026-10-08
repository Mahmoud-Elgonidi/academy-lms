<?php

namespace Modules\Support\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Support in the family area (/my): "Help", writing to the academy about the learner on screen. */
final class SupportFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'support-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyHelp::class]);
    }

    public function boot(Panel $panel): void {}
}
