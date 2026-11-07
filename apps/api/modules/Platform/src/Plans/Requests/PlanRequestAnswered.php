<?php

namespace Modules\Platform\Plans\Requests;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To whoever asked for an extra pack, in their language: it is on for this
 * month (with what is there now), or turned down with the platform team's
 * reason. The plan page says the same.
 */
final class PlanRequestAnswered extends Mailable
{
    use Queueable;

    /** @param  array<string, string>  $words  the sentence's placeholders, already written out */
    public function __construct(public readonly string $answer, public readonly array $words, public readonly string $url, string $locale)
    {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform::topups.mail.answer.subject_'.$this->answer, $this->words, $this->locale));
    }

    public function content(): Content
    {
        return new Content(text: 'platform::mail.plan-request-answered');
    }
}
