<?php

namespace Modules\Communication\Channels;

use InvalidArgumentException;
use Modules\Communication\Channels\Push\FcmPushGateway;
use Modules\Communication\Channels\Push\LogPushGateway;
use Modules\Communication\Channels\WhatsApp\LogWhatsAppGateway;
use Modules\Communication\Channels\WhatsApp\MetaCloudWhatsAppGateway;

/** Picks the adapter for each channel from config (docs/architecture/04, "Adapter + Manager"). */
final class ChannelManager
{
    public function get(string $channel): Channel
    {
        return match ($channel) {
            'email' => app(MailChannel::class),
            'whatsapp' => new WhatsAppChannel(config('communication.whatsapp.driver') === 'meta'
                ? new MetaCloudWhatsAppGateway(config('communication.whatsapp.meta'))
                : new LogWhatsAppGateway),
            'push' => new PushChannel(config('communication.push.driver') === 'fcm'
                ? new FcmPushGateway(config('communication.push.fcm'))
                : new LogPushGateway),
            default => throw new InvalidArgumentException("Unknown channel [{$channel}]."),
        };
    }
}
