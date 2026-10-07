<?php

namespace Modules\Communication\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Identity\Contracts\DTOs\OtpMessage;

/** A sign-in code by email, in the academy's name and colour (or the platform's) and the reader's language. */
final class OtpMail extends Mailable
{
    public function __construct(public readonly OtpMessage $otp)
    {
        $this->locale($otp->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('communication::otp.mail.subject', ['code' => $this->otp->code, 'name' => $this->otp->senderName], $this->otp->locale),
            from: new Address(config('mail.from.address'), $this->otp->senderName),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'communication::mail.otp', with: [
            'lang' => $this->otp->locale,
            'rtl' => $this->otp->locale === 'ar',
            'name' => $this->otp->senderName,
            'logo' => $this->otp->logoUrl,
            'colour' => $this->otp->colour ?? '#0F766E',
            'code' => $this->otp->code,
            'minutes' => $this->otp->minutes,
        ]);
    }
}
