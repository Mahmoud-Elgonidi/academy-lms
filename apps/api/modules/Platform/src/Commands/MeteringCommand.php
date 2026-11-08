<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Metering\MeteringSweep;

final class MeteringCommand extends Command
{
    protected $signature = 'platform:metering';

    protected $description = "Brings each academy's usage ledger up to date: the month's allowance, ended lots, unsettled holds and the alerts";

    public function handle(MeteringSweep $sweep): int
    {
        $this->info('Metered '.$sweep->all().' academies.');

        return self::SUCCESS;
    }
}
