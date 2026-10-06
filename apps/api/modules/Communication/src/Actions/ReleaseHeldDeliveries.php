<?php

namespace Modules\Communication\Actions;

use Carbon\CarbonImmutable;
use Modules\Communication\Jobs\SendDelivery;
use Modules\Communication\Models\Delivery;

/** Sweeper: messages held for quiet hours go out once the hours are over. */
final class ReleaseHeldDeliveries
{
    public function handle(?CarbonImmutable $now = null): int
    {
        $due = Delivery::query()->where('status', 'held')->where('not_before', '<=', $now ?? CarbonImmutable::now())->get();
        foreach ($due as $delivery) {
            $delivery->update(['status' => 'queued']);
            SendDelivery::dispatch($delivery->id);
        }

        return $due->count();
    }
}
