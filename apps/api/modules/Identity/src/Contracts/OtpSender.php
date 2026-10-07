<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Contracts\DTOs\OtpMessage;

/**
 * Delivers a one-time code. Identity decides who gets a code and checks it;
 * Communication, which sits above Identity, binds the real sender (WhatsApp
 * authentication template, SMS, email). Identity's own default only logs.
 */
interface OtpSender
{
    /** Whether a code can go by this channel (whatsapp, sms, email) right now. */
    public function supports(string $channel): bool;

    /** Sends the code. Throws when the channel refused it. */
    public function send(OtpMessage $message): void;
}
