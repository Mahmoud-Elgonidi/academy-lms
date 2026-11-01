<?php

namespace Modules\Quran\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Actions\Exams\BookQuranExam;
use Modules\Quran\Actions\Exams\RecordQuranExam;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Actions\Plans\StartQuranPlan;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Domain\Models\Session;

/**
 * The demo's Quran program (docs/architecture/02, section 26), through the
 * real actions: the academy holds exams and gives certificates its families
 * see; two ready tracks as its programs (Juz Amma by dictation for children,
 * the whole Quran backward from an-Nas); each demo learner on a Quran course
 * of their own on a plan (Yousef and Karim on Juz Amma; Hamza and Fahd on
 * the whole Quran, placed as knowing its last part already). Yousef's last lessons are recorded as
 * his teacher would have recorded them (RecordQuranLesson, a few mistakes
 * here and there); the first surah he finished was examined by another
 * teacher of the Quran the next day, passed with no mistake (excellent) and
 * earned a certificate (RecordQuranExam → IssueQuranCertificate); the next
 * stop he reached waits, suggested, to be booked.
 *
 * Only while the demo's plan has the Quran and the server has its text
 * (programs other than the Noorani Qaida need the mushaf); the settings and
 * the history only the first time (no plan yet), and a course that already
 * has a plan is left as it is, so it can run again on every deploy
 * (DemoCatchUpSeeder).
 */
final class DemoQuranPlansSeeder extends Seeder
{
    /** Each demo learner (by account) and the ready track their Quran course follows. */
    public const PLANS = ['yousef' => 'juz_amma_talqin', 'hamza' => 'full_backward', 'karim' => 'juz_amma_talqin', 'fahd' => 'full_backward'];

    /** What a learner on the whole Quran already knows when they start (their placement): the last part, well. */
    private const PLACEMENT = ['juz' => [30], 'strength' => 'good'];

    /** How many of Yousef's last lessons held are recorded. */
    private const RECORDED = 6;

    /** The mistakes in each recorded lesson's parts, by kind (the first lesson clean, then a few). */
    private const MISTAKES = [
        ['new' => [], 'near' => [], 'far' => []],
        ['new' => ['hesitation' => 1], 'near' => ['tajweed' => 1], 'far' => []],
        ['new' => ['wrong' => 1, 'tajweed' => 1], 'near' => [], 'far' => ['hesitation' => 1]],
        ['new' => ['hesitation' => 2], 'near' => ['wrong' => 1], 'far' => []],
    ];

    /** The real moment the seeder runs (each step sets the clock to its own moment). */
    private CarbonImmutable $now;

    public function run(QuranArea $area, QuranText $quran, SaveQuranProgram $programs, SaveQuranSettings $settings, StartQuranPlan $start): void
    {
        if (! $area->on() || ! $quran->available()) {
            return;
        }
        $this->now = CarbonImmutable::now();
        $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');
        $first = ! QuranPlan::query()->exists();
        if ($first) {
            $settings->handle([
                'exams_enabled' => true, 'examiner' => 'other_teacher', 'certificates_enabled' => true, 'certificates_family' => true,
                'certificate_title' => 'شهادة حفظ', 'certificate_signature' => 'هالة منصور، مديرة الأكاديمية',
            ]);
        }

        $history = null;
        foreach (self::PLANS as $student => $preset) {
            $course = $this->course($student);
            if ($course === null || QuranPlan::query()->where('enrollment_id', $course->id)->exists()) {
                continue;
            }
            $program = $this->program($programs, $preset, $owner);
            $held = $student === 'yousef' && $first ? $this->held($course->id) : collect();
            // A plan started before the lessons it records (an hour before the first), else now.
            CarbonImmutable::setTestNow($held->isEmpty() ? null : $held->first()->starts_at->subHour());
            try {
                $plan = $start->handle($course->id, $program->id, $preset === 'full_backward' ? ['placement' => self::PLACEMENT] : [], $owner);
            } catch (ValidationException) {
                // A course that cannot take a plan now (ended since, in a group): left as it is.
                continue;
            } finally {
                CarbonImmutable::setTestNow();
            }
            if ($held->isNotEmpty()) {
                $history = [$plan, $held, $course];
            }
        }
        if ($history !== null) {
            $this->history(...$history);
        }
    }

    /** The learner's Quran course still taught, not in a group. */
    private function course(string $student): ?object
    {
        return DB::table('enrollments as e')
            ->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('u.email', "{$student}@demo.test")->where('s.kind', 'quran')->whereIn('e.status', ['active', 'pending_payment'])
            ->whereNotExists(fn ($q) => $q->from('group_members as m')->whereColumn('m.enrollment_id', 'e.id')->whereNull('m.left_on'))
            ->orderBy('e.created_at')->first(['e.id', 'e.student_id', 'e.teacher_id']);
    }

