<?php

namespace Modules\Messaging\Events;

/** A message went into a conversation: the others in it hear, and the academy when something was hidden from it. */
final readonly class MessageSent
{
    public function __construct(public string $academyId, public string $conversationId, public string $messageId) {}
}
