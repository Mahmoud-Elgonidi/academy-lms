<?php

namespace Modules\Messaging\Http\V1\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Messaging\Actions\MarkConversationRead;
use Modules\Messaging\Actions\OpenConversation;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\ChatView;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;
use Modules\Messaging\Queries\MyConversations;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * Conversations between a teacher and a student, for the apps (the same as
 * "Messages" in /teach and /my): a teacher with each student they teach, a
 * family about one learner (learner_id) with each of the learner's
 * teachers. The student's guardians are in every conversation; a phone
 * number, an email, a link or a handle is hidden before anyone reads it.
 */
final class ConversationsController
{
    public function __construct(
        private readonly MessagingRules $rules,
        private readonly FamilyView $family,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * My conversations.
     *
     * A teacher: each student they teach (or wrote with); a family: the learner's teachers (`learner_id`, else the
     * first learner). Those with the newest message first; `id` is null until someone opens it.
     */
    public function index(Request $request, MyConversations $conversations): JsonResponse
    {
        $me = $this->me($request);
        $learner = $this->learner($request, $me);
        $rows = $learner === null ? $conversations->forTeacher($me) : $conversations->forLearner($me, $learner->id);
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                /** Null until the conversation is opened. */
                'id' => $r['conversation']?->id,
                /** Who it is with: a student for a teacher, a teacher for a family. */
                'with' => ['id' => $r['with_id'], 'name' => $r['with_name']],
                'last_message' => $r['last'] ? [
                    'body' => ChatView::excerpt($r['last']->body, 120),
                    'mine' => $r['last']->author_id === $me,
                    'at' => $this->time->present($r['last']->created_at),
                ] : null,
                /** Messages from the others since I last read it. */
                'unread' => (int) $r['unread'],
                'can_write' => $r['why_not'] === null,
                /** Why I cannot write, in words: messages off, paused by the academy, no longer teaching. */
                'why_not' => $r['why_not'] === null ? null : $this->words('messaging::chat.why_not.'.$r['why_not']),
            ];
        }

        return response()->json(['data' => $data, 'meta' => [
            /** The learner (a family's list). */
            'learner' => $learner ? ['id' => $learner->id, 'name' => $learner->name] : null,
            /** What everyone in a conversation should know: guardians read it, the academy reviews it, contacts are hidden. */
            'notice' => $this->words('messaging::screens.watched.'.$this->look($me, $learner)),
        ]]);
    }

    /**
     * Unread messages.
     *
     * How many messages from others wait in all my conversations (a guardian: all their children's), for a badge.
     */
    public function unread(Request $request, MyConversations $conversations): JsonResponse
    {
        return response()->json(['data' => ['count' => $conversations->unread($this->me($request))]]);
    }

    /**
     * Open a conversation.
     *
     * With `with_id` from my list (a teacher: a student they teach; a family: a teacher of `learner_id`). It is made
     * the first time; one that exists is returned as it is.
     */
    public function store(Request $request, OpenConversation $open): JsonResponse
    {
        $request->validate([
            /** The person on the other side, as my list names them. */
            'with_id' => ['required', 'string'],
            /** A family: the learner it is about. */
            'learner_id' => ['nullable', 'string'],
        ]);
        $me = $this->me($request);
        $learner = $this->learner($request, $me);
        $with = (string) $request->input('with_id');
        $c = $learner === null ? $open->handle($me, $me, $with) : $open->handle($me, $with, $learner->id);

        return response()->json(['data' => $this->full($c, $me)], 201);
    }

    /**
     * One conversation.
     *
     * Who is in it and the newest 200 messages, oldest first, as I read them (contacts hidden). Opening it marks
     * it read.
     */
    public function show(Request $request, string $conversation, MarkConversationRead $read): JsonResponse
    {
        $me = $this->me($request);
        $c = $this->mine($conversation, $me);
        $read->handle($c, $me);

        return response()->json(['data' => $this->full($c, $me)]);
    }

    /**
     * Write.
     *
     * `body` up to 2000 characters. A phone number, an email, a link (not the academy's) or an account name in it
     * is hidden before anyone reads it; `hidden` says so. Send an `Idempotency-Key`.
     */
    public function send(Request $request, string $conversation, SendMessage $send): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:'.MessagingRules::MAX_LENGTH]]);
        $me = $this->me($request);
        $c = $this->mine($conversation, $me);
        $message = $send->handle($c, $me, (string) $request->input('body'));

        return response()->json(['data' => $this->message($message, $me, $this->look($me, null, $c))], 201);
    }

    /** @return array<string, mixed> */
    private function full(Conversation $c, string $me): array
    {
        $why = $this->rules->whyNot($c);
        $side = $this->rules->sideOf($c, $me);
        $look = $this->look($me, null, $c);
        $with = $side === 'teacher' ? $c->student_id : $c->teacher_id;
        $members = ChatView::members($c, $this->rules);
        $messages = [];
        foreach (ChatView::messages($c) as $m) {
            $messages[] = $this->message($m, $me, $look);
        }
        $people = [];
        foreach ($members as $m) {
            $people[] = [
                'id' => (string) $m['id'],
                'name' => (string) $m['name'],
                /** teacher | student | guardian */
                'side' => (string) $m['side'],
                'label' => (string) $m['label'],
                /** The reader. */
                'me' => $m['id'] === $me,
            ];
        }

        return [
            'id' => $c->id,
            /** Who it is with: the student for the teacher, the teacher for the family. */
            'with' => ['id' => $with, 'name' => (string) (collect($members)->firstWhere('id', $with)['name'] ?? '')],
            /** Everyone in it, by name only: the teacher, the student, each guardian. */
            'members' => $people,
            /** The newest 200, oldest first. */
            'messages' => $messages,
            'can_write' => $why === null,
            /** Why I cannot write, in words. */
            'why_not' => $why === null ? null : $this->words('messaging::chat.why_not.'.$why),
            /** What everyone in it should know: guardians read it, the academy reviews it, contacts are hidden. */
            'notice' => $this->words('messaging::screens.watched.'.$look),
        ];
    }

    /** @return array<string, mixed> */
    private function message(ConversationMessage $m, string $me, string $look): array
    {
        return [
            'id' => $m->id,
            /** teacher | student | guardian */
            'side' => $m->side,
            'side_label' => $this->words('messaging::chat.sides.'.$m->side),
            'author' => (string) (ChatView::names([$m->author_id])[$m->author_id] ?? ''),
            'mine' => $m->author_id === $me,
            /** As everyone reads it: anything hidden shows as [•••]. */
            'body' => $m->body,
            /** What was hidden from it, in words; null when nothing was. */
            'hidden' => ChatView::hiddenNote($m->hidden, $look),
            'at' => $this->time->present($m->created_at),
        ];
    }

    /** Wording in the reader's language. */
    private function words(string $key): string
    {
        return (string) __($key);
    }

    private function me(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }

    /** A family names the learner (else the first); a teacher writes as themselves (null). */
    private function learner(Request $request, string $me): ?Learner
    {
        if ($this->rules->isTeacher($me)) {
            return null;
        }
        $learner = $this->family->learner($me, $request->input('learner_id') ?: $request->query('learner_id'));

        return $learner ?? throw new AuthorizationException;
    }

    private function mine(string $conversation, string $me): Conversation
    {
        $c = Conversation::query()->findOrFail($conversation);
        if ($this->rules->sideOf($c, $me) === null) {
            abort(404);
        }

        return $c;
    }

    /** teacher | classic | student | kids */
    private function look(string $me, ?Learner $learner, ?Conversation $c = null): string
    {
        if (($c !== null && $c->teacher_id === $me) || ($c === null && $learner === null)) {
            return 'teacher';
        }

        return $this->rules->look($me);
    }
}
