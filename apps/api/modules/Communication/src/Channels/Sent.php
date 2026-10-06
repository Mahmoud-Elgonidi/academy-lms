<?php

namespace Modules\Communication\Channels;

/** What a provider said when it took the message. */
final readonly class Sent
{
    public function __construct(public string $provider, public ?string $messageId = null) {}
}
