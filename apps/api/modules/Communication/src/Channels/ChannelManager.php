<?php

namespace Modules\Communication\Channels;

use InvalidArgumentException;
use Modules\Communication\Channels\Push\FcmPushGateway;
use Modules\Communication\Channels\Push\LogPushGateway;
use Modules\Communication\Channels\Sms\LogSmsGateway;
use Modules\Communication\Channels\Sms\SmsGateway;
use Modules\Communication\Channels\Sms\TwilioSmsGateway;
use Modules\Communication\Channels\WhatsApp\LogWhatsAppGateway;
use Modules\Communication\Channels\WhatsApp\MetaCloudWhatsAppGateway;
use Modules\Communication\Channels\WhatsApp\WhatsAppGateway;

/** Picks the adapter for each channel from config (docs/architecture/04, "Adapter + Manager"). */
final class ChannelManager
{
    public function get(string $channel): Channel
    {
        return match ($channel) {
            'email' => app(MailChannel::class),
            'whatsapp' => new WhatsAppChannel($this->whatsapp()),
            'push' => new PushChannel(config('communication.push.driver') === 'fcm'
                ? new FcmPushGateway(config('communication.push.fcm'))
                : new LogPushGateway),
            default => throw new InvalidArgumentException("Unknown channel [{$channel}]."),
        };
    }

    public function whatsapp(): WhatsAppGateway
    {
        return config('communication.whatsapp.driver') === 'meta'
            ? new MetaCloudWhatsAppGateway(config('communication.whatsapp.meta'))
            : new LogWhatsAppGateway;
    }

    /** Texts are for sign-in codes only; notifications go by WhatsApp, push and email. */
    public function sms(): ?SmsGateway
    {
        return match (config('communication.sms.driver')) {
            'twilio' => new TwilioSmsGateway(config('communication.sms.twilio')),
            'log' => new LogSmsGateway,
            default => null,
        };
    }
}
