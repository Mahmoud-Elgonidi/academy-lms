<?php

namespace Modules\Communication\Channels;

use Illuminate\Support\Facades\Mail;
use Modules\Communication\Mail\NotificationMail;
use Modules\Communication\Models\Notification;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\TestAddresses;
use Throwable;

final class MailChannel implements Channel
{
    public function __construct(private readonly BrandingApi $branding) {}

    public function address(Recipient $recipient): ?string
    {
        return $recipient->email ?: null;
    }

    public function send(Recipient $recipient, Notification $notification): Sent
    {
        try {
            $sent = Mail::to($recipient->email, $recipient->name)->send(new NotificationMail(
                $notification->title, $notification->body, $recipient->name, $recipient->locale, $this->branding->current(),
            ));
        } catch (Throwable $e) {
            MailHealth::failed($e);
            throw $e;
        }

        // Not sent after all: an address on .test is never handed to a real mail server (TestAddresses).
        if ($sent === null && TestAddresses::skips($recipient->email)) {
            return new Sent((string) config('mail.default'), skipped: 'test_address');
        }

        return new Sent((string) config('mail.default'), $sent?->getMessageId());
    }
}
