<?php

namespace Modules\Communication\Channels\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development: writes what would be sent to the log instead. */
final class LogWhatsAppGateway implements WhatsAppGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phoneE164, string $title, string $body, string $locale): string
    {
        Log::info("WhatsApp to {$phoneE164} ({$locale}): {$title} — {$body}");

        return 'log-'.Str::uuid7();
    }

    public function sendCode(string $phoneE164, string $code, string $locale): string
    {
        Log::info("WhatsApp code to {$phoneE164} ({$locale}): {$code}");

        return 'log-'.Str::uuid7();
    }
}
