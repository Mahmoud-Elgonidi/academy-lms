<?php

namespace Modules\Site\Listeners;

use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Support\RequestAccess;

/**
 * A new request from the academy's page, told to who answers it (wording in
 * lang/{ar,en}/notifications.php): the kind only, never who asked or their
 * number, which the requests screen shows to those allowed.
 */
final class SendSiteNotifications
{
    public function __construct(private readonly NotificationsApi $notifications, private readonly RequestAccess $access) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $notifications->define(new TemplateDefinition(
            key: 'site.request_new', category: 'staff',
            title: 'site::notifications.request_new.title', body: 'site::notifications.request_new.body',
            audience: 'site::notifications.audience.staff', channels: ['push', 'email'], sample: ['kind' => 'حصة تجريبية'],
        ));
    }

    public function requested(JoinRequested $event): void
    {
        $this->notifications->send(new Message('site.request_new', $this->access->recipients($event->kind), [
            'kind' => new Translatable('site::notifications.kinds.'.$event->kind),
        ], ['screen' => 'site_request', 'id' => $event->requestId], "site-request:{$event->requestId}"));
    }
}
