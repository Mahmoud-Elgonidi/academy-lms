<?php

namespace Modules\Announcements\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Announcements\Filament\Pages\Announcements;

/** Announcements in the back office: write one to families or teachers, and what was sent. Families and teachers read them in their own notifications. */
final class AnnouncementsPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'announcements';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Announcements::class]);
    }

    public function boot(Panel $panel): void {}
}
