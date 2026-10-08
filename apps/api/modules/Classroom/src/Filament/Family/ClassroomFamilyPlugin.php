<?php

namespace Modules\Classroom\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The classroom in the family area: consent to lesson recordings, and the learner's recordings to watch. */
final class ClassroomFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'classroom-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyRecordings::class]);
    }

    public function boot(Panel $panel): void {}
}
