<?php

namespace Modules\Communication\Channels;

use Modules\Communication\Models\Notification;

/** An outside channel (docs/architecture/04, "Adapter + Manager": MessagingChannel). */
interface Channel
{
    /** Where it would go ("sara@example.com", "+9665…", "2 devices"), or null when the person cannot be reached here. */
    public function address(Recipient $recipient): ?string;

    /** Sends, or throws so the delivery is retried. */
    public function send(Recipient $recipient, Notification $notification): Sent;
}
