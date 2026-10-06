<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Actions\Payments\SweepCheckouts;
use Modules\SharedKernel\Tenancy\EachTenant;

/** Every five minutes in each academy: online checkouts past their time, asked of the gateway once more (lost webhooks). */
final class SweepBillingCommand extends Command
{
    protected $signature = 'billing:sweep';

    protected $description = 'Settle or expire online checkouts past their time in every academy';

    public function handle(EachTenant $each, SweepCheckouts $checkouts): int
    {
        $changed = $each->sum(fn () => $checkouts->handle());
        $this->info("{$changed} checkout(s) settled or expired.");

        return self::SUCCESS;
    }
}
