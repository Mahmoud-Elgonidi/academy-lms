<?php

namespace Modules\Messaging\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;

/** Someone in a conversation has read it up to now (their count clears on every device). Nothing is written when nothing was new. */
final class MarkConversationRead
{
    public function __construct(private readonly MessagingRules $rules) {}

    public function handle(Conversation $conversation, string $userId): void
    {
        if ($this->rules->sideOf($conversation, $userId) === null) {
            throw new AuthorizationException;
        }
        if ($conversation->last_message_at === null) {
            return;
        }
        $read = DB::table('conversation_reads')->where('conversation_id', $conversation->id)->where('user_id', $userId)->value('read_at');
        if ($read !== null && CarbonImmutable::parse($read)->greaterThanOrEqualTo($conversation->last_message_at)) {
            return;
        }
        DB::table('conversation_reads')->upsert(
            [['academy_id' => $conversation->academy_id, 'conversation_id' => $conversation->id, 'user_id' => $userId, 'read_at' => CarbonImmutable::now()]],
            ['academy_id', 'conversation_id', 'user_id'], ['read_at'],
        );
    }
}
