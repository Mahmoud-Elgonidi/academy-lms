<?php

namespace Modules\Communication\Contracts\Events;

/** A delivery gave up after its retries; Support may follow up. */
final readonly class NotificationFailed
{
    public function __construct(
        public string $academyId,
        public string $notificationId,
        public string $deliveryId,
        public string $channel,
        public string $error,
    ) {}
}
