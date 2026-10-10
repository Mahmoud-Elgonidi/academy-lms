<?php

namespace Modules\Messaging;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;

/** A conversation in words, for every screen: who is in it, and its messages in the viewer's time zone and language. */
final class ChatView
{
    /** The newest messages a screen shows. */
    public const LIMIT = 200;

    /** @return list<ConversationMessage> the newest messages, oldest first */
    public static function messages(Conversation $c, int $limit = self::LIMIT): array
    {
        return ConversationMessage::query()->where('conversation_id', $c->id)->latest('created_at')->latest('id')->limit($limit)->get()->reverse()->values()->all();
    }

    /**
     * Who is in it, the teacher first, then the student, then each guardian.
     *
     * @return list<array{id: string, name: string, side: string, label: string}>
     */
    public static function members(Conversation $c, MessagingRules $rules): array
    {
        $ids = $rules->members($c);
        $names = self::names($ids);

        return array_map(fn (string $id) => [
            'id' => $id,
            'name' => $names[$id] ?? '',
            'side' => $side = $id === $c->teacher_id ? 'teacher' : ($id === $c->student_id ? 'student' : 'guardian'),
            'label' => __('messaging::chat.sides.'.$side),
        ], $ids);
    }

    /**
     * @param  list<ConversationMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public static function lines(array $messages, string $viewerId, string $tz, bool $staff = false, string $look = 'classic'): array
    {
        $names = self::names(array_map(fn (ConversationMessage $m) => $m->author_id, $messages));
        $locale = app()->getLocale();

        return array_map(function (ConversationMessage $m) use ($names, $viewerId, $tz, $locale, $staff, $look) {
            $at = $m->created_at->setTimezone($tz)->locale($locale);

            return [
                'id' => $m->id,
                'side' => $m->side,
                'mine' => $m->author_id === $viewerId,
                'author' => $names[$m->author_id] ?? '',
                'sideLabel' => __('messaging::chat.sides.'.$m->side),
                'body' => $m->body,
                'hidden' => self::hiddenNote($m->hidden, $look),
                // The academy's eyes only: something was hidden, and the words as typed can be opened (logged).
                'canReveal' => $staff && $m->hidden !== [],
                'outsideApp' => $staff && in_array('outside_app', $m->flags, true),
                'when' => $at->translatedFormat('j M · H:i'),
                'ago' => $at->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
            ];
        }, $messages);
    }

    /**
     * What was hidden, in words: a child's in the kids' look.
     *
     * @param  list<string>  $kinds
     */
    public static function hiddenNote(array $kinds, string $look = 'classic'): ?string
    {
        if ($kinds === []) {
            return null;
        }

        return __($look === 'kids' ? 'messaging::chat.hidden_kids' : 'messaging::chat.hidden', ['what' => implode(__('messaging::chat.and'), array_map(fn (string $k) => __('messaging::chat.kinds.'.$k), $kinds))]);
    }

    /** @return array{teacher: string, student: string} the two names, for the activity log */
    public static function pair(Conversation $c): array
    {
        $names = self::names([$c->teacher_id, $c->student_id]);

        return ['teacher' => $names[$c->teacher_id] ?? '', 'student' => $names[$c->student_id] ?? ''];
    }

    /** The excerpt a list or a notification shows. */
    public static function excerpt(?string $body, int $length = 90): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', (string) $body));

        return mb_strlen($flat) > $length ? mb_substr($flat, 0, $length - 1).'…' : $flat;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public static function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
