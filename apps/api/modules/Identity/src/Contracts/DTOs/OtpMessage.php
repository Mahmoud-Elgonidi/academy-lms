<?php

namespace Modules\Identity\Contracts\DTOs;

/**
 * A code to deliver, with everything the message needs: it is sent after the
 * response went out, when the request's academy is no longer set.
 */
final readonly class OtpMessage
{
    public function __construct(
        public string $channel,          // whatsapp | sms | email
        public string $destination,      // E.164 phone or email
        public string $code,
        public int $minutes,
        public string $locale,
        public string $senderName,       // the academy's name, or the platform's for discovery
        public ?string $logoUrl = null,
        public ?string $colour = null,
        public ?string $host = null,     // the academy's web host, for the browser's one-time-code autofill (SMS)
    ) {}
}
