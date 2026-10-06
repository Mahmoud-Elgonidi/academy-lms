<?php

namespace Modules\Communication\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Communication\Jobs\SendDelivery;
use Modules\Communication\Models\Delivery;

/** From the message log: try a failed delivery again, now. */
final class ResendDelivery
{
    public function handle(Delivery $delivery): void
    {
        if ($delivery->status !== 'failed') {
            throw ValidationException::withMessages(['delivery' => __('communication::communication.errors.not_failed')]);
        }
        $delivery->update(['status' => 'queued', 'error' => null]);
        SendDelivery::dispatch($delivery->id);
    }
}
