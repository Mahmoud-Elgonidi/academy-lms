<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Billing\Database\Seeders\DemoBillingSeeder;
use Modules\Billing\Models\Invoice;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Evaluation\Actions\RateLesson;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Payroll\Actions\ClosePayrollMonth;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Support\PayrollMonths;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\TenantContext;
use RuntimeException;
use SplPriorityQueue;
use Throwable;

/**
 * An academy of a paying customer's size, for measuring the heavy screens
 * (docs/architecture/03, section 13): 40 teachers on shifts, 400 students
 * (the children with guardians who consented, some of them siblings), about
 * 550 courses (a student with three of them: s001@load.test), a published
 * curriculum per subject, and thirteen weeks of history lived through the
 * real actions day by day: enrolments, invoices and renewals paid by each
 * payer's habit (a few left owing), lessons joined and closed by the sweeper
 * (some late, absent or cancelled), reports with homework handed in and
 * marked, ratings and teachers' pay; then three weeks ahead and the lessons
 * running now. Not part of DatabaseSeeder; never in production:
 *
 *   php artisan db:seed --class=LoadAcademySeeder --force
 *
 * Sizes: LOAD_TEACHERS, LOAD_STUDENTS, LOAD_WEEKS (and LOAD_SLUG) in the
 * environment. Sign in as owner@load.test, t01@load.test, s001@load.test or
 * g001@load.test (s001's guardian) with the demo password. Notifications are
 * not sent while it runs (the inboxes stay empty), to keep it under an hour.
 */
final class LoadAcademySeeder extends Seeder
{
    private const DOMAIN = 'load.test';

    /** Day pairs (ISO weekdays, Friday off): a student's courses take different pairs, so they never meet. */
    private const PAIRS = [[6, 2], [7, 3], [1, 4]];

    /** Teachers' bands on their own clock: [from hour, to hour). */
    private const BANDS = [[9, 16], [14, 21], [17, 23]];

    /** [minutes, a week, weight] */
    private const PLANS = [[30, 2, 35], [45, 2, 25], [30, 1, 20], [60, 2, 10], [45, 1, 10]];

    /** [subject en, subject ar, weight] */
    private const SUBJECTS = [
        ['Quran memorisation', 'قرآن · حفظ', 30], ['Tajweed', 'تجويد', 20], ['Arabic', 'لغة عربية', 20],
        ['English', 'إنجليزي', 20], ['Arabic grammar', 'نحو', 10],
    ];

    private const MALE = ['محمد', 'أحمد', 'محمود', 'خالد', 'عمر', 'يوسف', 'حسن', 'علي', 'إبراهيم', 'مصطفى', 'طارق', 'سامي', 'كريم', 'ياسر', 'هشام', 'عادل', 'فهد', 'سلمان', 'زياد', 'حمزة', 'آدم', 'مالك', 'نادر', 'وليد'];

    private const FEMALE = ['نور', 'سارة', 'منى', 'هالة', 'ريم', 'مريم', 'فاطمة', 'عائشة', 'ليلى', 'سلمى', 'هند', 'دينا', 'رنا', 'لينا', 'جنى', 'ليان', 'رهف', 'شهد', 'أسماء', 'نوال', 'منيرة', 'لولوة', 'غادة', 'إيمان'];

    private const FAMILY = ['العتيبي', 'القحطاني', 'المطيري', 'الدوسري', 'الزهراني', 'الشمري', 'الحربي', 'الغامدي', 'سالم', 'يوسف', 'إبراهيم', 'عبد الرحمن', 'فؤاد', 'منصور', 'النجار', 'الخطيب', 'حسين', 'عثمان', 'السيد', 'الشريف'];

    private SplPriorityQueue $queue;

    private int $seq = 0;

    /** @var array<string, int> */
    private array $count = ['courses' => 0, 'skipped' => 0, 'lessons' => 0, 'cancelled' => 0, 'reports' => 0, 'homework' => 0, 'ratings' => 0, 'payments' => 0, 'failed' => 0];

    /** @var array<string, true> invoices and reports already looked after */
    private array $seen = [];

    /** @var array<string, array{guardian: ?string, adult: bool, key: string}> */
    private array $students = [];

    /** @var array<string, int> payer => their habit (0 quick … 3 slow) */
    private array $habits = [];

    /** @var array<string, list<string>> enrollment => its lessons in order (the curriculum) */
    private array $path = [];

