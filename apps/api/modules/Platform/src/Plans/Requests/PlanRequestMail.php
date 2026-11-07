<?php

namespace Modules\Platform\Plans\Requests;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Platform\Contracts\ConsoleApi;

/**
 * To the platform team: an academy asked for an extra pack or a bigger plan,
 * what it asked, and where to answer it (console › Plan requests). In Arabic
 * and English, one message; no names of people.
 */
final class PlanRequestMail extends Mailable
{
    use Queueable;

    /** @param  array{academy: string, what: string, note: ?string}  $request  as people read it, already written out */
    public function __construct(public readonly string $kind, public readonly array $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform::topups.mail.team.subject_'.$this->kind, ['academy' => $this->request['academy']], 'en'));
    }

    public function content(): Content
    {
        return new Content(text: 'platform::mail.plan-request', with: ['url' => app(ConsoleApi::class)->consoleUrl('plan-requests')]);
    }
}
