<?php

namespace Modules\Catalog\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Catalog\Filament\Subjects\SubjectResource;

/** Catalog in the back office: the subjects, their levels and categories. */
final class CatalogPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'catalog';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([SubjectResource::class]);
    }

    public function boot(Panel $panel): void {}
}
