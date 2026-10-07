<?php

namespace Modules\Import\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Import\Filament\Pages\ImportData;

/** Import in the academy back office: bringing teachers, families and schedules in from sheets. */
final class ImportPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'import';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ImportData::class]);
    }

    public function boot(Panel $panel): void {}
}
