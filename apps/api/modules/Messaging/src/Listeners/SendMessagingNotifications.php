<?php

namespace Modules\Messaging\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Messaging\ChatView;
use Modules\Messaging\Events\MessageSent;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;

/**
 * Messaging's notifications (wording in lang/{ar,en}/notifications.php).
 * The others in a conversation hear of a new message once until they read
 * it (not on every line); who oversees conversations hears, at most once a
 * day for each, that something was hidden from one. Nobody hears of their
 * own words.
 */
final class SendMessagingNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly MessagingRules $rules,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'messaging::notifications.audience.';
        foreach ([
            ['new_message', 'learning', $a.'members', ['push', 'whatsapp'], ['from' => 'أ. أحمد', 'excerpt' => 'أحسنت اليوم! راجع آخر صفحتين قبل الحصة القادمة.']],
            ['contact_hidden', 'staff', $a.'overseers', ['push', 'email'], ['teacher' => 'أ. أحمد', 'student' => 'يوسف', 'who' => 'المعلّم']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "messaging.{$name}", category: $category,
                title: "messaging::notifications.{$name}.title", body: "messaging::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function sent(MessageSent $event): void
    {
        $c = Conversation::query()->find($event->conversationId);
        $m = ConversationMessage::query()->find($event->messageId);
        if ($c === null || $m === null) {
            return;
        }
        $names = ChatView::names([$m->author_id, $c->teacher_id, $c->student_id]);
        $others = array_values(array_diff($this->rules->members($c), [$m->author_id]));
        $reads = DB::table('conversation_reads')->where('conversation_id', $c->id)->whereIn('user_id', $others)->pluck('read_at', 'user_id');
        foreach ($others as $userId) {
            // Once for each run of unread messages: the key changes when they read.
            $since = isset($reads[$userId]) ? CarbonImmutable::parse($reads[$userId])->getTimestamp() : 0;
            $this->notifications->send(new Message('messaging.new_message', [$userId], [
                'from' => $names[$m->author_id] ?? '',
                'excerpt' => ChatView::excerpt($m->body),
            ], ['screen' => 'conversation', 'id' => $c->id], "chat:{$c->id}:{$since}"));
        }

        if ($m->hidden !== []) {
            $this->notifications->send(new Message('messaging.contact_hidden', $this->rules->overseers(), [
                'teacher' => $names[$c->teacher_id] ?? '',
                'student' => $names[$c->student_id] ?? '',
                'who' => new Translatable('messaging::chat.sides.'.$m->side),
            ], ['screen' => 'messaging_conversation', 'id' => $c->id], 'chat-hidden:'.$c->id.':'.CarbonImmutable::now()->format('Y-m-d')));
        }
    }
}
