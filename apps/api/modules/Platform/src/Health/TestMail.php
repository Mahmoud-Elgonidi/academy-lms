<?php

namespace Modules\Platform\Health;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A test from the platform: the server's mail works and reaches the platform team (Arabic and English, one message). */
final class TestMail extends Mailable
{
    use Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform::health.mail.test_subject', ['app' => (string) config('app.name')], 'en'));
    }

    public function content(): Content
    {
        return new Content(text: 'platform::mail.test', with: ['mailer' => (string) config('mail.default'), 'host' => (string) config('app.url')]);
    }
}
