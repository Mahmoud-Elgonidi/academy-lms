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
 * whose subject has one program follows it. A month of reports for the three
 * students with history (one per look): Ziad's teacher (khaled@demo.test)
 * reported five of his six lessons, one of them late; a supervisor reviewed
 * the first; the last is overdue and waits in the teacher area with today's
 * plan filled in. Yousef finished his first island, Maryam her first level,
 * each with a lesson back for review. Homework in every state, including
 * voice recordings.
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

        // [items [lesson index, outcome, score], engagement, overall, note, hours after the lesson, homework]
        $scripts = [
            // Ziad (middle school, Arabic grammar): five of six reported, one late; the last is overdue.
            'ziad' => [
                [[[0, 'mastered', 5], [1, 'introduced', 3]], 5, 4, 'بداية ممتازة: زياد بيفرّق بين الاسم والفعل بسهولة.', 2,
                    ['title' => 'اكتب خمس جمل فيها أسماء، وحدّد علامة كل اسم', 'accepts' => 'text', 'due_days' => 3]],
                [[[1, 'mastered', 4], [2, 'introduced', 3]], 4, 4, 'المثنى تمام، الجمع محتاج تدريب بسيط.', 20, null],
                [[[2, 'mastered', 5], [3, 'practiced', 3]], 4, 5, 'تحسن واضح في التذكير والتأنيث.', 3,
                    ['title' => 'صرّف الفعل "كتب" في الماضي مع كل الضمائر', 'accepts' => 'any', 'due_days' => 3]],
                [[[3, 'mastered', 4], [4, 'introduced', 3], [0, 'practiced', 5]], 3, 4, 'كان مرهق شوية النهارده، بس راجعنا علامات الاسم كويس.', 1, null],
                [[[4, 'practiced', 4]], 5, 4, 'المضارع محتاج حصة كمان قبل ما ننتقل للأمر.', 2,
                    ['title' => 'سجّل نفسك وانت بتقرا فقرة الكتاب ص 14 وتحدد الأفعال المضارعة', 'accepts' => 'audio', 'due_days' => 4]],
            ],
            // Yousef (9, Juz' Amma): the first island done, Al-Fatiha back for a review, halfway through the second.
            'yousef' => [
                [[[0, 'introduced', 4]], 5, 5, 'بداية حلوة! يوسف حافظ الفاتحة كويس، هنظبط المدود مع بعض.', 2,
                    ['title' => 'سمّع الفاتحة وسجّل صوتك', 'accepts' => 'audio', 'due_days' => 2]],
                [[[0, 'practiced', 5], [1, 'introduced', 4]], 5, 5, 'الفاتحة بقت ممتازة ⭐ وبدأنا الناس والفلق.', 3, null],
                [[[1, 'practiced', 5], [2, 'introduced', 3]], 4, 4, 'الناس والفلق تمام. الإخلاص محتاجة تكرار.', 2, null],
                [[[2, 'practiced', 4], [3, 'introduced', 4]], 5, 5, 'شاطر جدًا النهارده، سمّع من غير ما يبص.', 1,
                    ['title' => 'اقرا سورة النصر 3 مرات مع بابا أو ماما', 'accepts' => 'text', 'due_days' => 2]],
                [[[2, 'mastered', 5], [3, 'practiced', 4]], 5, 5, 'خلّص قصار السور كلها! 🎉', 2, null],
                [[[4, 'introduced', 4], [0, 'needs_review', 2]], 4, 4, 'بدأنا الكوثر والماعون. الفاتحة محتاجة مراجعة بسيطة في المدود.', 2, null],
                [[[4, 'practiced', 5], [5, 'introduced', 3]], 5, 5, 'الكوثر والماعون حفظهم كويس جدًا. الحصة الجاية قريش.', 2,
                    ['title' => 'سجّل سورة قريش', 'accepts' => 'audio', 'due_days' => 5]],
            ],
            // Maryam (16, Nour Al-Bayan): the first level done, one lesson back for review; she missed one lesson.
            'maryam' => [
                [[[0, 'introduced', 4]], 4, 4, 'مخارج الحروف كويسة، محتاجين نشتغل على الحروف الحلقية.', 3,
                    ['title' => 'اقري صفحة الحروف المفردة وسجّلي نفسك', 'accepts' => 'audio', 'due_days' => 3]],
                [[[0, 'practiced', 4]], 4, 4, 'تحسن في الحروف الحلقية.', 2, null],
                [[[0, 'mastered', 5], [1, 'introduced', 4]], 5, 5, 'الحروف المفردة اتقفلت. بدأنا المركبة.', 2,
                    ['title' => 'حلّي تمرين الحروف المركبة ص 9', 'accepts' => 'any', 'due_days' => 3]],
                [[[1, 'mastered', 4], [2, 'introduced', 3]], 4, 4, 'المركبة تمام بعد الغياب. نكمّل الحركات.', 4, null],
                [[[2, 'mastered', 5], [3, 'introduced', 4]], 5, 5, 'خلّصت المستوى الأول. ممتازة في التنوين.', 2,
                    ['title' => 'استخرجي 10 أمثلة للإظهار من سورة البقرة', 'accepts' => 'text', 'due_days' => 4]],
                [[[3, 'mastered', 4], [1, 'needs_review', 2], [4, 'introduced', 3]], 4, 4, 'الإظهار تمام. الحروف المركبة محتاجة مراجعة سريعة، وبدأنا الإدغام.', 2, null],
            ],
        ];

        $reportsFor = [];
        foreach ($scripts as $key => $script) {
            $student = DB::table('users')->where('email', "{$key}@demo.test")->value('id');
            $enrollmentId = DB::table('enrollments')->where('student_id', $student)->orderBy('created_at')->value('id');
            $versionId = $enrollmentId ? $enrollments->find($enrollmentId)?->curriculumVersionId : null;
            if ($versionId === null) {
                continue;
            }
            $lesson = array_map(fn ($n) => $n->id, $curriculum->tree($versionId)->lessons());
            $reports = SessionReport::query()->where('enrollment_id', $enrollmentId)->where('kind', 'lesson')->orderBy('session_starts_at')->get();
            $reportsFor[$key] = [$student, $enrollmentId, $reports];

            foreach ($script as $i => [$items, $engagement, $overall, $note, $after, $homework]) {
                $report = $reports[$i] ?? null;
                if ($report === null) {
                    break;
                }
                CarbonImmutable::setTestNow($report->session_starts_at->addMinutes(45)->addHours($after));
                $submit->handle($report, $report->teacher_id, [
                    'engagement' => $engagement, 'overall' => $overall, 'notes_for_student' => $note,
                    'notes_internal' => $key === 'ziad' && $i === 3 ? 'ولي الأمر قال إن عنده امتحانات في المدرسة الأسبوع ده.' : null,
                    'items' => array_map(fn ($it) => ['node_id' => $lesson[$it[0]], 'outcome' => $it[1], 'score' => $it[2]], $items),
                    'homework' => $homework,
                ]);
            }
        }

        // Maryam's teacher wrote why she missed her lesson.
        if ($absence = SessionReport::query()->where('enrollment_id', $reportsFor['maryam'][1] ?? null)->where('kind', 'absence')->first()) {
            CarbonImmutable::setTestNow($absence->session_starts_at->addHours(3));
            $submit->handle($absence, $absence->teacher_id, ['absence_note' => 'مريم ماحضرتش وولي الأمر بلّغ بعدها إن عندها امتحان في المدرسة.']);
        }

        // A supervisor read Ziad's first report.
        if ($first = ($reportsFor['ziad'][2] ?? collect())->first()) {
            CarbonImmutable::setTestNow($first->refresh()->submitted_at->addDay());
            $review->handle($first, $owner, 'تقرير واضح. ياريت تضيف ملاحظة عن الواجب في الحصة الجاية.');
        }

        // Homework, in every state: [index, what is handed in (text, or null for a recording), hours before it is due, mark, comment]
        $handed = [
            'ziad' => [  // the first handed in and marked, the second missed, the recording handed in and waiting
                [0, "الكتابُ مفيدٌ.\nفي البيتِ حديقةٌ.\nالطالبُ مجتهدٌ.\nمحمدٌ في المسجدِ.\nالقلمُ جديدٌ.", 24, 4, 'ممتاز! انتبه للتنوين في الجملة الرابعة.'],
                [2, null, 10, null, null],
            ],
            'yousef' => [  // both early and marked; the last one still to do
                [0, null, 20, 5, 'صوتك جميل يا يوسف! ⭐'],
                [1, 'قريت سورة النصر 3 مرات مع ماما 😊', 12, 5, 'برافو!'],
            ],
            'maryam' => [  // three handed in on time, two marked
                [0, null, 30, 4, 'كويس جدًا. ركّزي على القلقلة.'],
                [1, 'حليت التمرين كله، عندي سؤال في رقم 4.', 6, 5, 'إجابة رقم 4 صح، هنشرحها الحصة الجاية.'],
                [2, 'أنعمت، من آمن، من هاد، عليمٌ حكيم، …', 15, null, null],
            ],
        ];
        foreach ($handed as $key => $items) {
            [$student, $enrollmentId] = $reportsFor[$key] ?? [null, null];
            $homework = $enrollmentId ? Homework::query()->where('enrollment_id', $enrollmentId)->orderBy('due_at')->get() : collect();
            foreach ($items as [$index, $text, $hoursEarly, $score, $comment]) {
                $h = $homework->get($index);
                if ($h === null || $h->due_at->subHours($hoursEarly)->greaterThan($realNow)) {
                    continue;   // not given yet, or not due for a while: still to do
                }
                CarbonImmutable::setTestNow($h->due_at->subHours($hoursEarly));
                $handIn->handle($h, $student, $text, $text === null ? $this->recording($key) : null);
                if ($score !== null && $h->due_at->addHours(5)->lessThan($realNow)) {
                    CarbonImmutable::setTestNow($h->due_at->addHours(5));
                    $mark->handle($h->refresh(), $h->teacher_id, $score, $comment);
                }
            }
        }

        CarbonImmutable::setTestNow($realNow);
        $overdue->handle();
        $missed->handle();
        CarbonImmutable::setTestNow();
    }

    /** Two seconds of a quiet tone, so the player in the teacher area has something to play. */
    private function recording(string $who): UploadedFile
    {
        $rate = 8000;
        $samples = '';
        for ($i = 0; $i < 2 * $rate; $i++) {
            $samples .= pack('v', (int) (900 * sin(2 * M_PI * 440 * $i / $rate)));
        }
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
        $path = tempnam(sys_get_temp_dir(), 'rec');
        file_put_contents($path, $wav);

        return new UploadedFile($path, "recording-{$who}.wav", 'audio/wav', null, true);
    }
}
