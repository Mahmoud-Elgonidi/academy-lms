<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Proofs\ApprovePaymentProof;
use Modules\Billing\Actions\Proofs\RejectPaymentProof;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Models\PaymentProof;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\Room;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\Homework;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;
use RuntimeException;

/**
 * Puts back what the portal's end-to-end tests use up in the demo academy
 * (apps/web/e2e, run by its global setup before every run), so each test
 * finds its state at any hour and on a demo seeded days ago: never in
 * production, and only the demo academy's demo accounts.
 *
 * Through the real actions where the domain keeps history (a waiting proof
 * is turned down, an invoice is sold, homework is set and handed in, past
 * lessons happen as the sweepers would have them), and by removing only
 * what earlier runs made where nothing else depends on it (a week's chest
 * opened, cheers, ratings, eggs hatched, rounds left open, their homework).
 * What the tests need (the homework's titles, the academy's own host) is
 * printed as one line, `E2E_FIXTURES {json}`. What depends on the moment a
 * test runs is done when it asks, through artisan tinker: a lesson to go
 * into now (`lessonNow`) and staff approving a receipt (`approveProof`).
 */
final class E2eResetSeeder extends Seeder
{
    /** Homework the tests find by title: Ziad hands the first in in words, Khaled marks the second (a recording). */
    public const HAND_IN = 'اكتب ثلاث جمل فيها فعل ماضٍ';

    public const TO_MARK = 'سمّع الفقرة بصوتك';

    private CarbonImmutable $now;

    /** When the demo was seeded: what the seeders made is dated before it, what people did after. */
    private CarbonImmutable $seeded;

    /** @var array<string, string> user ids by demo key */
    private array $ids = [];

    public function run(TenantDirectory $directory, TenantContext $tenants): void
    {
        $academy = self::demo($directory);
        $this->now = CarbonImmutable::now();

        $fixtures = $tenants->run($academy, function () {
            $this->ids = DB::table('users')->where('email', 'like', '%@demo.test')->pluck('id', 'email')
                ->mapWithKeys(fn ($id, $email) => [str_replace('@demo.test', '', (string) $email) => (string) $id])->all();
            foreach (['owner', 'accountant', 'khaled', 'ziad', 'ziad.guardian', 'yousef', 'yousef.guardian', 'maryam'] as $key) {
                if (! isset($this->ids[$key])) {
                    throw new RuntimeException("The demo account {$key}@demo.test is missing: seed the demo first.");
                }
            }
            $this->seeded = CarbonImmutable::parse((string) DB::table('users')->where('id', $this->ids['owner'])->value('created_at'));
            AuditActor::use(new AuditActor('user', $this->ids['owner'], (string) DB::table('users')->where('id', $this->ids['owner'])->value('name')));

            try {
                foreach (['ziad', 'yousef', 'maryam'] as $student) {
                    $this->livePast($student);
                    $this->handInLastWeek($student);
                }
                $this->play();
                $this->chests();
                $this->homework();
                $this->eggs();
                $this->cheersAndRatings();
                $this->money();
                $this->lessonsAhead();
                $this->practiceHomework();
            } finally {
                CarbonImmutable::setTestNow();
                AuditActor::forget();
            }

            return ['homework' => ['hand_in' => self::HAND_IN, 'to_mark' => self::TO_MARK]];
        });
        // Its own host ({slug}.{platform domain}), as a browser reaches it on a server (here perhaps without DNS).
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);
        $fixtures['academy_host'] = $academy->slug.'.'.config('platform.domain').($port ? ":{$port}" : '');

