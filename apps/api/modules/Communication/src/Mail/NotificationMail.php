<?php

namespace Modules\Communication\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Platform\Contracts\DTOs\Branding;

/** A notification by email, in the academy's name and colour and the recipient's language. */
final class NotificationMail extends Mailable
{
    public function __construct(
        public readonly string $heading,
        public readonly string $text,
        public readonly string $recipientName,
        public readonly string $lang,
        public readonly Branding $branding,
    ) {
        $this->locale($lang);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading, from: new Address(config('mail.from.address'), $this->branding->name($this->lang)));
    }

    public function content(): Content
    {
        return new Content(view: 'communication::mail.notification', with: [
            'academy' => $this->branding->name($this->lang),
            'logo' => $this->branding->logoUrl,
            'colour' => $this->branding->tokens['primary'] ?? '#0F766E',
            'rtl' => $this->lang === 'ar',
        ]);
    }
}
