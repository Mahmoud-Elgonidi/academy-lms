<?php

namespace Modules\Communication\Console;

use Illuminate\Console\Command;
use Modules\Communication\Actions\ReleaseHeldDeliveries;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every minute in each academy: messages held for quiet hours go out when the hours end. */
final class SweepCommunicationCommand extends Command
{
    protected $signature = 'communication:sweep';

    protected $description = 'Send WhatsApp and push messages held for quiet hours, in every academy';

    public function handle(EachTenant $each, ReleaseHeldDeliveries $release): int
    {
        $released = $each->sum(fn () => $release->handle());
        $this->info("{$released} held message(s) released.");

        return self::SUCCESS;
    }
}
