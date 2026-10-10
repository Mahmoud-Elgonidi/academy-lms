<?php

namespace Modules\Messaging\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Messages in the teacher area (/teach). */
final class MessagingTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'messaging-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([TeacherMessages::class]);
    }

    public function boot(Panel $panel): void {}
}