        $this->command?->line('E2E_FIXTURES '.json_encode($fixtures, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Staff (the accountant) approve the transfer receipt Ziad's guardian sent with this reference, for what it said:
     * the end-to-end tests' stand-in for /admin (php artisan tinker --execute). Returns the invoice's number.
     */
    public static function approveProof(string $reference): string
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($reference) {
            $payer = (string) DB::table('users')->where('email', 'ziad.guardian@demo.test')->value('id');
            $accountant = DB::table('users')->where('email', 'accountant@demo.test')->first(['id', 'name']);
            $proof = PaymentProof::query()->where('payer_id', $payer)->where('reference', $reference)->where('status', 'pending')->firstOrFail();
            AuditActor::use(new AuditActor('user', (string) $accountant->id, (string) $accountant->name));
            try {
                $invoice = app(ApprovePaymentProof::class)->handle($proof->id, Money::of((int) $proof->amount_minor, (string) $proof->currency),
                    (string) $proof->method, $proof->reference, CarbonImmutable::now(), null, (string) $accountant->id);
            } finally {
                AuditActor::forget();
            }

            return (string) $invoice->number;
        });
    }

    private static function demo(TenantDirectory $directory): TenantRef
    {
        if (app()->isProduction()) {
            throw new RuntimeException('E2eResetSeeder changes the demo academy for the end-to-end tests: never in production.');
        }

        return $directory->bySlug('demo') ?? throw new RuntimeException('No demo academy here: run php artisan db:seed first.');
    }

