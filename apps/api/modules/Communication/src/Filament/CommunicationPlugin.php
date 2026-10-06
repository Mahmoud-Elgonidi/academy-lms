<?php

namespace Modules\Communication\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Communication\Filament\Pages\MessageLog;
use Modules\Communication\Filament\Pages\NotificationTemplates;

/** Communication in the academy back office: the message log and the templates. The bell is in every panel. */
final class CommunicationPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'communication';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MessageLog::class, NotificationTemplates::class]);
    }

    public function boot(Panel $panel): void {}
}
