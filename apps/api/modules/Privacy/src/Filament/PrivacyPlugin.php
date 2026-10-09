<?php

namespace Modules\Privacy\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Privacy\Filament\Pages\DataRequests;

/** People's requests about their data in the back office, and the academy's details on its privacy notice and terms. */
final class PrivacyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'privacy';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([DataRequests::class]);
    }

    public function boot(Panel $panel): void {}
}
