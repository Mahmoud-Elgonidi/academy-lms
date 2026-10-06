<?php

namespace Modules\Scheduling\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Pages\ReviewShiftProposal;
use Modules\Scheduling\Filament\Pages\ShiftProposals;

/** Scheduling's screens in the academy back office. */
final class SchedulingPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'scheduling';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([LiveBoard::class, FindTeacher::class, ShiftProposals::class, ReviewShiftProposal::class]);
    }

    public function boot(Panel $panel): void {}
}
