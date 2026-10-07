<?php

namespace Modules\Communication\Channels;

use Modules\Communication\Channels\Push\PushGateway;
use Modules\Communication\Models\Notification;
use Modules\Communication\Models\PushDevice;
use Modules\SharedKernel\Tenancy\TenantContext;

final class PushChannel implements Channel
{
    public function __construct(private readonly PushGateway $gateway, private readonly TenantContext $tenant) {}

    public function address(Recipient $recipient): ?string
    {
        $count = PushDevice::query()->where('user_id', $recipient->id)->count();

        return $count > 0 ? trans_choice('communication::communication.devices', $count, ['n' => $count]) : null;
    }

    public function send(Recipient $recipient, Notification $notification): Sent
    {
        $tokens = PushDevice::query()->where('user_id', $recipient->id)->pluck('token')->all();
        // Whose message it is (one phone may hold accounts in several academies): the app opens that account first.
        $result = $this->gateway->send($tokens, $notification->title, $notification->body,
            ['notification_id' => $notification->id, 'academy' => $this->tenant->require()->slug, 'user' => $recipient->id] + $notification->action);

        if ($result->deadTokens !== []) {
            PushDevice::query()->whereIn('token', $result->deadTokens)->delete();
        }

        return new Sent($this->gateway->name(), implode(',', array_slice($result->messageIds, 0, 3)) ?: null);
    }
}
