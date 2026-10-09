<?php

namespace Modules\Platform\Health;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the platform team: what broke, what is well again, and where to look. In Arabic and English, one message. */
final class HealthAlert extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, array{status: string, value: string, hint: ?string}>  $failing
     * @param  list<string>  $recovered
     */
    public function __construct(public readonly array $failing, public readonly array $recovered, public readonly bool $reminder) {}

    public function envelope(): Envelope
    {
        $app = (string) config('app.name');
        $subject = $this->failing === []
            ? __('platform::health.mail.subject_well', ['app' => $app], 'en')
            : __('platform::health.mail.subject_fail', ['app' => $app, 'count' => count($this->failing)], 'en');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(text: 'platform::mail.health-alert', with: ['url' => rtrim((string) (config('platform.console_url') ?: config('app.url').'/console'), '/').'/health']);
    }
}
