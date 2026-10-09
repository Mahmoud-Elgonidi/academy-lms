<?php

namespace Modules\Privacy\Listeners;

use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Privacy\Contracts\Events\AccountErased;
use Modules\Privacy\Contracts\Events\DataExportReady;
use Modules\Privacy\Contracts\Events\ErasureApproved;
use Modules\Privacy\Contracts\Events\ErasureRefused;
use Modules\Privacy\Contracts\Events\ErasureRequested;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Privacy's messages (wording in lang/{ar,en}/notifications.php): whoever
 * asked hears that their copy is ready, that an erasure was approved (with
 * the day, while their addresses still exist) or refused (with the
 * academy's reason), and a guardian that their child's account is gone;
 * the academy hears of a new request. Nobody hears of their own doing.
 */
final class SendPrivacyNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'privacy::notifications.audience.';
        $person = ['person' => 'مريم'];
        foreach ([
            ['export_ready', 'lessons', $a.'requester', ['push', 'email'], $person + ['until' => 'الخميس 16 أكتوبر']],
            ['erasure_requested', 'staff', $a.'handlers', ['push', 'email'], $person + ['due' => 'السبت 8 نوفمبر']],
            ['erasure_approved', 'lessons', $a.'requester', ['push', 'email'], $person + ['at' => 'الجمعة 10 أكتوبر · 18:00']],
            ['erasure_refused', 'lessons', $a.'requester', ['push', 'email'], $person + ['answer' => 'عليه فاتورة لسه متدفعتش']],
            ['account_erased', 'lessons', $a.'guardian', ['push', 'email'], []],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "privacy.{$name}", category: $category,
                title: "privacy::notifications.{$name}.title", body: "privacy::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function exportReady(DataExportReady $event): void
    {
        $request = DataRequest::query()->find($event->requestId);
        if ($request === null || $request->expires_at === null) {
            return;
        }
        $this->notifications->send(new Message('privacy.export_ready', [$request->requested_by],
            ['person' => Subjects::name($request->subject_id), 'until' => $request->expires_at],
            ['screen' => 'my_data', 'id' => $request->id], "privacy-export:{$request->id}"));
    }

    public function erasureRequested(ErasureRequested $event): void
    {
        $request = DataRequest::query()->find($event->requestId);
        if ($request === null) {
            return;
        }
        $handlers = array_values(array_diff($this->identity->usersWithAbility(Ability::HandleDataRequests), [$request->requested_by]));
        $this->notifications->send(new Message('privacy.erasure_requested', $handlers,
            ['person' => Subjects::name($request->subject_id), 'due' => $request->due_at],
            ['screen' => 'data_request', 'id' => $request->id], "privacy-erasure:{$request->id}"));
    }

    public function erasureApproved(ErasureApproved $event): void
    {
        $request = DataRequest::query()->find($event->requestId);
        if ($request === null || $request->erase_after === null) {
            return;
        }
        $this->notifications->send(new Message('privacy.erasure_approved', array_values(array_unique([$request->requested_by, $request->subject_id])),
            ['person' => Subjects::name($request->subject_id), 'at' => $request->erase_after],
            ['screen' => 'my_data', 'id' => $request->id], "privacy-approved:{$request->id}"));
    }

    public function erasureRefused(ErasureRefused $event): void
    {
        $request = DataRequest::query()->find($event->requestId);
        if ($request === null) {
            return;
        }
        $this->notifications->send(new Message('privacy.erasure_refused', [$request->requested_by],
            ['person' => Subjects::name($request->subject_id), 'answer' => (string) $request->answer],
            ['screen' => 'my_data', 'id' => $request->id], "privacy-refused:{$request->id}"));
    }

    public function accountErased(AccountErased $event): void
    {
        $request = DataRequest::query()->find($event->requestId);
        // The person themselves can no longer be reached; a guardian who asked for a child hears it is done.
        if ($request === null || $request->requested_by === $request->subject_id || Subjects::isErased($request->requested_by)) {
            return;
        }
        // No name: it is gone with the account.
        $this->notifications->send(new Message('privacy.account_erased', [$request->requested_by], [],
            ['screen' => 'my_data', 'id' => $request->id], "privacy-erased:{$request->id}"));
    }
}
