<?php

namespace Modules\Communication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Communication\Channels\ChannelManager;
use Modules\Communication\Channels\Recipient;
use Modules\Communication\Models\Notification;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * One message to someone with no account (SendToContact), on the
 * notifications queue: the written words to one address by one channel.
 * Retried like any delivery; nothing of it is kept once it went.
 */
final class SendContactDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $channel,
        public readonly string $address,
        public readonly string $name,
        public readonly string $locale,
        public readonly string $title,
        public readonly string $body,
    ) {
        $this->onQueue('notifications');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return config('communication.retry_after_seconds', [60, 300, 900]);
    }

    public function handle(ChannelManager $channels, TenantContext $tenants): void
    {
        $academy = $tenants->require();
        $recipient = new Recipient(
            id: '',
            name: $this->name,
            email: $this->channel === 'email' ? $this->address : null,
            phone: $this->channel === 'whatsapp' ? $this->address : null,
            locale: $this->locale,
            timezone: $academy->defaultTimezone,
        );
        $words = (new Notification)->forceFill(['title' => $this->title, 'body' => $this->body]);
        $channels->get($this->channel)->send($recipient, $words);
    }
}
