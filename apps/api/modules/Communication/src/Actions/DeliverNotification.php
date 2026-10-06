<?php

namespace Modules\Communication\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Communication\Channels\ChannelManager;
use Modules\Communication\Models\Delivery;
use Modules\Communication\Queries\Recipients;
use Throwable;

/** Hands one delivery to its channel; a failure is recorded and thrown so the job retries. */
final class DeliverNotification
{
    public function __construct(
        private readonly ChannelManager $channels,
        private readonly Recipients $recipients,
    ) {}

    public function handle(string $deliveryId): void
    {
        $delivery = Delivery::query()->with('notification')->find($deliveryId);
        if ($delivery === null || ! in_array($delivery->status, ['queued', 'failed'], true)) {
            return;
        }
        $person = $this->recipients->one($delivery->notification->user_id);
        if ($person === null) {
            $delivery->update(['status' => 'skipped', 'error' => 'inactive']);

            return;
        }

        $delivery->increment('attempts');
        try {
            $sent = $this->channels->get($delivery->channel)->send($person, $delivery->notification);
        } catch (Throwable $e) {
            $delivery->update(['status' => 'queued', 'error' => Str::limit($e->getMessage(), 490)]);

            throw $e;
        }

        $delivery->update(['status' => 'sent', 'provider' => $sent->provider, 'provider_message_id' => $sent->messageId, 'sent_at' => CarbonImmutable::now(), 'error' => null]);
    }
}
