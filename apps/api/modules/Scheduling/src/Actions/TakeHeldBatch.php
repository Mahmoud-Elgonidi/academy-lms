<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\BookingHold;

/** A coordinator's live hold, in date order, or a validation error when it is gone or expired. */
final class TakeHeldBatch
{
    /** @return Collection<int, BookingHold> */
    public function handle(string $batchId, string $coordinatorId): Collection
    {
        $holds = BookingHold::query()->where('batch_id', $batchId)->where('created_by', $coordinatorId)->orderBy('starts_at')->get();
        if ($holds->isEmpty() || $holds->first()->expires_at->isPast()) {
            throw ValidationException::withMessages(['hold' => __('scheduling::find.errors.hold_expired')]);
        }

        return $holds;
    }
}
