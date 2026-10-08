<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Plans\MeasureUsage;

final class MeasureUsageCommand extends Command
{
    protected $signature = 'platform:usage';

    protected $description = "Counts each academy's use of its plan (teachers, active students, video minutes, storage)";

    public function handle(MeasureUsage $measure): int
    {
        $this->info('Counted '.$measure->all().' academies.');

        return self::SUCCESS;
    }
}
