<?php

namespace Modules\Support\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Support\Filament\Pages\TicketPage;
use Modules\Support\Filament\Pages\Tickets;

/** Support in the academy back office: the tickets and each ticket's page. */
final class SupportPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'support';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Tickets::class, TicketPage::class]);
    }

    public function boot(Panel $panel): void {}
}
