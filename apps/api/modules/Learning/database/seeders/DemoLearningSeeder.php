<?php

namespace Modules\Learning\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\MarkMissedHomework;
use Modules\Learning\Actions\MarkOverdueReports;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\ReviewSessionReport;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;

/**
 * Demo reports and homework through the real actions. Every enrollment
 * whose subject has one program follows it. Ziad's teacher (khaled@demo.test)
 * reported five of his six lessons, one of them late; a supervisor reviewed
 * the first; the last is overdue and waits in the teacher area with today's
 * plan filled in. Homework in every state, including a voice recording.
 */
final class DemoLearningSeeder extends Seeder
{
    public function run(
        EnrollmentApi $enrollments,
        CurriculumApi $curriculum,
        SubmitSessionReport $submit,
        ReviewSessionReport $review,
        SubmitHomework $handIn,
        ReviewHomework $mark,
        MarkOverdueReports $overdue,
        MarkMissedHomework $missed,
    ): void {
        $realNow = CarbonImmutable::now();
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');

        foreach (DB::table('enrollments')->get(['id', 'subject_id']) as $e) {
            $programs = $curriculum->programsFor($e->subject_id);
            if (count($programs) === 1) {
                $enrollments->attachCurriculum($e->id, $programs[0]->id, $owner);
            }
        }

        $ziad = DB::table('users')->where('email', 'ziad@demo.test')->value('id');
        $enrollmentId = DB::table('enrollments')->where('student_id', $ziad)->value('id');
        $versionId = $enrollmentId ? $enrollments->find($enrollmentId)?->curriculumVersionId : null;
        if ($versionId === null) {
            return;
        }
        $lesson = array_map(fn ($n) => $n->id, $curriculum->tree($versionId)->lessons());
        $reports = SessionReport::query()->where('enrollment_id', $enrollmentId)->where('kind', 'lesson')->orderBy('session_starts_at')->get();

        // [items [lesson index, outcome, score], engagement, overall, note, hours after the lesson, homework]
        $script = [
            [[[0, 'mastered', 5], [1, 'introduced', 3]], 5, 4, 'بداية ممتازة: زياد بيفرّق بين الاسم والفعل بسهولة.', 2,
                ['title' => 'اكتب خمس جمل فيها أسماء، وحدّد علامة كل اسم', 'accepts' => 'text', 'due_days' => 3]],
            [[[1, 'mastered', 4], [2, 'introduced', 3]], 4, 4, 'المثنى تمام، الجمع محتاج تدريب بسيط.', 20, null],
            [[[2, 'mastered', 5], [3, 'practiced', 3]], 4, 5, 'تحسن واضح في التذكير والتأنيث.', 3,
                ['title' => 'صرّف الفعل "كتب" في الماضي مع كل الضمائر', 'accepts' => 'any', 'due_days' => 3]],
            [[[3, 'mastered', 4], [4, 'introduced', 3], [0, 'practiced', 5]], 3, 4, 'كان مرهق شوية النهارده، بس راجعنا علامات الاسم كويس.', 1, null],
            [[[4, 'practiced', 4]], 5, 4, 'المضارع محتاج حصة كمان قبل ما ننتقل للأمر.', 2,
                ['title' => 'سجّل نفسك وانت بتقرا فقرة الكتاب ص 14 وتحدد الأفعال المضارعة', 'accepts' => 'audio', 'due_days' => 4]],
        ];

        foreach ($script as $i => [$items, $engagement, $overall, $note, $after, $homework]) {
            $report = $reports[$i] ?? null;
            if ($report === null) {
                break;
            }
            CarbonImmutable::setTestNow($report->session_starts_at->addMinutes(45)->addHours($after));
            $submit->handle($report, $report->teacher_id, [
                'engagement' => $engagement, 'overall' => $overall, 'notes_for_student' => $note,
                'notes_internal' => $i === 3 ? 'ولي الأمر قال إن عنده امتحانات في المدرسة الأسبوع ده.' : null,
                'items' => array_map(fn ($it) => ['node_id' => $lesson[$it[0]], 'outcome' => $it[1], 'score' => $it[2]], $items),
                'homework' => $homework,
            ]);
        }

        // A supervisor read the first report.
        if ($first = $reports->first()) {
            CarbonImmutable::setTestNow($first->refresh()->submitted_at->addDay());
            $review->handle($first, $owner, 'تقرير واضح. ياريت تضيف ملاحظة عن الواجب في الحصة الجاية.');
        }

        // Homework: the first handed in and marked, the second still to do (missed), the recording handed in and waiting.
        $homework = Homework::query()->where('enrollment_id', $enrollmentId)->orderBy('due_at')->get();
        if ($h = $homework->get(0)) {
            CarbonImmutable::setTestNow($h->due_at->subDay());
            $handIn->handle($h, $ziad, "الكتابُ مفيدٌ.\nفي البيتِ حديقةٌ.\nالطالبُ مجتهدٌ.\nمحمدٌ في المسجدِ.\nالقلمُ جديدٌ.", null);
            CarbonImmutable::setTestNow($h->due_at->addHours(5));
            $mark->handle($h->refresh(), $h->teacher_id, 4, 'ممتاز! انتبه للتنوين في الجملة الرابعة.');
        }
        if ($h = $homework->get(2)) {
            CarbonImmutable::setTestNow($h->due_at->subHours(10));
            $handIn->handle($h, $ziad, null, $this->recording());
        }

        CarbonImmutable::setTestNow($realNow);
        $overdue->handle();
        $missed->handle();
        CarbonImmutable::setTestNow();
    }

    /** Two seconds of a quiet tone, so the player in the teacher area has something to play. */
    private function recording(): UploadedFile
    {
        $rate = 8000;
        $samples = '';
        for ($i = 0; $i < 2 * $rate; $i++) {
            $samples .= pack('v', (int) (900 * sin(2 * M_PI * 440 * $i / $rate)));
        }
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
        $path = tempnam(sys_get_temp_dir(), 'rec');
        file_put_contents($path, $wav);

        return new UploadedFile($path, 'تسجيل-زياد.wav', 'audio/wav', null, true);
    }
}
