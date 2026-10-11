<?php

namespace Modules\Learning\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\MarkMissedHomework;
use Modules\Learning\Actions\MarkOverdueReports;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\ReviewSessionReport;
use Modules\Learning\Actions\SaveReportTemplate;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;

/**
 * Demo reports and homework through the real actions. Every enrollment
 * whose subject has one program follows it. A month of reports for the three
 * students with history (one per look): Ziad's teacher (khaled@demo.test)
 * reported five of his six lessons, one of them late; a supervisor reviewed
 * the first; the last is overdue and waits in the teacher area with today's
 * plan filled in. Yousef finished his first island, Maryam her first level,
 * each with a lesson back for review. Homework in every state, including
 * voice recordings. The academy's own questions for three subjects (Quran
 * memorisation in two versions: a revision question and a follow-up only
 * the academy reads came in the middle of Yousef's month), answered in
 * every report; English has none.
 */
final class DemoLearningSeeder extends Seeder
{
    public function run(
        EnrollmentApi $enrollments,
        CurriculumApi $curriculum,
        SubmitSessionReport $submit,
        ReviewSessionReport $review,
        SaveReportTemplate $templates,
        SubmitHomework $handIn,
        ReviewHomework $mark,
        MarkOverdueReports $overdue,
        MarkMissedHomework $missed,
        AssignHomework $assign,
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
            // Yousef (9, Juz' Amma): the first island done, Al-Fatiha back for a review, halfway through the second; his last homework comes with a game round.
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
                    ['title' => 'سجّل سورة قريش', 'accepts' => 'audio', 'due_days' => 5, 'practice' => true]],
            ],
            // Maryam (16, Nour Al-Bayan): the first level done, one lesson back for review; she missed one lesson. Her teacher has just set a game round as homework.
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

        // The academy's own questions, by subject (Arabic and English words).
        $q = fn (string $ar, string $en, string $type, array $more = []) => ['label_ar' => $ar, 'label_en' => $en, 'type' => $type] + $more;
        $choices = fn (array $pairs) => ['options' => array_map(fn ($p) => ['ar' => $p[0], 'en' => $p[1]], $pairs)];
        $memorisation = [
            $q('الآيات اللي اتحفظت', 'Verses memorised', 'number', ['required' => true, 'min' => 0, 'max' => 300, 'unit_ar' => 'آية', 'unit_ar_few' => 'آيات', 'unit_en' => 'verses']),
            $q('التجويد', 'Tajweed', 'choice', $choices([['ممتاز', 'Excellent'], ['كويس', 'Good'], ['محتاج شغل', 'Needs work']])),
        ];
        $questions = [
            'Quran memorisation' => $memorisation,
            'Tajweed' => [
                $q('الأحكام اللي اتطبقت صح', 'Rules applied correctly', 'rating', ['required' => true]),
                $q('القراءة من المصحف', 'Reading from the Mushaf', 'choice', $choices([['من غير أخطاء', 'No mistakes'], ['أخطاء قليلة', 'A few mistakes'], ['محتاجة تدريب', 'Needs practice']])),
                $q('صفحات القراءة', 'Pages read', 'number', ['min' => 0, 'max' => 40, 'unit_ar' => 'صفحة', 'unit_ar_few' => 'صفحات', 'unit_en' => 'pages']),
            ],
            'Arabic grammar' => [
                $q('الإعراب', 'Parsing', 'rating', ['required' => true]),
                $q('الكلمات الجديدة', 'New words', 'number', ['min' => 0, 'max' => 50, 'unit_ar' => 'كلمة', 'unit_ar_few' => 'كلمات', 'unit_en' => 'words']),
                $q('ملاحظة للمشرف', 'Note for the supervisor', 'text', ['family' => false]),
            ],
        ];
        $subject = fn (string $name) => (string) DB::table('subjects')->where('name_en', $name)->value('id');
        $first = SessionReport::query()->min('session_starts_at');
        CarbonImmutable::setTestNow(($first ? CarbonImmutable::parse($first) : $realNow)->subDays(2));
        foreach ($questions as $name => $fields) {
            if ($subject($name) !== '') {
                $templates->handle($subject($name), $fields, $owner);
            }
        }

        // Answers by report, in the order of the scripts (keys as the templates gave them).
        $answers = [
            'ziad' => [['f1' => 4, 'f2' => 8], ['f1' => 4, 'f2' => 6], ['f1' => 5, 'f2' => 10],
                ['f1' => 3, 'f2' => 5, 'f3' => 'الواجب اتأخر يومين؛ اتكلمت مع ولي الأمر.'], ['f1' => 4, 'f2' => 7]],
            'yousef' => [['f1' => 7, 'f2' => 'o2'], ['f1' => 11, 'f2' => 'o1'], ['f1' => 9, 'f2' => 'o2'],
                ['f1' => 8, 'f2' => 'o1', 'f3' => true, 'f4' => false], ['f1' => 12, 'f2' => 'o1', 'f3' => true, 'f4' => false],
                ['f1' => 6, 'f2' => 'o3', 'f3' => false, 'f4' => true], ['f1' => 10, 'f2' => 'o1', 'f3' => true, 'f4' => false]],
            'maryam' => [['f1' => 3, 'f2' => 'o3', 'f3' => 2], ['f1' => 4, 'f2' => 'o2', 'f3' => 3], ['f1' => 5, 'f2' => 'o1', 'f3' => 4],
                ['f1' => 4, 'f2' => 'o2', 'f3' => 3], ['f1' => 5, 'f2' => 'o1', 'f3' => 5], ['f1' => 4, 'f2' => 'o2', 'f3' => 4]],
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
                if ($key === 'yousef' && $i === 3 && $subject('Quran memorisation') !== '') {
                    // Halfway through the month the academy asks two more questions about memorisation.
                    CarbonImmutable::setTestNow($report->session_starts_at->subHours(3));
                    $templates->handle($subject('Quran memorisation'), [...ReportTemplate::currentFor($subject('Quran memorisation'))->fields,
                        $q('راجع المحفوظ', 'Revised what he knows', 'yes_no', ['required' => true]),
                        $q('محتاج متابعة من المشرف', 'Needs a supervisor\'s follow-up', 'yes_no', ['family' => false]),
                    ], $owner);
                }
                CarbonImmutable::setTestNow($report->session_starts_at->addMinutes(45)->addHours($after));
                $submit->handle($report, $report->teacher_id, [
                    'answers' => $answers[$key][$i] ?? [],
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

        // Maryam's teacher has just set a game round on izhar and idgham: playing it hands it in.
        [$maryam, $maryamCourse] = $reportsFor['maryam'] ?? [null, null];
        $versionId = $maryamCourse ? $enrollments->find($maryamCourse)?->curriculumVersionId : null;
        if ($versionId !== null) {
            $lessons = collect($curriculum->tree($versionId)->lessons())->whereIn('titleEn', ['Izhar', 'Idgham'])->pluck('id')->values()->all();
            CarbonImmutable::setTestNow($realNow->subHours(20));
            $assign->handle([
                'teacher_id' => (string) DB::table('enrollments')->where('id', $maryamCourse)->value('teacher_id'),
                'student_id' => $maryam, 'enrollment_id' => $maryamCourse,
                'title' => 'جولة تدريب على الإظهار والإدغام', 'accepts' => 'practice',
                'due_at' => $realNow->addDays(2)->setTime(18, 0), 'practice_nodes' => $lessons,
            ]);
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
