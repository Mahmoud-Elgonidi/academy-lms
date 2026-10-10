<?php

namespace Modules\Messaging\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;
use Modules\People\Contracts\PeopleApi;

/**
 * A person's conversations, for their screens and the apps: a teacher's
 * with each student they teach (or wrote with), a family's about one
 * learner with each of the learner's teachers; the last words, what is new
 * since they last read, and whether they may write.
 */
final class MyConversations
{
    public function __construct(private readonly MessagingRules $rules, private readonly PeopleApi $people) {}

    /**
     * Newest first, then the rest by name.
     *
     * @return list<array{conversation: ?Conversation, with_id: string, with_name: string, teacher_id: string, student_id: string, last: ?ConversationMessage, unread: int, why_not: ?string}>
     */
    public function forTeacher(string $teacherId): array
    {
        $now = CarbonImmutable::now();
        $students = $this->rules->studentsOf($teacherId, $now);
        $talks = Conversation::query()->where('teacher_id', $teacherId)->get()->keyBy('student_id');
        $names = $this->names(array_merge(array_keys($students), $talks->keys()->all()));
        [$last, $unread] = $this->latest($talks->pluck('id')->all(), $teacherId);
        $rows = [];
        foreach (array_unique([...$talks->keys()->all(), ...array_keys($students)]) as $studentId) {
            $c = $talks->get($studentId);
            $rows[] = $this->row($c, $teacherId, (string) $studentId, (string) $studentId, $names[$studentId] ?? '', $last[$c?->id] ?? null, (int) ($unread[$c?->id] ?? 0), $now);
        }

        return $this->sorted($rows);
    }

    /** @return list<array<string, mixed>> the learner's conversations, as one of their family (the student or a guardian) sees them */
    public function forLearner(string $viewerId, string $studentId): array
    {
        $now = CarbonImmutable::now();
        $teachers = $this->rules->teachersOf($studentId, $now);
        $talks = Conversation::query()->where('student_id', $studentId)->get()->keyBy('teacher_id');
        $names = $this->names(array_merge(array_keys($teachers), $talks->keys()->all()));
        [$last, $unread] = $this->latest($talks->pluck('id')->all(), $viewerId);
        $rows = [];
        foreach (array_unique([...$talks->keys()->all(), ...array_keys($teachers)]) as $teacherId) {
            $c = $talks->get($teacherId);
            $rows[] = $this->row($c, (string) $teacherId, $studentId, (string) $teacherId, $names[$teacherId] ?? '', $last[$c?->id] ?? null, (int) ($unread[$c?->id] ?? 0), $now);
        }

        return $this->sorted($rows);
    }

    /** Messages others wrote, in every conversation the person is in, since they last read it. */
    public function unread(string $userId): int
    {
        // Their own as a teacher or a student, and their children's as a guardian.
        $students = [$userId, ...$this->people->studentsOf($userId)];

        return (int) DB::table('conversation_messages as m')
            ->join('conversations as c', fn ($j) => $j->on('c.academy_id', '=', 'm.academy_id')->on('c.id', '=', 'm.conversation_id'))
            ->leftJoin('conversation_reads as r', fn ($j) => $j->on('r.academy_id', '=', 'c.academy_id')->on('r.conversation_id', '=', 'c.id')->where('r.user_id', '=', $userId))
            ->where(fn ($q) => $q->where('c.teacher_id', $userId)->orWhereIn('c.student_id', $students))
            ->where('m.author_id', '<>', $userId)
            ->whereRaw("m.created_at > coalesce(r.read_at, '-infinity'::timestamptz)")
            ->count();
    }

    /** @return array<string, mixed> */
    private function row(?Conversation $c, string $teacherId, string $studentId, string $withId, string $withName, ?ConversationMessage $last, int $unread, CarbonImmutable $now): array
    {
        return [
            'conversation' => $c,
            'with_id' => $withId,
            'with_name' => $withName,
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
            'last' => $last,
            'unread' => $unread,
            // Not written to yet: whether it could be.
            'why_not' => $this->rules->whyNot($c ?? new Conversation(['teacher_id' => $teacherId, 'student_id' => $studentId]), $now),
        ];
    }

    /**
     * Each conversation's newest message, and how many from others the viewer has not read, in two reads.
     *
     * @param  list<string>  $ids
     * @return array{0: array<string, ConversationMessage>, 1: array<string, int>}
     */
    private function latest(array $ids, string $viewerId): array
    {
        if ($ids === []) {
            return [[], []];
        }
        $last = ConversationMessage::query()->selectRaw('distinct on (conversation_id) conversation_messages.*')
            ->whereIn('conversation_id', $ids)->orderBy('conversation_id')->orderByDesc('created_at')->orderByDesc('id')
            ->get()->keyBy('conversation_id')->all();
        $unread = DB::table('conversation_messages as m')
            ->leftJoin('conversation_reads as r', fn ($j) => $j->on('r.academy_id', '=', 'm.academy_id')->on('r.conversation_id', '=', 'm.conversation_id')->where('r.user_id', '=', $viewerId))
            ->whereIn('m.conversation_id', $ids)->where('m.author_id', '<>', $viewerId)
            ->whereRaw("m.created_at > coalesce(r.read_at, '-infinity'::timestamptz)")
            ->groupBy('m.conversation_id')->selectRaw('m.conversation_id, count(*) as n')->pluck('n', 'm.conversation_id')->all();

        return [$last, array_map('intval', $unread)];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function sorted(array $rows): array
    {
        usort($rows, fn (array $a, array $b) => [($b['last']?->created_at?->getTimestamp() ?? 0), $a['with_name']] <=> [($a['last']?->created_at?->getTimestamp() ?? 0), $b['with_name']]);

        return $rows;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function names(array $ids): array
    {
        return $ids === [] ? [] : DB::table('users')->whereIn('id', array_values(array_unique($ids)))->pluck('name', 'id')->all();
    }
}
