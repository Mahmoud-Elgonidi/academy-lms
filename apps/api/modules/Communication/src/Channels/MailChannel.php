<?php

namespace Modules\Communication\Channels;

use Illuminate\Support\Facades\Mail;
use Modules\Communication\Mail\NotificationMail;
use Modules\Communication\Models\Notification;
use Modules\Platform\Contracts\BrandingApi;

final class MailChannel implements Channel
{
    public function __construct(private readonly BrandingApi $branding) {}

    public function address(Recipient $recipient): ?string
    {
        return $recipient->email ?: null;
    }

    public function send(Recipient $recipient, Notification $notification): Sent
    {
        $sent = Mail::to($recipient->email, $recipient->name)->send(new NotificationMail(
            $notification->title, $notification->body, $recipient->name, $recipient->locale, $this->branding->current(),
        ));

        return new Sent((string) config('mail.default'), $sent?->getMessageId());
    }
}
