<?php

namespace Modules\Billing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Billing\Actions\AutoRenew\ChargeAutoRenewal;

/**
 * Charges one renewal invoice to the payer's saved card (J5b; the academy
 * travels with the job). Idempotent per invoice: the automatic charge's row
 * is claimed once under the payer's lock (ChargeAutoRenewal), and the job is
 * never retried by the queue (a charge whose answer never came is asked of
 * the gateway, never made again).
 */
final class ChargeRenewal implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /** Held while one is queued for the same charge. */
    public int $uniqueFor = 900;

    public function __construct(public readonly string $autoChargeId) {}

    public function uniqueId(): string
    {
        return $this->autoChargeId;
    }

    public function handle(ChargeAutoRenewal $charge): void
    {
        $charge->handle($this->autoChargeId);
    }
}
