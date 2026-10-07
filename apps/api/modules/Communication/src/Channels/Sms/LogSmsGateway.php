<?php

namespace Modules\Communication\Channels\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development: writes what would be sent to the log instead. */
final class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phoneE164, string $text): string
    {
        Log::info("SMS to {$phoneE164}: {$text}");

        return 'log-'.Str::uuid7();
    }
}
