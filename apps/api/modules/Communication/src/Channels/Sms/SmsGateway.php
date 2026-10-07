<?php

namespace Modules\Communication\Channels\Sms;

interface SmsGateway
{
    public function name(): string;

    /** Sends a text; returns the provider's message id. Throws on failure. */
    public function send(string $phoneE164, string $text): string;
}
