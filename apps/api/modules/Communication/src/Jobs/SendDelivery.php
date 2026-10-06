<?php

namespace Modules\Communication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Modules\Communication\Actions\DeliverNotification;
use Modules\Communication\Contracts\Events\NotificationFailed;
use Modules\Communication\Models\Delivery;
use Throwable;

/** One delivery on the notifications queue; the academy travels with the job (TenantContext). */
final class SendDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $deliveryId)
    {
        $this->onQueue('notifications');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return config('communication.retry_after_seconds', [60, 300, 900]);
    }

    public function handle(DeliverNotification $deliver): void
    {
        $deliver->handle($this->deliveryId);
    }

    public function failed(Throwable $e): void
    {
        $delivery = Delivery::query()->find($this->deliveryId);
        if ($delivery === null) {
            return;
        }
        $delivery->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 490)]);
        event(new NotificationFailed($delivery->academy_id, $delivery->notification_id, $delivery->id, $delivery->channel, (string) $delivery->error));
    }
}
