<?php

namespace Modules\Site\Listeners;

use Modules\Communication\Contracts\DTOs\ContactMessage;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;
use Modules\Site\Support\PickLinks;
use Modules\Site\Support\RequestAccess;

/**
 * A new request from the academy's page, told to who answers it (wording in
 * lang/{ar,en}/notifications.php): the kind only, never who asked or their
 * number, which the requests screen shows to those allowed. Whoever asked
 * for a free trial hears back at once by WhatsApp and email (they have no
 * account yet): the academy answers within its hours, and, while families
 * book their own, their link to pick a time. A family that booked its trial
 * already hears Scheduling's message (the time, the class link, change or
 * cancel), not this one.
 */
final class SendSiteNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly RequestAccess $access,
        private readonly PickLinks $picks,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $notifications->define(new TemplateDefinition(
            key: 'site.request_new', category: 'staff',
            title: 'site::notifications.request_new.title', body: 'site::notifications.request_new.body',
            audience: 'site::notifications.audience.staff', channels: ['push', 'email'], sample: ['kind' => 'حصة تجريبية'],
        ));
        $notifications->define(new TemplateDefinition(
            key: 'site.request_received', category: 'lessons',
            title: 'site::notifications.request_received.title', body: 'site::notifications.request_received.body',
            audience: 'site::notifications.audience.requester', channels: ['whatsapp', 'email'],
            sample: ['name' => 'مروة', 'hours' => '4', 'next' => 'وإن شئتم فاختاروا موعد الحصة بأنفسكم من هنا: https://demo.lms.test/ar/trial/…'],
        ));
    }

    /** The visitor who asked for a free trial hears back at once (unless they booked it already). */
    public function reply(JoinRequested $event): void
    {
        $request = $event->kind === 'trial' ? JoinRequest::query()->find($event->requestId) : null;
        if ($request === null || $request->session_id !== null) {
            return;
        }
        $settings = SiteSettings::current();
        $next = $settings->familyBooks() && $request->subject_id !== null
            ? new Translatable('site::notifications.request_received.pick', ['link' => $this->picks->url($request)])
            : new Translatable('site::notifications.request_received.wait');
        $this->notifications->sendToContact(new ContactMessage(
            template: 'site.request_received',
            name: $request->name,
            phone: $request->phone,
            email: $request->email,
            locale: $request->locale,
            timezone: $request->timezone ?? app(TenantContext::class)->require()->defaultTimezone,
            data: ['name' => $request->name, 'hours' => (string) $settings->response_hours, 'next' => $next],
        ));
    }

    public function requested(JoinRequested $event): void
    {
        $this->notifications->send(new Message('site.request_new', $this->access->recipients($event->kind), [
            'kind' => new Translatable('site::notifications.kinds.'.$event->kind),
        ], ['screen' => 'site_request', 'id' => $event->requestId], "site-request:{$event->requestId}"));
    }
}
