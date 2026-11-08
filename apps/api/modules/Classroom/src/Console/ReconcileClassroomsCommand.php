<?php

namespace Modules\Classroom\Console;

use Illuminate\Console\Command;
use Modules\Classroom\Actions\MeterRooms;
use Modules\Classroom\Actions\ReconcileRooms;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every minute in each academy: who is really in each open lesson's room, from the provider; and the closed rooms' minutes not counted yet. */
final class ReconcileClassroomsCommand extends Command
{
    protected $signature = 'classroom:reconcile';

    protected $description = 'Check the video provider for who is in each open lesson room and fix attendance';

    public function handle(EachTenant $each, ReconcileRooms $reconcile, MeterRooms $meter): int
    {
        $fixed = $each->sum(fn () => $reconcile->handle());
        $metered = $each->sum(fn () => $meter->handle());
        $this->info("{$fixed} attendance change(s) from the rooms, {$metered} room(s) counted into the video minutes.");

        return self::SUCCESS;
    }
}
