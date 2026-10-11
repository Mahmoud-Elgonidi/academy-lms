<?php

namespace Modules\Learning\Privacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Learning\Actions\SubmitHomework;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A learner's homework and what they handed in (with the files), the
 * lesson reports about them as their family reads them (never the
 * teacher's internal notes), the challenges they claimed and the cheers.
 * Erasing removes what they handed in (the files too, after commit), the
 * claims and the cheers; the reports stay, the academy's own record.
 */
final class LearningData implements PersonalDataSource
{
    public function key(): string
    {
        return 'learning';
    }

    public function export(string $userId): PersonalDataPart
    {
        $submissions = $this->submissions($userId)->get(['s.homework_id', 's.body', 's.file_path', 's.file_name', 's.submitted_at']);
        $files = [];
        foreach ($submissions as $i => $s) {
            if ($s->file_path !== null) {
                $files[$s->file_path] = 'homework/'.substr((string) $s->submitted_at, 0, 10).'-'.($i + 1).'-'.basename((string) $s->file_name);
            }
        }

        return new PersonalDataPart([
            'homework' => DB::table('homework')->where('student_id', $userId)->orderBy('created_at')
                ->get(['id', 'title', 'instructions', 'due_at', 'status', 'grade', 'feedback', 'created_at'])
                ->map(fn (object $h) => (array) $h + ['handed_in' => $submissions->where('homework_id', $h->id)
                    ->map(fn (object $s) => ['text' => $s->body, 'file' => $s->file_path === null ? null : ($files[$s->file_path] ?? null), 'at' => $s->submitted_at])->values()->all()])
                ->all(),
            'lesson_reports' => DB::table('session_reports')->where('student_id', $userId)->where('status', 'submitted')->orderBy('session_starts_at')
                ->get(['session_starts_at as lesson_at', 'kind', 'engagement', 'overall', 'notes_for_student', 'absence_note', 'submitted_at'])->map(fn (object $r) => (array) $r)->all(),
            'challenges' => DB::table('challenge_claims')->where('student_id', $userId)->orderBy('claimed_at')
                ->get(['challenge', 'period', 'points', 'sticker', 'claimed_at'])->map(fn (object $c) => (array) $c)->all(),
            // Practice rounds in the games are kept with the learner's record (like lessons); the copy has them all.
            'practice' => DB::table('practice_rounds')->where('student_id', $userId)->orderBy('played_at')
                ->get(['game', 'focus', 'correct', 'total', 'stars', 'seconds', 'played_at'])->map(fn (object $r) => (array) $r)->all(),
            'cheers' => DB::table('cheers')->where(fn ($q) => $q->where('student_id', $userId)->orWhere('from_id', $userId))->orderBy('created_at')
                ->select(['kind', 'created_at'])->selectRaw("case when from_id = ? then 'sent' else 'received' end as direction", [$userId])
                ->get()->map(fn (object $c) => (array) $c)->all(),
        ], $files);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        $files = $this->submissions($userId)->whereNotNull('s.file_path')->pluck('s.file_path')->all();
        $handedIn = DB::table('homework_submissions')->whereIn('id', $this->submissions($userId)->select('s.id'))->delete();
        if ($files !== []) {
            DB::afterCommit(fn () => Storage::disk(SubmitHomework::DISK)->delete($files));
        }

        return [
            'homework_handed_in' => $handedIn,
            'challenges' => DB::table('challenge_claims')->where('student_id', $userId)->delete(),
            'cheers' => DB::table('cheers')->where(fn ($q) => $q->where('student_id', $userId)->orWhere('from_id', $userId))->delete(),
        ];
    }

    /** What the person handed in: their own, and what was handed in on their homework. */
    private function submissions(string $userId)
    {
        return DB::table('homework_submissions as s')
            ->join('homework as h', fn ($j) => $j->on('h.academy_id', '=', 's.academy_id')->on('h.id', '=', 's.homework_id'))
            ->where(fn ($q) => $q->where('h.student_id', $userId)->orWhere('s.submitted_by', $userId))
            ->orderBy('s.submitted_at');
    }
}
