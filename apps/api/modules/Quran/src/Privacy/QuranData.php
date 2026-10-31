<?php

namespace Modules\Quran\Privacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Quran\Support\Recordings;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * What the Quran module holds about a learner: the hifz map (each page, every
 * review and the pages recited), the mistakes notebook (the words their
 * teachers marked in their lessons' mushaf), and the checks of their recited
 * homework (what was heard and how it went). Erasing removes them all (a
 * recording still waiting to be heard too, after commit). Held whatever the
 * academy's plan says, as it is the learner's.
 */
final class QuranData implements PersonalDataSource
{
    public function key(): string
    {
        return 'quran';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'hifz_pages' => DB::table('hifz_pages')->where('student_id', $userId)->orderBy('page')
                ->get(['page', 'state', 'due_at', 'last_reviewed_at', 'reviews', 'lapses'])->map(fn (object $p) => (array) $p)->all(),
            'hifz_reviews' => DB::table('hifz_reviews')->where('student_id', $userId)->orderBy('reviewed_at')
                ->get(['page', 'grade', 'source', 'reviewed_at'])->map(fn (object $r) => (array) $r)->all(),
            'hifz_recited' => DB::table('hifz_recitations')->where('student_id', $userId)->orderBy('created_at')
                ->get(['page', 'status', 'grade', 'created_at'])->map(fn (object $r) => (array) $r)->all(),
            'mistakes' => DB::table('recitation_mistakes')->where('student_id', $userId)->orderBy('created_at')
                ->get(['surah', 'verse', 'word', 'kind', 'created_at'])->map(fn (object $m) => (array) $m)->all(),
            // A recited homework's check (for the teacher) is about the learner too: what was heard and how it went.
            'recitation_checks' => $this->checks($userId)->where('c.status', 'ready')
                ->get(['h.title as homework', 'c.heard', 'c.result', 'c.checked_at'])
                ->map(fn (object $c) => ['homework' => $c->homework, 'heard' => $c->heard, 'summary' => json_decode((string) $c->result, true)['summary'] ?? null, 'at' => $c->checked_at])
                ->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        $waiting = DB::table('hifz_recitations')->where('student_id', $userId)->whereNotNull('file_path')->pluck('file_path')->all();
        if ($waiting !== []) {
            DB::afterCommit(fn () => Storage::disk(Recordings::DISK)->delete($waiting));
        }

        return [
            'recitation_checks' => DB::table('submission_checks')->whereIn('id', $this->checks($userId)->select('c.id'))->delete(),
            'hifz' => DB::table('recitation_mistakes')->where('student_id', $userId)->delete()
                + DB::table('hifz_recitations')->where('student_id', $userId)->delete()
                + DB::table('hifz_reviews')->where('student_id', $userId)->delete()
                + DB::table('hifz_pages')->where('student_id', $userId)->delete(),
        ];
    }

    /** The checks of what the person handed in on their homework, or handed in themself. */
    private function checks(string $userId)
    {
        return DB::table('submission_checks as c')
            ->join('homework_submissions as s', fn ($j) => $j->on('s.academy_id', '=', 'c.academy_id')->on('s.id', '=', 'c.submission_id'))
            ->join('homework as h', fn ($j) => $j->on('h.academy_id', '=', 'c.academy_id')->on('h.id', '=', 'c.homework_id'))
            ->where(fn ($q) => $q->where('h.student_id', $userId)->orWhere('s.submitted_by', $userId))
            ->orderBy('c.created_at');
    }
}
