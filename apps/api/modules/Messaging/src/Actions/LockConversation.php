<?php

namespace Modules\Messaging\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Messaging\ChatView;
use Modules\Messaging\Models\Conversation;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The academy pauses a conversation (conversations.manage): both sides still
 * read it, nobody writes until it is unlocked. The reason stays on the
 * conversation for the academy; the people in it see only that it is
 * paused. In the activity log without the reason.
 */
final class LockConversation
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function lock(string $conversationId, string $reason, string $actorId): Conversation
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 400) {
            throw ValidationException::withMessages(['reason' => __('messaging::chat.errors.reason')]);
        }

        return DB::transaction(function () use ($conversationId, $reason, $actorId) {
            $c = Conversation::query()->whereKey($conversationId)->lockForUpdate()->firstOrFail();
            if ($c->isLocked()) {
                throw ValidationException::withMessages(['reason' => __('messaging::chat.errors.locked_already')]);
            }
            $c->update(['locked_at' => CarbonImmutable::now(), 'locked_by' => $actorId, 'lock_reason' => $reason]);
            $this->audit->record('messaging.conversation_locked', 'conversation', $c->id, ChatView::pair($c));

            return $c;
        });
    }

    public function unlock(string $conversationId, string $actorId): Conversation
    {
        return DB::transaction(function () use ($conversationId) {
            $c = Conversation::query()->whereKey($conversationId)->lockForUpdate()->firstOrFail();
            if (! $c->isLocked()) {
                return $c;
            }
            $c->update(['locked_at' => null, 'locked_by' => null, 'lock_reason' => null]);
            $this->audit->record('messaging.conversation_unlocked', 'conversation', $c->id, ChatView::pair($c));

            return $c;
        });
    }
}