    /** The ready track's program, made once, under its Arabic name (the demo's language). */
    private function program(SaveQuranProgram $programs, string $preset, string $owner): QuranProgram
    {
        return QuranProgram::query()->where('preset', $preset)->whereNull('archived_at')->orderBy('created_at')->first()
            ?? $programs->fromPreset($preset, trans('quran::programs.presets.'.$preset.'.name', [], 'ar'), $owner);
    }

    /**
     * The course's last lessons held, oldest first.
     *
     * @return Collection<int, Session>
     */
    private function held(string $enrollmentId): Collection
    {
        return Session::query()->where('enrollment_id', $enrollmentId)->where('type', 'regular')->where('status', 'completed')
            ->where('ends_at', '<', $this->now->subHour())
            ->orderByDesc('starts_at')->take(self::RECORDED)->get()->reverse()->values();
    }

    /**
     * Yousef's lessons recorded in turn, each at its own moment; the first stop he passes examined the next day and
     * passed with its certificate; the next stop he reaches waits, suggested, and the lessons after it stay unrecorded.
     *
     * @param  Collection<int, Session>  $held
     */
    private function history(QuranPlan $plan, Collection $held, object $course): void
    {
        $plans = app(Plans::class);
        try {
            foreach ($held->values() as $i => $lesson) {
                $portion = $plans->portion($plan->refresh());
                if ($portion === null) {
                    return;
                }
                $parts = array_filter(self::MISTAKES[$i % count(self::MISTAKES)], fn (string $part) => $portion->has($part), ARRAY_FILTER_USE_KEY);
                $now = $lesson->starts_at->addMinutes(25);
                CarbonImmutable::setTestNow($now);
                app(RecordQuranLesson::class)->handle((string) $lesson->id, (string) $lesson->teacher_id,
                    ['parts' => array_map(fn (array $mistakes) => ['mistakes' => $mistakes], $parts)], $now);

                $suggested = QuranExam::query()->where('student_id', $course->student_id)->where('status', 'suggested')->orderBy('created_at')->first();
                if ($suggested === null) {
                    continue;
                }
                if (QuranExam::query()->where('student_id', $course->student_id)->where('status', 'passed')->exists()) {
                    // The next stop reached waits for the office to book it.
                    return;
                }
                $this->examine($suggested, $lesson, $held->get($i + 1), $course);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /** The exam booked with another teacher of the Quran for the day after the lesson that reached it, held, and passed with no mistake. */
    private function examine(QuranExam $exam, Session $after, ?Session $next, object $course): void
    {
        $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');
        $examiners = DB::table('user_roles as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.role', 'teacher')->where('u.id', '!=', $course->teacher_id)->where('u.email', 'like', '%@demo.test')
            ->whereIn('u.email', ['mahmoud@demo.test', 'sara@demo.test', 'mona@demo.test'])->orderBy('u.email')->pluck('u.id')->map(fn ($id) => (string) $id);
        $latest = min(($next?->starts_at ?? $this->now)->subHours(2)->getTimestamp(), $this->now->subHours(2)->getTimestamp());
        $day = $after->ends_at->setTimezone('Asia/Riyadh')->addDay()->startOfDay();

        foreach ([16, 15, 14, 13, 17, 12] as $hour) {
            $at = $day->setTime($hour, 0)->utc();
            if ($at->getTimestamp() + 30 * 60 > $latest) {
                continue;
            }
            foreach ($examiners as $examiner) {
                CarbonImmutable::setTestNow($after->ends_at->addMinutes(20));
                try {
                    $booked = app(BookQuranExam::class)->handle($exam, $examiner, $at, 30, $owner);
                } catch (ValidationException) {
                    // That examiner (or Yousef) is busy then, or may not examine him.
                    continue;
                }
                $session = Session::query()->findOrFail((string) $booked->session_id);
                CarbonImmutable::setTestNow($session->starts_at->addMinute());
                app(RecordParticipantJoin::class)->handle($session, $examiner);
                CarbonImmutable::setTestNow($session->starts_at->addMinutes(2));
                app(RecordParticipantJoin::class)->handle($session->refresh(), (string) $course->student_id);
                $now = $session->starts_at->addMinutes(20);
                CarbonImmutable::setTestNow($now);
                app(RecordQuranExam::class)->handle($booked, $examiner, ['mistakes' => []], $now);
                CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
                app(ApplyAttendanceDeadlines::class)->handle();

                return;
            }
        }
    }
}
