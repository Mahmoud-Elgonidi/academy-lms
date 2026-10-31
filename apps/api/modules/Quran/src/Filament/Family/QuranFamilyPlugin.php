<?php

namespace Modules\Quran\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The Quran in the family area: the learner's hifz map, its daily review and the mistakes notebook. */
final class QuranFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'quran-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyHifz::class]);
    }

    public function boot(Panel $panel): void {}
}
