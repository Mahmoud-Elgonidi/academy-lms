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
 * teachers marked in their lessons' mushaf), the checks of their recited
 * homework (what was heard and how it went), their plans on the academy's
 * Quran programs and each lesson's grades (never the teacher's notes), their
 * exams (never why one was cancelled) and certificates. Erasing removes them
 * all (a recording still waiting to be heard too, after commit), but for a
 * certificate the academy issued: it keeps its number and is revoked, so
 * verifying it says so, and the exam behind it loses the examiner's words
 * and the mistakes. Held whatever the academy's plan says, as it is the
 * learner's. The programs are the academy's and name nobody.
 */
final class QuranData implements PersonalDataSource
{
    /** A certificate revoked because its holder's account was erased (never shown: verifying says only "revoked"). */
    public const ERASED = 'erased';

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
            'quran_plans' => DB::table('quran_plans as p')->join('quran_programs as g', fn ($j) => $j->on('g.academy_id', '=', 'p.academy_id')->on('g.id', '=', 'p.program_id'))
                ->where('p.student_id', $userId)->orderBy('p.created_at')
                ->get(['g.name as program', 'g.track', 'p.position_surah', 'p.position_verse', 'p.status', 'p.finished_at', 'p.created_at'])->map(fn (object $p) => (array) $p)->all(),
            'quran_lessons' => DB::table('quran_lesson_results')->where('student_id', $userId)->orderBy('recorded_at')->get(['parts', 'advanced', 'recorded_at'])
                ->map(fn (object $r) => ['parts' => array_map(fn (array $part) => array_diff_key($part, ['note' => true]), json_decode((string) $r->parts, true) ?: []),
                    'advanced' => (bool) $r->advanced, 'recorded_at' => $r->recorded_at])->all(),
            'quran_exams' => DB::table('quran_exams as e')->join('quran_programs as g', fn ($j) => $j->on('g.academy_id', '=', 'e.academy_id')->on('g.id', '=', 'e.program_id'))
                ->where('e.student_id', $userId)->orderBy('e.created_at')
                ->get(['g.name as program', 'e.stop_kind', 'e.stop_number', 'e.status', 'e.grade', 'e.mistakes', 'e.result_note', 'e.retake_on', 'e.recorded_at', 'e.created_at'])
                ->map(fn (object $e) => ['mistakes' => json_decode((string) $e->mistakes, true)] + (array) $e)->all(),
            'quran_certificates' => DB::table('quran_certificates')->where('student_id', $userId)->orderBy('issued_at')
                ->get(['number', 'code', 'portion', 'riwaya', 'grade', 'issued_at', 'revoked_at'])
                ->map(fn (object $c) => ['portion' => json_decode((string) $c->portion, true)] + (array) $c)->all(),
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

        // A certificate the academy issued keeps its number (revoked, so verifying it says so); the rest of the exams go.
        $certified = DB::table('quran_certificates')->where('student_id', $userId)->pluck('exam_id')->all();
        $revoked = DB::table('quran_certificates')->where('student_id', $userId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => self::ERASED, 'updated_at' => now()]);
        DB::table('quran_exams')->where('student_id', $userId)->update(['retake_of' => null]);
        $exams = DB::table('quran_exams')->where('student_id', $userId)->whereNotIn('id', $certified)->delete();
        DB::table('quran_exams')->whereIn('id', $certified)->update(['plan_id' => null, 'result_note' => null, 'mistakes' => null, 'cancel_reason' => null, 'updated_at' => now()]);

        return [
            'quran_exams' => $exams + $revoked,
            'recitation_checks' => DB::table('submission_checks')->whereIn('id', $this->checks($userId)->select('c.id'))->delete(),
            'quran_lessons' => DB::table('quran_lesson_results')->where('student_id', $userId)->delete()
                + DB::table('quran_plans')->where('student_id', $userId)->delete(),
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
