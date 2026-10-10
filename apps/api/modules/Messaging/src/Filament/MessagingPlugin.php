<?php

namespace Modules\Messaging\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Messaging\Filament\Pages\Conversations;

/** Messaging in the back office: reading teachers' and families' conversations, pausing one, and the academy's switch. */
final class MessagingPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'messaging';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Conversations::class]);
    }

    public function boot(Panel $panel): void {}
}
