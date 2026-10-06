<?php

namespace Modules\Curriculum\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Curriculum\Filament\Programs\ProgramResource;

/** Curriculum's screens in the academy back office: programs and their content. */
final class CurriculumPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'curriculum';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([ProgramResource::class]);
    }

    public function boot(Panel $panel): void {}
}
