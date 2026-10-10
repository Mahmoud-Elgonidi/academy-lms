<?php

namespace Modules\Messaging\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Messaging\Events\MessageSent;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;

/**
 * Someone in a conversation writes (docs/architecture/02, section 17): the
 * teacher, the student or one of the student's guardians. A phone number,
 * an email, a link (not the academy's own) or a handle is hidden before it
 * is saved; the words as typed are kept encrypted for the academy's
 * oversight only. The others hear of it; the academy hears when something
 * was hidden.
 */
final class SendMessage
{
    public function __construct(private readonly MessagingRules $rules) {}

    public function handle(Conversation $conversation, string $authorId, ?string $body): ConversationMessage
    {
        $side = $this->rules->sideOf($conversation, $authorId) ?? throw new AuthorizationException;
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", (string) $body)));
        if ($text === '') {
            throw ValidationException::withMessages(['body' => __('messaging::chat.errors.empty')]);
        }
        if (mb_strlen($text) > MessagingRules::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => __('messaging::chat.errors.long', ['max' => MessagingRules::MAX_LENGTH])]);
        }
        if (($why = $this->rules->whyNot($conversation)) !== null) {
            throw ValidationException::withMessages(['body' => __('messaging::chat.why_not.'.$why)]);
        }
        $masked = $this->rules->masker()->mask($text);

        return DB::transaction(function () use ($conversation, $authorId, $side, $text, $masked) {
            $c = Conversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if ($c->isLocked()) {
                throw ValidationException::withMessages(['body' => __('messaging::chat.why_not.locked')]);
            }
            $now = CarbonImmutable::now();
            if (ConversationMessage::query()->where('author_id', $authorId)->where('created_at', '>', $now->subMinute())->count() >= MessagingRules::PER_MINUTE) {
                throw ValidationException::withMessages(['body' => __('messaging::chat.errors.too_fast')]);
            }
            $message = ConversationMessage::query()->create([
                'conversation_id' => $c->id, 'author_id' => $authorId, 'side' => $side,
                'body' => $masked->text, 'hidden' => $masked->hidden, 'flags' => $masked->flags,
                'original' => $masked->changed() ? $text : null,
            ]);
            $c->update([
                'last_message_at' => $message->created_at,
                'messages_count' => $c->messages_count + 1,
                'hidden_count' => $c->hidden_count + ($masked->changed() ? 1 : 0),
                'flagged_at' => $masked->changed() || $masked->flags !== [] ? $message->created_at : $c->flagged_at,
            ]);
            // The author has read their own words.
            DB::table('conversation_reads')->upsert(
                [['academy_id' => $c->academy_id, 'conversation_id' => $c->id, 'user_id' => $authorId, 'read_at' => $message->created_at]],
                ['academy_id', 'conversation_id', 'user_id'], ['read_at'],
            );
            DB::afterCommit(fn () => event(new MessageSent($c->academy_id, $c->id, $message->id)));

            return $message;
        });
    }
}
