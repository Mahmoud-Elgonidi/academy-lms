<?php

namespace Modules\Identity\Otp;

use Illuminate\Support\Facades\Log;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;

/** Identity's default until Communication binds the real sender: the code goes to the log. */
final class LogOtpSender implements OtpSender
{
    public function supports(string $channel): bool
    {
        return in_array($channel, ['whatsapp', 'sms', 'email'], true);
    }

    public function send(OtpMessage $message): void
    {
        Log::info("Sign-in code for {$message->destination} by {$message->channel}: {$message->code}");
    }
}
