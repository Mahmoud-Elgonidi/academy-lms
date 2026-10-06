<?php

namespace Modules\Communication\Channels\WhatsApp;

interface WhatsAppGateway
{
    public function name(): string;

    /** Sends a business-initiated message; returns the provider's message id. Throws on failure. */
    public function send(string $phoneE164, string $title, string $body, string $locale): string;
}
