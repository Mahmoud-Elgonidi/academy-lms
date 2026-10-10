<?php

namespace Modules\Messaging\Actions;

use Carbon\CarbonImmutable;
use Modules\Messaging\ChatView;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Someone overseeing (conversations.oversee) reads a conversation, or what
 * was hidden from one message of it: each opening is in the activity log,
 * and a conversation that needed a look has had it.
 */
final class OverseeConversation
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function open(string $conversationId, string $actorId): Conversation
    {
        $c = Conversation::query()->findOrFail($conversationId);
        if ($c->needsLook()) {
            $c->update(['reviewed_at' => CarbonImmutable::now()]);
        }
        $this->audit->record('messaging.conversation_viewed', 'conversation', $c->id, ChatView::pair($c));

        return $c;
    }

    /** The words as typed, of a message something was hidden from. */
    public function original(string $messageId, string $actorId): string
    {
        $m = ConversationMessage::query()->whereNotNull('original')->findOrFail($messageId);
        $c = Conversation::query()->findOrFail($m->conversation_id);
        $this->audit->record('messaging.original_revealed', 'conversation', $c->id, ['message' => $m->id] + ChatView::pair($c));

        return (string) $m->original;
    }
}
