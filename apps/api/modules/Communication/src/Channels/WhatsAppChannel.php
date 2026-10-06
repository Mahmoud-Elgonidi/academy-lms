<?php

namespace Modules\Communication\Channels;

use Modules\Communication\Channels\WhatsApp\WhatsAppGateway;
use Modules\Communication\Models\Notification;

final class WhatsAppChannel implements Channel
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    public function address(Recipient $recipient): ?string
    {
        return $recipient->phone ?: null;
    }

    public function send(Recipient $recipient, Notification $notification): Sent
    {
        return new Sent($this->gateway->name(), $this->gateway->send((string) $recipient->phone, $notification->title, $notification->body, $recipient->locale));
    }
}
