<?php

namespace Modules\Safeguarding\Queries;

use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\PeopleApi;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;
use Modules\Scheduling\Contracts\AttendanceApi;

/**
 * The leads' view of concerns: the ones still open (urgent first, then the
 * oldest), the closed ones of the last 90 days, and one concern in full:
 * who raised it and about whom, the lesson, what was said, every step taken,
 * and where the teacher it may be about stands with students under 18.
 */
final class ConcernBoard
{
    public const CLOSED_DAYS = 90;

    public function __construct(
        private readonly AttendanceApi $lessons,
        private readonly PeopleApi $people,
    ) {}

    /** @return array{open: list<array<string, mixed>>, closed: list<array<string, mixed>>} */
    public function lists(): array
    {
        $open = Concern::query()->where('status', '<>', ConcernStatus::Closed->value)->orderByDesc('urgent')->orderBy('created_at')->get();
        $closed = Concern::query()->where('status', ConcernStatus::Closed->value)->where('closed_at', '>=', now()->subDays(self::CLOSED_DAYS))->orderByDesc('closed_at')->get();
        $names = $this->names($open->concat($closed)->flatMap(fn (Concern $c) => [$c->student_id, $c->teacher_id])->filter()->unique()->all());
        $row = fn (Concern $c) => [
            'id' => $c->id, 'number' => $c->reference(), 'category' => $c->category, 'urgent' => $c->urgent, 'status' => $c->status,
            'side' => $c->reporter_side, 'student' => $names[$c->student_id] ?? null, 'teacher' => $names[$c->teacher_id] ?? null,
            'created_at' => $c->created_at, 'escalated' => $c->escalated_at !== null, 'outcome' => $c->outcome, 'closed_at' => $c->closed_at,
        ];

        return ['open' => $open->map($row)->all(), 'closed' => $closed->map($row)->all()];
    }

    /** @return array<string, mixed>|null */
    public function details(string $concernId): ?array
    {
        $c = Concern::query()->find($concernId);
        if ($c === null) {
            return null;
        }
        $entries = ConcernEntry::query()->where('concern_id', $c->id)->orderBy('created_at')->get();
        $names = $this->names(array_values(array_filter(array_unique([$c->reporter_id, $c->student_id, $c->teacher_id, ...$entries->pluck('author_id')->all()]))));
        $lesson = $c->session_id ? $this->lessons->lesson($c->session_id) : null;

        return [
            'concern' => $c,
            'reporter' => $names[$c->reporter_id] ?? '—',
            'student' => $c->student_id ? ($names[$c->student_id] ?? '—') : null,
            'teacher' => $c->teacher_id ? ($names[$c->teacher_id] ?? '—') : null,
            'lesson' => $lesson,
            'clearance' => $c->teacher_id ? ($this->people->clearances([$c->teacher_id])[$c->teacher_id] ?? null) : null,
            'entries' => $entries->map(fn (ConcernEntry $e) => [
                'kind' => $e->kind, 'event' => $e->event, 'body' => $e->body, 'data' => $e->data,
                'by' => $e->author_id ? ($names[$e->author_id] ?? '—') : null, 'at' => $e->created_at,
            ])->all(),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function names(array $ids): array
    {
        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
