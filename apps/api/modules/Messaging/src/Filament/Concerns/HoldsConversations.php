<?php

namespace Modules\Messaging\Filament\Concerns;

use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Messaging\Actions\MarkConversationRead;
use Modules\Messaging\Actions\OpenConversation;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\ChatView;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Queries\MyConversations;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * A teacher's or a family's conversations in their panel
 * (docs/architecture/02, section 17): the people they talk with (a teacher's
 * students, a learner's teachers), one conversation open next to them with
 * who else is in it, and writing. What is new is read as it shows; the page
 * follows the person's own live channel.
 */
trait HoldsConversations
{
    /** Who the conversation open is with: a student on a teacher's page, a teacher on a family's. */
    #[Url]
    public ?string $with = null;

    /** Opened from a notification: the conversation, whoever's learner it is about. */
    #[Url]
    public ?string $conversation = null;

    public string $body = '';

    /** teacher | family */
    abstract protected function side(): string;

    /** The learner a family's page is about; null for a teacher. */
    abstract protected function learnerId(): ?string;

    public function mountHoldsConversations(): void
    {
        $me = (string) auth()->id();
        $c = $this->conversation ? Conversation::query()->find($this->conversation) : null;
        if ($c !== null && app(MessagingRules::class)->sideOf($c, $me) !== null) {
            if ($this->side() === 'family') {
                app(FamilyView::class)->choose($me, $c->student_id);
                $this->with = $c->teacher_id;
            } else {
                $this->with = $c->student_id;
            }
        }
        $this->conversation = null;
    }

    /** teacher | classic | student | kids: whose words the page speaks in. */
    public function look(): string
    {
        return $this->side() === 'teacher' ? 'teacher' : app(MessagingRules::class)->look((string) auth()->id());
    }

    public function channel(): string
    {
        return PersonalChannel::name((string) auth()->id());
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        $me = (string) auth()->id();
        $q = app(MyConversations::class);
        $rows = $this->side() === 'teacher' ? $q->forTeacher($me) : (($learner = $this->learnerId()) === null ? [] : $q->forLearner($me, $learner));
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (array $r) => [
            'with' => $r['with_id'],
            'name' => $r['with_name'],
            'initial' => mb_substr(trim($r['with_name']), 0, 1),
            'excerpt' => $r['last'] ? ($r['last']->author_id === $me ? __('messaging::screens.you').': ' : '').ChatView::excerpt($r['last']->body, 70) : null,
            'ago' => $r['last']?->created_at->setTimezone($tz)->locale(app()->getLocale())->diffForHumans(['options' => CarbonInterface::JUST_NOW, 'short' => true]),
            'unread' => $r['unread'],
            'readOnly' => $r['why_not'] !== null,
        ], $rows);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function opened(): ?array
    {
        [$teacherId, $studentId] = $this->pair() ?? [null, null];
        if ($teacherId === null) {
            return null;
        }
        $me = (string) auth()->id();
        $rules = app(MessagingRules::class);
        $c = Conversation::query()->where('teacher_id', $teacherId)->where('student_id', $studentId)->first();
        if ($c !== null && $rules->sideOf($c, $me) === null) {
            return null;
        }
        if ($c === null && ! $rules->teaches($teacherId, $studentId)) {
            return null;
        }
        // Read as it shows (the count clears on every device).
        if ($c !== null) {
            app(MarkConversationRead::class)->handle($c, $me);
        }
        $pair = $c ?? new Conversation(['teacher_id' => $teacherId, 'student_id' => $studentId]);
        $members = ChatView::members($pair, $rules);
        $why = $rules->whyNot($pair);
        $withId = $this->side() === 'teacher' ? $studentId : $teacherId;

        return [
            'with' => $withId,
            'name' => collect($members)->firstWhere('id', $withId)['name'] ?? '',
            'others' => implode('، ', array_map(fn (array $m) => $m['name'].' ('.$m['label'].')',
                array_values(array_filter($members, fn (array $m) => $m['id'] !== $me && $m['id'] !== $withId)))),
            'lines' => $c === null ? [] : ChatView::lines(ChatView::messages($c), $me, app(ViewerTimezone::class)->effective(), look: $this->look()),
            'whyNot' => $why === null ? null : __('messaging::chat.why_not.'.$why),
        ];
    }

    public function pick(string $with): void
    {
        $this->with = $with;
        $this->reset('body');
        unset($this->opened, $this->rows);
    }

    public function back(): void
    {
        $this->with = null;
        $this->reset('body');
        unset($this->opened, $this->rows);
    }

    public function send(): void
    {
        [$teacherId, $studentId] = $this->pair() ?? abort(404);
        $me = (string) auth()->id();
        try {
            $c = app(OpenConversation::class)->handle($me, $teacherId, $studentId);
            $message = app(SendMessage::class)->handle($c, $me, $this->body);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->reset('body');
        unset($this->opened, $this->rows);
        if ($message->hidden !== []) {
            Notification::make()->warning()->title(ChatView::hiddenNote($message->hidden, $this->look()))->send();
        }
    }

    /** @return array{0: string, 1: string}|null the teacher and the student of the conversation open */
    private function pair(): ?array
    {
        if ($this->with === null || $this->with === '') {
            return null;
        }
        if ($this->side() === 'teacher') {
            return [(string) auth()->id(), $this->with];
        }
        $learner = $this->learnerId();

        return $learner === null ? null : [$this->with, $learner];
    }
}
