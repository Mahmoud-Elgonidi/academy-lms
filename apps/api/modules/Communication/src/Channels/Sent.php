<?php

namespace Modules\Communication\Channels;

/** What a provider said when it took the message; or why it was not sent after all ($skipped: test_address). */
final readonly class Sent
{
    public function __construct(public string $provider, public ?string $messageId = null, public ?string $skipped = null) {}
}