    /**
     * Lessons that ended since the demo was seeded happen as they would have: both come in a minute or two after
     * the start and the attendance sweep closes them (a report waits, the week's missions fill); one still unpaid
     * goes to rule 16's sweep first, as the scheduler would have sent it.
     */
    private function livePast(string $student): void
    {
        $id = $this->ids[$student];
        $past = Session::query()->where('status', 'scheduled')->where('ends_at', '<', $this->now->subMinutes(15))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $id)->where('role', 'student'))
            ->orderBy('starts_at')->get();

        foreach ($past as $session) {
            if ($session->funding_status->value === 'unfunded' && $session->enrollment_id !== null) {
                CarbonImmutable::setTestNow($session->starts_at->subMinute());
                app(CancelUnpaidSessions::class)->handle($session->starts_at->subMinute());
                if ($session->refresh()->status->value !== 'scheduled') {
                    continue;
                }
            }
            CarbonImmutable::setTestNow($session->starts_at->addMinute());
            app(RecordParticipantJoin::class)->handle($session->refresh(), $session->teacher_id);
            CarbonImmutable::setTestNow($session->starts_at->addMinutes(2));
            app(RecordParticipantJoin::class)->handle($session->refresh(), $id);
            CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
            app(ApplyAttendanceDeadlines::class)->handle();
        }
        CarbonImmutable::setTestNow();
    }

    /** Last week's homework (Saturday to Friday on the student's clock) handed in before it was due, so last week's chest is ready. */
    private function handInLastWeek(string $student): void
    {
        $id = $this->ids[$student];
        $week = $this->now->setTimezone($this->zone($id))->startOfWeek(ChallengeCatalog::WEEK_STARTS)->startOfDay();
        $open = Homework::query()->where('student_id', $id)->whereIn('accepts', ['any', 'text', 'audio'])
            ->where('due_at', '>=', $week->subWeek()->utc())->where('due_at', '<', $week->utc())
            ->whereDoesntHave('submissions')->get();
        foreach ($open as $homework) {
            CarbonImmutable::setTestNow($homework->due_at->subHour());
            app(SubmitHomework::class)->handle($homework, $id, $homework->accepts === 'audio' ? null : 'تم 👍', $homework->accepts === 'audio' ? $this->recording() : null);
        }
        CarbonImmutable::setTestNow();
    }

    /** Play time back as the demo set it: no round left open, Yousef's guardian's 20 minutes, no night lock, Ziad and Maryam on the academy's. */
    private function play(): void
    {
        $students = [$this->ids['ziad'], $this->ids['yousef'], $this->ids['maryam']];
        DB::table('game_plays')->whereIn('student_id', $students)->whereNull('finished_at')->delete();
        DB::table('game_limits')->whereIn('student_id', [$this->ids['ziad'], $this->ids['maryam']])->delete();
        app(SaveGameLimit::class)->handle($this->ids['yousef'], $this->ids['yousef.guardian'], true, 20, false);
    }

    /** The week's chests Yousef and Ziad opened in earlier runs, closed again. */
    private function chests(): void
    {
        DB::table('challenge_claims')->whereIn('student_id', [$this->ids['ziad'], $this->ids['yousef']])
            ->where('challenge', 'chest')->where('claimed_at', '>', $this->seeded)->delete();
    }

    /** Ziad's two homework for the tests, made again: one to hand in in words, one handed in as a recording for Khaled to mark. */
    private function homework(): void
    {
        $ziad = $this->ids['ziad'];
        $course = $this->course('ziad');
        Homework::query()->where('student_id', $ziad)->whereIn('title', [self::HAND_IN, self::TO_MARK])->get()->each->delete();

        $set = fn (string $title, string $accepts, int $days) => app(AssignHomework::class)->handle([
            'teacher_id' => $this->ids['khaled'], 'student_id' => $ziad, 'enrollment_id' => $course,
            'title' => $title, 'accepts' => $accepts, 'due_at' => $this->now->addDays($days)->setTime(18, 0),
        ]);
        $set(self::HAND_IN, 'text', 3);
        app(SubmitHomework::class)->handle($set(self::TO_MARK, 'audio', 2), $ziad, null, $this->recording());
    }

    /**
     * Yousef has an egg ready to hatch: the creatures earlier runs hatched from stars go back into their eggs, and
     * if his stars still fill none, his latest one from stars does too (the test hatches it again).
     */
    private function eggs(): void
    {
        $yousef = $this->ids['yousef'];
        $fromStars = fn () => DB::table('creature_finds')->where('student_id', $yousef)->where('source', 'stars');
        $fromStars()->where('found_at', '>', $this->seeded)->delete();
        $stars = app(LearningApi::class)->stars($yousef);
        while (intdiv($stars, 12) <= $fromStars()->count()) {
            $last = $fromStars()->where('buddy', false)->whereNull('nickname')->orderByDesc('egg')->orderByDesc('found_at')->value('id');
            if ($last === null) {
                throw new RuntimeException('Yousef has no egg to hatch and no creature from stars to give back: seed the demo again.');
            }
            DB::table('creature_finds')->where('id', $last)->delete();
        }
    }

    /** Ziad's guardian may cheer again today and rate the lessons earlier runs rated. */
    private function cheersAndRatings(): void
    {
        DB::table('cheers')->where('from_id', $this->ids['ziad.guardian'])->where('created_at', '>', $this->seeded)->delete();
        DB::table('lesson_ratings')->where('rater_id', $this->ids['ziad.guardian'])->where('created_at', '>', $this->seeded)->delete();
    }

    /** Ziad's guardian owes an invoice that takes a receipt: one left waiting by a run is turned down, and when everything is paid, an extra lesson is sold. */
    private function money(): void
    {
        $payer = $this->ids['ziad.guardian'];
        PaymentProof::query()->where('payer_id', $payer)->where('status', 'pending')->get()
            ->each(fn (PaymentProof $p) => app(RejectPaymentProof::class)->handle($p->id, 'إيصال تجريبي من اختبار قديم.', $this->ids['accountant']));

        $owed = DB::table('invoices as i')->join('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'i.academy_id')->on('ba.id', '=', 'i.billing_account_id'))
            ->where('ba.payer_id', $payer)->where('i.status', 'issued')->whereColumn('i.paid_minor', '<', 'i.total_minor')->exists();
        if (! $owed) {
            app(BillingApi::class)->sellExtraLessons($this->course('ziad'), 1, $this->ids['owner']);
        }
    }

    /** Ziad has lessons ahead to cancel: his weekly times run on to the horizon, and two more are booked when fewer than three are left. */
    private function lessonsAhead(): void
    {
        $course = $this->course('ziad');
        app(MaterializeSessions::class)->handle([$course]);
        app(FundSessions::class)->handle($course);

        $ahead = fn () => Session::query()->where('enrollment_id', $course)->where('status', 'scheduled')->where('starts_at', '>', $this->now->addDays(2))->count();
        $subject = (string) DB::table('enrollments')->where('id', $course)->value('subject_id');
        for ($day = 21; $ahead() < 3 && $day < 90; $day++) {
            try {
                app(ScheduleSession::class)->handle(new NewSession(
                    teacherId: $this->ids['khaled'], studentId: $this->ids['ziad'], subjectId: $subject,
                    startsAt: $this->now->addDays($day)->setTime(13, 0), durationMin: 45, anchorTimezone: 'Africa/Cairo',
                    funding: 'unfunded', enrollmentId: $course,
                ));
            } catch (ValidationException) {
                // Booked that hour: the next day.
            }
        }
    }

    /**
     * A lesson whose room is open now and for a quarter of an hour at least, for Khaled (`teacher`) or Ziad
     * (`student`), on its teacher's outside link, so the class page opens without a video server: one already
     * there (scheduled or under way), else one booked now, Khaled and Ziad together when both are free, else
     * Ziad with any teacher who is, or Khaled with any of his students who is. It starts today on the browser's
     * clock (the tests' Africa/Cairo) and the person's own, so it is among the day's lessons on both portals.
     * The class page tests call it just before they go in (php artisan tinker), so it holds at any hour and
     * however long the run took. Returns the lesson's id.
     */
    public static function lessonNow(string $who): string
    {
        $academy = self::demo(app(TenantDirectory::class));
        $role = match ($who) {
            'teacher', 'student' => $who,
            default => throw new RuntimeException("Not teacher or student: {$who}."),
        };

        return app(TenantContext::class)->run($academy, function () use ($academy, $role): string {
            $ids = DB::table('users')->whereIn('email', ['khaled@demo.test', 'ziad@demo.test'])->pluck('id', 'email');
            [$khaled, $ziad] = [(string) $ids['khaled@demo.test'], (string) $ids['ziad@demo.test']];
            $me = $role === 'teacher' ? $khaled : $ziad;
            $now = CarbonImmutable::now();
            // The student finds the lesson on their list, which starts at the start of today; the teacher opens it
            // by its id, so for them a lesson running since before midnight will do (one that started at 23:45 is
            // still on at 00:10, and keeps everyone in it busy).
            $today = $role === 'teacher' ? $now->subDay() : collect(['Africa/Cairo', (string) (DB::table('users')->where('id', $me)->value('timezone') ?: 'UTC')])
                ->map(fn (string $zone) => $now->setTimezone($zone)->startOfDay()->utc())->max();

            // A room opens 10 minutes before the lesson and closes 10 minutes after its end: one open for 5 minutes more will do.
            $lesson = Session::query()->whereIn('status', ['scheduled', 'in_progress'])
                ->where('starts_at', '>=', $today)->where('starts_at', '<=', $now->addMinutes(10))->where('ends_at', '>=', $now->subMinutes(5))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $me)->where('role', $role))
                ->orderByDesc('ends_at')->first();

            if ($lesson === null) {
                $course = DB::table('enrollments')->where('student_id', $ziad)->orderBy('created_at')->first(['subject_id']);
                $others = $role === 'teacher'
                    ? DB::table('enrollments')->where('teacher_id', $khaled)->where('student_id', '!=', $ziad)->orderBy('created_at')->pluck('student_id')
                    : DB::table('user_roles')->where('role', 'teacher')->where('user_id', '!=', $khaled)->orderBy('user_id')->pluck('user_id');
                $pairs = [[$khaled, $ziad], ...$others->unique()->map(fn ($other) => $role === 'teacher' ? [$khaled, (string) $other] : [(string) $other, $ziad])];
                // Now, or a few minutes on when one of them is finishing a lesson; a little before when that is today too.
                $starts = collect([...range(0, 10), ...range(-1, -5)])->map(fn (int $m) => $now->addMinutes($m)->startOfMinute())->filter(fn ($at) => $at >= $today);
                // A short lesson when they are between two of theirs.
                foreach ([45, 20] as $minutes) {
                    foreach ($pairs as [$teacher, $student]) {
                        foreach ($starts as $at) {
                            try {
                                $lesson = app(ScheduleSession::class)->handle(new NewSession(
                                    teacherId: $teacher, studentId: $student, subjectId: (string) $course->subject_id, startsAt: $at,
                                    durationMin: $minutes, anchorTimezone: 'Africa/Cairo',
                                ));
                                break 3;
                            } catch (ValidationException) {
                                // One of them has a lesson then.
                            }
                        }
                    }
                }
            }
            if ($lesson === null) {
                throw new RuntimeException("No lesson could be opened now for the {$role}: everyone they could have it with is busy.");
            }

            if (! DB::table('meeting_links')->where('teacher_id', $lesson->teacher_id)->exists()) {
                app(SaveMeetingLink::class)->handle($lesson->teacher_id, 'https://meet.google.com/noor-demo-e2e');
            }
            // The tests go into lessons by the teacher's outside link: the academy's choice, so a room follows it.
            app(SaveClassroomSettings::class)->handle('external');
            Room::query()->updateOrCreate(['session_id' => $lesson->id], ['provider' => 'external', 'name' => Room::nameFor($academy->id, $lesson->id)]);

            return (string) $lesson->id;
        });
    }

    /** Maryam has a game round as homework to play, as her teacher set it in the demo. */
    private function practiceHomework(): void
    {
        $maryam = $this->ids['maryam'];
        $waiting = Homework::query()->where('student_id', $maryam)->where('accepts', 'practice')->where('status', 'assigned')->where('due_at', '>', $this->now->addDay())->exists();
        if ($waiting) {
            return;
        }
        $course = $this->course('maryam');
        $nodes = DB::table('homework')->where('student_id', $maryam)->where('accepts', 'practice')->whereNotNull('practice_nodes')->orderByDesc('created_at')->value('practice_nodes');
        app(AssignHomework::class)->handle([
            'teacher_id' => (string) DB::table('enrollments')->where('id', $course)->value('teacher_id'),
            'student_id' => $maryam, 'enrollment_id' => $course,
            'title' => 'جولة تدريب على الإظهار والإدغام', 'accepts' => 'practice',
            'due_at' => $this->now->addDays(2)->setTime(18, 0), 'practice_nodes' => $nodes === null ? null : json_decode((string) $nodes, true),
        ]);
    }

    /** The student's first course (the one with history in the demo). */
    private function course(string $student): string
    {
        return (string) (DB::table('enrollments')->where('student_id', $this->ids[$student])->orderBy('created_at')->value('id')
            ?? throw new RuntimeException("{$student}@demo.test has no course: seed the demo first."));
    }

    private function zone(string $userId): string
    {
        return (string) (DB::table('users')->where('id', $userId)->value('timezone') ?: 'UTC');
    }

    /** Two seconds of a quiet tone (as the demo's recordings), so the teacher's player has something to play. */
    private function recording(): UploadedFile
    {
        $rate = 8000;
        $samples = '';
        for ($i = 0; $i < 2 * $rate; $i++) {
            $samples .= pack('v', (int) (900 * sin(2 * M_PI * 440 * $i / $rate)));
        }
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;
        $path = tempnam(sys_get_temp_dir(), 'e2e');
        file_put_contents($path, $wav);

        return new UploadedFile($path, 'recording-e2e.wav', 'audio/wav', null, true);
    }
}
