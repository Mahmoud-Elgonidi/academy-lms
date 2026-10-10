<?php

namespace Modules\Messaging\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Messages in the family area (/my): the learner on screen with their teachers. */
final class MessagingFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'messaging-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyMessages::class]);
    }

    public function boot(Panel $panel): void {}
}