    /** @var array<string, int> enrollment => where the student is on it */
    private array $at = [];

    private string $owner = '';

    private string $accountant = '';

    private CarbonImmutable $lastSweep;

    private float $started = 0;

    public function run(PlatformApi $platform, TenantDirectory $directory, TenantContext $tenants): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LoadAcademySeeder makes a large made-up academy for measuring; it never runs in production.');
        }
        $slug = (string) (env('LOAD_SLUG') ?: 'load');
        if ($directory->bySlug($slug) !== null) {
            $this->command?->info("Academy \"{$slug}\" already exists; nothing to seed.");

            return;
        }

        $started = $this->started = microtime(true);
        mt_srand(20261011);
        $academy = $platform->createAcademy(new NewAcademy(
            slug: $slug, nameAr: 'أكاديمية الفرقان', nameEn: 'Furqan Academy', baseCurrency: 'SAR',
            defaultTimezone: 'Asia/Riyadh', defaultLocale: 'ar', country: 'SA', plan: 'pro',
        ));

        // Nobody is told anything while thirteen weeks go by in minutes.
        $notifications = app(NotificationsApi::class);
        app()->instance(NotificationsApi::class, new class($notifications) implements NotificationsApi
        {
            public function __construct(private readonly NotificationsApi $real) {}

            public function define(TemplateDefinition $template): void
            {
                $this->real->define($template);
            }

            public function templates(): array
            {
                return $this->real->templates();
            }

            public function send(Message $message): int
            {
                return 0;
            }
        });

        try {
            $tenants->run($academy, function () use ($started) {
                $this->people();
                $this->say('people', $started);
                $this->curriculum();
                $this->say('curriculum', $started);
                $this->history();
                $this->say('history', $started);
            });
        } finally {
            CarbonImmutable::setTestNow();
            AuditActor::forget();
            app()->instance(NotificationsApi::class, $notifications);
        }

        $this->command?->info(sprintf('Seeded academy "%s" in %d s: %s.', $slug, (int) (microtime(true) - $started),
            implode(', ', array_map(fn (string $k, int $n) => "{$n} {$k}", array_keys($this->count), $this->count))));
    }

    // ---- The academy and its people (through the modules' contracts). ----

    private function people(): void
    {
        $identity = app(IdentityApi::class);
        $people = app(PeopleApi::class);
        $catalog = app(CatalogApi::class);
        $now = CarbonImmutable::now();
        $user = fn (string $key, string $name, Role $role, string $tz, array $more = []) => $identity->createUser(new NewUser(
            name: $name, roles: [$role, ...$more], email: "{$key}@".self::DOMAIN, locale: 'ar', timezone: $tz,
        ));

        $this->owner = $user('owner', 'هالة منصور', Role::Owner, 'Asia/Riyadh', [Role::Admin]);
        AuditActor::use(new AuditActor('user', $this->owner, 'هالة منصور'));
        $this->accountant = $user('accountant', 'محاسب الأكاديمية', Role::Accountant, 'Asia/Riyadh');
        $user('scheduler', 'منسّق الجداول', Role::Scheduler, 'Africa/Cairo');

        $subjects = [];
        foreach (self::SUBJECTS as [$en, $ar]) {
            $subjects[$en] = $catalog->createSubject($ar, $en);
        }
        $this->call(DemoBillingSeeder::class);
        app(SaveRateCard::class)->handle(null, ['name' => 'الجدول الأساسي', 'currency' => 'SAR', 'is_default' => true, 'lines' => [
            ['unit' => 'hour', 'amount' => '60'], ['subject_id' => $subjects['Quran memorisation'], 'unit' => 'hour', 'amount' => '70'],
        ]]);
        SchedulingSettings::current()->forceFill(['horizon_weeks' => 3])->save();

        // Teachers: two subjects each, in three bands of the day, most in Cairo.
        $groups = [['Quran memorisation', 'Tajweed'], ['Tajweed', 'Quran memorisation'], ['Arabic', 'Arabic grammar'], ['English', 'Arabic'], ['Quran memorisation', 'Arabic']];
        $since = $now->subMonths(6)->toDateString();
        $teachers = (int) env('LOAD_TEACHERS', 40);
        for ($i = 1; $i <= $teachers; $i++) {
            $female = $i % 2 === 0;
            $tz = match (true) {
                $i % 7 === 3 => 'Asia/Amman',
                $i % 7 === 5 => 'Asia/Riyadh',
                default => 'Africa/Cairo',
            };
            $name = 'أ. '.$this->pick($female ? self::FEMALE : self::MALE, $i).' '.$this->pick(self::FAMILY, $i * 7);
            $id = $user(sprintf('t%02d', $i), $name, Role::Teacher, $tz);
            $taught = $groups[$i % count($groups)];
            $people->saveTeacherProfile(new TeacherProfileData($id, $female ? 'female' : 'male', ['ar', 'en'], array_map(fn (string $s) => $subjects[$s], $taught), maxWeeklyMinutes: 45 * 60));
            [$from, $to] = self::BANDS[$i % count(self::BANDS)];
            $version = ShiftSchedule::query()->create([
                'teacher_id' => $id, 'version' => 1, 'timezone' => $tz, 'status' => 'active', 'effective' => "[{$since},)", 'approved_by' => $this->owner,
            ]);
            $week = WeeklyShifts::fromArray(array_map(fn (int $d) => ['weekday' => $d, 'start' => sprintf('%02d:00', $from), 'end' => sprintf('%02d:00', $to)], [6, 7, 1, 2, 3, 4]), $tz);
            foreach ($week->blocks as $block) {
                $version->rules()->create(['teacher_id' => $id, 'weekday' => $block->weekday, 'local_start' => $block->start(), 'duration_min' => $block->durationMin]);
            }
        }

        // Students: a quarter in each look (kids, middle, teen, adults); the children's guardians, now and then
        // with a brother or sister, give their consent.
        $zones = ['Asia/Riyadh', 'Asia/Riyadh', 'Asia/Riyadh', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Africa/Cairo', 'Africa/Cairo', 'Europe/London', 'America/New_York'];
        $consent = app(GrantConsent::class);
        $guardian = null;
        $siblings = 0;
        $guardians = 0;
        $count = (int) env('LOAD_STUDENTS', 400);
        for ($i = 1; $i <= $count; $i++) {
            $age = match ($i % 4) {
                1 => 11 + $i % 3,
                2 => 7 + $i % 4,
                3 => 14 + $i % 4,
                default => 19 + $i % 27,
            };
            $tz = $zones[$i % count($zones)];
            $female = mt_rand(0, 1) === 1;
            $family = $this->pick(self::FAMILY, mt_rand(0, 99));
            $id = $user(sprintf('s%03d', $i), $this->pick($female ? self::FEMALE : self::MALE, mt_rand(0, 99)).' '.$family, Role::Student, $tz);
            $people->saveStudentProfile($id, $now->subYears($age)->subMonths(mt_rand(0, 11))->startOfDay(), null);
            $this->students[$id] = ['guardian' => null, 'adult' => $age >= 18, 'key' => sprintf('s%03d', $i)];
            if ($age >= 18) {
                continue;
            }
            if ($guardian === null || $siblings <= 0) {
                $guardians++;
                $guardian = $user(sprintf('g%03d', $guardians), $this->pick(self::MALE, mt_rand(0, 99)).' '.$family, Role::Guardian, $tz);
                $siblings = $i === 1 ? 1 : $this->weighted([0 => 60, 1 => 30, 2 => 10]);
            } else {
                $siblings--;
            }
            $people->linkGuardian($guardian, $id, $female ? 'father' : 'mother');
            $consent->handle($id, $guardian, 'family', '203.0.113.10', 'Load');
            $this->students[$id]['guardian'] = $guardian;
        }

        // Everyone signs in with the demo password: one hash for all, not one each.
        DB::table('users')->update(['password' => Hash::make((string) config('app.demo.password'))]);
    }

    /** A program per subject, published: four levels of three units of four lessons. */
    private function curriculum(): void
    {
        $programs = app(SaveProgram::class);
        $nodes = app(SaveNode::class);
        $publish = app(PublishVersion::class);
        foreach (DB::table('subjects')->get(['id', 'name_ar', 'name_en']) as $subject) {
            $program = $programs->handle(null, ['subject_id' => $subject->id, 'name_ar' => 'منهج '.$subject->name_ar, 'name_en' => $subject->name_en.' programme']);
            $version = $program->workingVersion();
            for ($l = 1; $l <= 4; $l++) {
                $level = $nodes->handle($version, null, ['kind' => 'level', 'title_ar' => "المستوى {$l}", 'title_en' => "Level {$l}"]);
                for ($u = 1; $u <= 3; $u++) {
                    $unit = $nodes->handle($version, null, ['kind' => 'unit', 'parent_id' => $level->id, 'title_ar' => "الوحدة {$l}.{$u}", 'title_en' => "Unit {$l}.{$u}"]);
                    for ($n = 1; $n <= 4; $n++) {
                        $nodes->handle($version, null, ['kind' => 'lesson', 'parent_id' => $unit->id, 'title_ar' => "الدرس {$l}.{$u}.{$n}", 'title_en' => "Lesson {$l}.{$u}.{$n}",
                            'objectives' => null, 'weight' => 1, 'est_sessions' => 1 + ($n % 2), 'mastery_rule' => 'mastered_once']);
                    }
                }
            }
            $publish->handle($version, $this->owner);
        }
    }

    // ---- Thirteen weeks, day by day, through the real actions. ----

    private function history(): void
    {
        $realNow = CarbonImmutable::now();
        $cutoff = $realNow->subMinutes(20);
        $weeks = (int) env('LOAD_WEEKS', 13);
        $first = $realNow->subWeeks($weeks)->utc()->startOfDay();
        $this->queue = new SplPriorityQueue;
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $this->lastSweep = $first;

        foreach ($this->courses($first, $realNow) as $course) {
            $this->at($course['enrol_at'], fn () => $this->enrol($course));
        }

        $planned = $first->subDay();
        for ($day = $first; $day->lessThan($cutoff); $day = $day->addDay()) {
            $end = $day->addDay()->min($cutoff);
            if ($day->dayOfWeekIso === 6) {
                $this->at($day->addHours(5), fn () => $this->keepAhead());
            }
            for ($h = 0; $h < 24; $h++) {
                if ($day->addHours($h)->addMinutes(15)->lessThan($end)) {
                    $this->at($day->addHours($h)->addMinutes(15), fn () => $this->sweep());
                }
            }
            // Tomorrow's lessons are known today: who cancels; today's are joined as they start.
            $this->planLessons($day, $end, $planned);
            $this->runUntil($end);
            if ($day->dayOfWeekIso === 5) {
                $this->say('week to '.$day->toDateString(), $this->started);
            }
        }

        // Now: what is due is closed, the weeks ahead are there, and the lessons under way have their people in.
        CarbonImmutable::setTestNow();
        $this->keepAhead();
        $join = app(RecordParticipantJoin::class);
        $now = CarbonImmutable::now();
        foreach (Session::query()->where('status', 'scheduled')->where('starts_at', '>', $cutoff->subHour())->where('starts_at', '<=', $now)->with('participants')->get() as $session) {
            $student = $session->participants->firstWhere('role.value', 'student')?->user_id;
            $teacherAt = $session->starts_at->addMinutes(mt_rand(-2, 4) + (mt_rand(1, 10) === 1 ? 8 : 0));
            if ($teacherAt->lessThanOrEqualTo($now)) {
                $join->handle($session, $session->teacher_id, $teacherAt);
            }
            $studentAt = $session->starts_at->addMinutes(mt_rand(-1, 6));
            if ($student !== null && $studentAt->lessThanOrEqualTo($now) && mt_rand(1, 12) > 1) {
                $join->handle($session->refresh(), $student, $studentAt);
            }
        }
        app(ApplyAttendanceDeadlines::class)->handle();

        // The accountant has closed the months before last.
        $months = app(PayrollMonths::class);
        for ($month = $months->of($first); $month->lessThan($months->current()->subMonth()); $month = $month->addMonth()) {
            app(ClosePayrollMonth::class)->handle($month, $this->accountant);
        }
    }

    /**
     * Who learns what with whom, and from when: a student's courses on different day pairs, each with the
     * least busy teacher of the subject who is free at one hour on both days.
     *
     * @return list<array<string, mixed>>
     */
    private function courses(CarbonImmutable $first, CarbonImmutable $realNow): array
    {
        $teachers = DB::table('users as u')->join('teacher_profiles as p', 'p.user_id', '=', 'u.id')
            ->orderBy('u.email')->get(['u.id', 'u.email', 'u.timezone']);
        $subjects = DB::table('subjects')->pluck('id', 'name_en');
        $teaches = DB::table('teacher_subjects')->get(['teacher_id', 'subject_id'])->groupBy('subject_id')->map(fn ($rows) => $rows->pluck('teacher_id')->all());
        $busy = [];
        $load = [];
        $bands = [];
        foreach ($teachers->values() as $i => $t) {
            $bands[$t->id] = self::BANDS[((int) substr($t->email, 1, 2)) % count(self::BANDS)];
            $load[$t->id] = 0;
        }
        $tz = $teachers->pluck('timezone', 'id');
        $plans = [];
        foreach (self::PLANS as [$minutes, $perWeek, $weight]) {
            $plans["{$minutes}x{$perWeek}"] = $weight;
        }
        $regions = collect(app(BillingApi::class)->regions())->keyBy(fn (RegionOption $r) => $r->currency);

        $out = [];
        $n = 0;
        foreach ($this->students as $studentId => $s) {
            $n++;
            $how = $n === 1 ? 3 : $this->weighted([1 => 70, 2 => 22, 3 => 8]);
            $taken = [];
            $rotation = mt_rand(0, 2);
            $payer = $s['guardian'] ?? $studentId;
            $this->habits[$payer] ??= $this->weighted([0 => 50, 1 => 30, 2 => 15, 3 => 5]);
            $currency = match (crc32($payer) % 20) {
                0, 1 => 'AED',
                2, 3 => 'GBP',
                4 => 'USD',
                default => 'SAR',
            };
            for ($k = 0; $k < $how; $k++) {
                $subject = null;
                for ($try = 0; $try < 10 && ($subject === null || isset($taken[$subject])); $try++) {
                    $subject = $this->weighted(array_combine(array_column(self::SUBJECTS, 0), array_column(self::SUBJECTS, 2)));
                }
                if (isset($taken[$subject])) {
                    continue;
                }
                $taken[$subject] = true;
                [$minutes, $perWeek] = array_map('intval', explode('x', (string) $this->weighted($plans)));
                $pair = self::PAIRS[($rotation + $k) % 3];
                $days = $perWeek === 1 ? [$pair[0]] : $pair;

                $choice = null;
                $candidates = $teaches[$subjects[$subject]] ?? [];
                usort($candidates, fn (string $a, string $b) => $load[$a] <=> $load[$b]);
                foreach ($candidates as $teacherId) {
                    [$from, $to] = $bands[$teacherId];
                    $free = array_values(array_filter(range($from, $to - 1), fn (int $h) => ! array_filter($days, fn (int $d) => isset($busy[$teacherId][$d][$h]))));
                    if ($free !== []) {
                        $choice = [$teacherId, $free[mt_rand(0, count($free) - 1)]];
                        break;
                    }
                }
                if ($choice === null) {
                    $this->count['skipped']++;

                    continue;
                }
                [$teacherId, $hour] = $choice;
                foreach ($days as $d) {
                    $busy[$teacherId][$d][$hour] = true;
                }
                $load[$teacherId] += count($days);
                // Most started at the beginning; the rest joined since, the latest a week ago.
                $starts = mt_rand(1, 100) <= 65 ? $first->addDays(mt_rand(0, 6)) : $first->addDays(mt_rand(7, max(7, (int) $first->diffInDays($realNow) - 7)));
                $out[] = [
                    'student' => $studentId, 'payer' => $payer, 'teacher' => $teacherId, 'teacher_tz' => (string) $tz[$teacherId],
                    'subject' => (string) $subjects[$subject], 'minutes' => $minutes, 'per_week' => $perWeek, 'days' => $days, 'hour' => $hour,
                    'region' => $regions[$currency]->id ?? $regions['SAR']->id, 'starts' => $starts,
                    'enrol_at' => CarbonImmutable::parse($starts->subDay()->toDateString().' 07:00', 'UTC')->addMinutes(mt_rand(0, 600)),
                ];
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $c */
    private function enrol(array $c): void
    {
        $plan = collect(app(BillingApi::class)->planOptions($c['minutes']))
            ->first(fn (PlanOption $p) => ! $p->shared && $p->sessionMinutes === $c['minutes'] && $p->sessionsPerWeek === $c['per_week']);
        $occurrences = [];
        foreach ($c['days'] as $weekday) {
            $day = $c['starts']->setTimezone($c['teacher_tz'])->startOfDay();
            $day = $day->addDays(($weekday - $day->dayOfWeekIso + 7) % 7);
            for ($i = 0; $i < 2; $i++) {
                $occurrences[] = CarbonImmutable::parse($day->addWeeks($i)->toDateString().sprintf(' %02d:00', $c['hour']), $c['teacher_tz'])->getTimestamp();
            }
        }
        try {
            $batch = app(PlaceBookingHold::class)->handle($c['teacher'], $c['student'], $occurrences, $c['minutes'], $this->owner);
            $booked = app(EnrollStudent::class)->handle($batch, $c['student'], $c['subject'], new EnrollmentTerms($plan->id, $c['payer'], $c['region']), $this->owner);
            $this->count['courses']++;
            $version = DB::table('enrollments')->where('id', $booked->enrollmentId)->value('curriculum_version_id');
            $this->path[$booked->enrollmentId] = $version === null ? [] : array_map(fn ($n) => $n->id, app(CurriculumApi::class)->tree($version)->lessons());
            $this->at[$booked->enrollmentId] = 0;
        } catch (ValidationException $e) {
            $this->count['skipped']++;
            $this->command?->warn('  course skipped: '.json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
        }
        $this->invoices();
    }

    /** Lessons materialised ahead for every course, and funded from what is paid. */
    private function keepAhead(): void
    {
        app(MaterializeSessions::class)->handle();
        $fund = app(FundSessions::class);
        foreach (DB::table('enrollments')->whereIn('status', ['pending_payment', 'active'])->pluck('id') as $id) {
            $fund->handle((string) $id);
        }
    }

    /** Today's lessons: who comes when (and who leaves early), and tomorrow's that the family cancels. */
    private function planLessons(CarbonImmutable $day, CarbonImmutable $end, CarbonImmutable &$planned): void
    {
        $tomorrow = $day->addDay();
        if ($planned->lessThan($tomorrow)) {
            $lessons = Session::query()->where('status', 'scheduled')->where('type', 'regular')
                ->where('starts_at', '>=', $tomorrow)->where('starts_at', '<', $tomorrow->addDay())->get(['id', 'starts_at']);
            foreach ($lessons as $l) {
                if (mt_rand(1, 100) <= 4) {
                    $this->at($l->starts_at->subHours(mt_rand(3, 30))->max($day->addMinute()), fn () => $this->cancel($l->id));
                }
            }
            $planned = $tomorrow;
        }

        $lessons = Session::query()->where('status', 'scheduled')->where('starts_at', '>=', $day)->where('starts_at', '<', $day->addDay())
            ->where('ends_at', '<', $end)->orderBy('starts_at')->get(['id', 'starts_at']);
        foreach ($lessons as $session) {
            $this->at($session->starts_at->subMinutes(3), fn () => $this->lesson($session->id));
        }
    }

    private function cancel(string $sessionId): void
    {
        $session = Session::query()->with('participants')->find($sessionId);
        if ($session === null || $session->status->value !== 'scheduled') {
            return;
        }
        $student = $session->participants->firstWhere('role.value', 'student')?->user_id;
        $by = $this->students[$student]['guardian'] ?? $student;
        app(CancelSession::class)->handle($session, 'student', ['illness', 'travel', 'other'][mt_rand(0, 2)], null, $by);
        $this->count['cancelled']++;
    }

    /** One lesson as it goes: the teacher (on time, late, or not at all), the student, and when each leaves. */
    private function lesson(string $sessionId): void
    {
        $session = Session::query()->with('participants')->find($sessionId);
        if ($session === null || $session->status->value !== 'scheduled') {
            return;
        }
        $join = app(RecordParticipantJoin::class);
        $leave = app(RecordParticipantLeave::class);
        $student = (string) $session->participants->firstWhere('role.value', 'student')?->user_id;
        $start = $session->starts_at;
        $end = $session->ends_at;
        $recent = $start->greaterThan(CarbonImmutable::now()->subDay());
        $outcome = $this->weighted(['completed' => 90, 'student_absent' => 6, 'teacher_absent' => 2, 'nobody' => $recent ? 2 : 0]);

        if ($outcome === 'completed' || $outcome === 'student_absent') {
            $late = $this->weighted([0 => 85, 1 => 12, 2 => 3]);
            $join->handle($session, $session->teacher_id, $start->addMinutes(match ($late) {
                0 => mt_rand(-2, 1),
                1 => mt_rand(3, 6),
                default => mt_rand(7, 9),
            }));
        }
        if ($outcome === 'completed' || $outcome === 'teacher_absent') {
            $join->handle($session->refresh(), $student, $start->addMinutes($this->weighted([mt_rand(-1, 2) => 80, mt_rand(3, 5) => 17, mt_rand(6, 8) => 3])));
        }
        if ($outcome === 'completed') {
            $leave->handle($session->refresh(), $student, mt_rand(1, 20) === 1 ? $end->subMinutes(4) : $end->addMinutes(mt_rand(0, 1)));
            $leave->handle($session->refresh(), $session->teacher_id, $end->addMinutes(mt_rand(0, 2)));
        } elseif ($outcome === 'student_absent') {
            $leave->handle($session->refresh(), $session->teacher_id, $start->addMinutes(20));
        } elseif ($outcome === 'teacher_absent') {
            $leave->handle($session->refresh(), $student, $start->addMinutes(12));
        }
        $this->count['lessons']++;
    }

    /** The per-minute sweeper (as the scheduler runs it), then what follows a lesson closed: its report and maybe a rating. */
    private function sweep(): void
    {
        app(ApplyAttendanceDeadlines::class)->handle();
        $now = CarbonImmutable::now();
        $reports = SessionReport::query()->where('status', 'due')->where('created_at', '>=', $this->lastSweep)->get(['id', 'kind', 'session_id', 'student_id', 'due_at']);
        foreach ($reports as $r) {
            if (isset($this->seen[$r->id])) {
                continue;
            }
            $this->seen[$r->id] = true;
            $when = $this->weighted([0 => 70, 1 => 15, 2 => 10, 3 => 5]);
            if ($when < 3) {
                $at = match ($when) {
                    0 => $now->addMinutes(mt_rand(30, 480)),
                    1 => $now->addMinutes(mt_rand(540, 1200))->min($r->due_at->subMinutes(5)),
                    default => $r->due_at->addMinutes(mt_rand(60, 1800)),
                };
                $this->at($at, fn () => $this->report($r->id));
            }
            if ($r->kind === 'lesson' && mt_rand(1, 100) <= 35) {
                $this->at($now->addMinutes(mt_rand(60, 1100)), fn () => $this->rate($r->session_id, $r->student_id));
            }
        }
        $this->lastSweep = $now;
        $this->invoices();
    }

    /** The teacher's report: the lesson's place in the curriculum and how it went, with homework half the time. */
    private function report(string $reportId): void
    {
        $report = SessionReport::query()->find($reportId);
        if ($report === null || $report->status !== 'due') {
            return;
        }
        $data = ['absence_note' => 'لم يحضر الطالب ولم يعتذر.'];
        $homework = false;
        $mastered = false;
        if ($report->kind === 'lesson') {
            $path = $this->path[$report->enrollment_id] ?? [];
            $at = $this->at[$report->enrollment_id] ?? 0;
            $outcome = $this->weighted(['mastered' => 55, 'practiced' => 30, 'introduced' => 10, 'needs_review' => 5]);
            $mastered = $outcome === 'mastered';
            $homework = mt_rand(1, 2) === 1;
            $data = [
                'engagement' => mt_rand(3, 5), 'overall' => mt_rand(3, 5), 'notes_for_student' => 'أحسنت، استمر على المراجعة اليومية.',
                'items' => $path === [] ? [] : [['node_id' => $path[min($at, count($path) - 1)], 'outcome' => $outcome, 'score' => mt_rand(3, 5)]],
                'homework' => $homework ? ['title' => 'مراجعة درس اليوم', 'instructions' => 'راجع ما أخذناه واكتب ملخصًا قصيرًا.', 'accepts' => 'text', 'due_days' => 3] : null,
            ];
        }
        try {
            app(SubmitSessionReport::class)->handle($report, $report->teacher_id, $data);
            $this->count['reports']++;
        } catch (ValidationException $e) {
            $this->count['failed']++;

            return;
        }
        if ($mastered) {
            $this->at[$report->enrollment_id] = ($this->at[$report->enrollment_id] ?? 0) + 1;
        }
        if ($homework && mt_rand(1, 10) <= 7) {
            $id = Homework::query()->where('report_id', $report->id)->value('id');
            if ($id !== null) {
                $this->at(CarbonImmutable::now()->addMinutes(mt_rand(360, 3600)), fn () => $this->handIn($id));
            }
        }
    }

    private function handIn(string $homeworkId): void
    {
        $homework = Homework::query()->find($homeworkId);
        if ($homework === null || ! in_array($homework->status, ['assigned', 'missed'], true)) {
            return;
        }
        app(SubmitHomework::class)->handle($homework, $homework->student_id, 'راجعت الدرس، وهذا ملخصي.', null);
        $this->count['homework']++;
        if (mt_rand(1, 100) <= 85) {
            $this->at(CarbonImmutable::now()->addMinutes(mt_rand(90, mt_rand(1, 10) === 1 ? 4200 : 2700)), function () use ($homeworkId) {
                $homework = Homework::query()->find($homeworkId);
                if ($homework?->status === 'submitted') {
                    app(ReviewHomework::class)->handle($homework, (string) $homework->teacher_id, mt_rand(3, 5), 'ممتاز.');
                }
            });
        }
    }

    private function rate(string $sessionId, string $studentId): void
    {
        $s = $this->students[$studentId] ?? null;
        $rater = $s !== null && ! $s['adult'] && $s['guardian'] !== null ? $s['guardian'] : $studentId;
        try {
            app(RateLesson::class)->handle($sessionId, $rater, $this->weighted([5 => 60, 4 => 25, 3 => 10, 2 => 3, 1 => 2]));
            $this->count['ratings']++;
        } catch (ValidationException) {
            $this->count['failed']++;
        }
    }

    /** Invoices that went out since last looked at: each payer pays by habit, a few not at all. */
    private function invoices(): void
    {
        $payers = DB::table('billing_accounts')->pluck('payer_id', 'id');
        foreach (Invoice::query()->where('status', 'issued')->get(['id', 'issued_at', 'billing_account_id']) as $invoice) {
            if (isset($this->seen[$invoice->id])) {
                continue;
            }
            $this->seen[$invoice->id] = true;
            $habit = $this->habits[(string) ($payers[$invoice->billing_account_id] ?? '')] ?? 1;
            if (mt_rand(1, 100) <= 3) {
                continue;   // left owing
            }
            $hours = match ($habit) {
                0 => mt_rand(1, 24),
                1 => mt_rand(24, 72),
                2 => mt_rand(72, 168),
                default => mt_rand(168, 336),
            };
            $at = CarbonImmutable::now()->max($invoice->issued_at)->addHours($hours)->setTimezone('Asia/Riyadh');
            $this->at($at->hour < 9 ? $at->setTime(9 + mt_rand(0, 3), mt_rand(0, 59)) : $at, fn () => $this->pay($invoice->id, $habit));
        }
    }

    private function pay(string $invoiceId, int $habit): void
    {
        $invoice = Invoice::query()->find($invoiceId);
        if ($invoice === null || $invoice->status !== 'issued') {
            return;
        }
        $method = ['bank_transfer', 'card_terminal', 'wallet', 'cash'][($habit + mt_rand(0, 1)) % 4];
        app(BillingApi::class)->recordPayment($invoice->id, $method, $invoice->outstanding(), 'LD-'.(100000 + $this->count['payments']), CarbonImmutable::now(), $this->accountant);
        $this->count['payments']++;
    }

    // ---- The clock. ----

    private function at(CarbonImmutable $when, Closure $do): void
    {
        $this->queue->insert($do, [-$when->getTimestamp(), -(++$this->seq)]);
    }

    private function runUntil(CarbonImmutable $end): void
    {
        while (! $this->queue->isEmpty()) {
            $top = $this->queue->top();
            $ts = -$top['priority'][0];
            if ($ts >= $end->getTimestamp()) {
                return;
            }
            $this->queue->extract();
            CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestamp($ts, 'UTC'));
            try {
                ($top['data'])();
            } catch (Throwable $e) {
                $this->count['failed']++;
                if ($this->count['failed'] <= 20) {
                    $this->command?->warn('  '.class_basename($e).': '.$e->getMessage());
                }
            }
        }
    }

    // ---- Small things. ----

    /** @param  array<int|string, int>  $weights */
    private function weighted(array $weights): int|string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    /** @param  list<string>  $list */
    private function pick(array $list, int $i): string
    {
        return $list[$i % count($list)];
    }

    private function say(string $what, float $started): void
    {
        $this->command?->info(sprintf('  %s: %s (%d s)', $what, implode(', ', array_map(fn (string $k, int $n) => "{$n} {$k}", array_keys($this->count), $this->count)), (int) (microtime(true) - $started)));
    }
}
