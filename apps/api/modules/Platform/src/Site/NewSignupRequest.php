<?php

namespace Modules\Platform\Site;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the platform's owners: an academy asked to join from the page, who to talk to, and where to answer it. In Arabic and English, one message. */
final class NewSignupRequest extends Mailable
{
    use Queueable;

    /** @param  array<string, mixed>  $request  the request's fields, as asked */
    public function __construct(public readonly array $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform::site.mail.subject', ['academy' => (string) $this->request['academy_name']], 'en'));
    }

    public function content(): Content
    {
        $console = rtrim((string) (config('platform.console_url') ?: config('app.url').'/console'), '/');

        return new Content(text: 'platform::mail.signup-request', with: ['url' => $console.'/signup-requests']);
    }
}
