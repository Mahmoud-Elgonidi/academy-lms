<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Models\Plan;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Learning\Actions\SubmitGroupReport;
use Modules\Learning\Models\SessionReport;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Models\RateCard;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\Groups\CreateGroup;
use Modules\Scheduling\Actions\Groups\JoinGroup;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Contracts\DTOs\GroupJoin;
use Modules\Scheduling\Contracts\DTOs\NewGroup;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * Group lessons in the demo (docs/architecture/02, section 25), through the
 * real actions: Khaled takes groups of up to five and Mona of up to six; a
 * group plan beside each private shape a group fits (about 60% of its price
 * in every region); group lines on the pay cards. Khaled's Arabic group of
 * adults started about two weeks ago with Tamer and Dana, Nader joined a
 * week later (the lessons he missed off his first invoice), and its lessons
 * took place (Dana missed one); Mona's children's Arabic group started ten
 * days ago with Lina, Yahya (their mother Huda pays) and Tala, so a child
 * sees "with 2 classmates" and her mother their first names; and Mona's
 * Quran group forms with Sama, waiting for three more. Ziad and the other
 * one-to-one students stay as they are (the end-to-end tests use them).
 *
 * Fresh demos run it before payroll (so their months price the lessons);
 * DemoCatchUpSeeder brings it to a demo made before groups. Each part only
 * once: nothing when its people are already there.
 */
class DemoGroupsSeeder extends Seeder
{
    /** The plans' shapes a group is sold in: minutes, lessons a week, lessons a block. */
    public const SHAPES = [[30, 1, 4], [30, 2, 8], [45, 2, 8]];

    /** A group plan's price against the private one of the same shape. */
    public const SHARE = 0.6;

    /** The demo's group people, by key: name, age, time zone, guardian (key, name, relation) or null. */
    public const PEOPLE = [
        'tamer' => ['تامر سامي', 27, 'Asia/Riyadh', null],
        'dana' => ['دانة فهد', 24, 'Asia/Dubai', null],
        'nader' => ['نادر وليد', 31, 'Europe/London', null],
        'lina' => ['لينا عادل', 8, 'Asia/Riyadh', ['lina.guardian', 'هدى سامر', 'mother']],
        'yahya' => ['يحيى عادل', 10, 'Asia/Riyadh', ['lina.guardian', 'هدى سامر', 'mother']],
        'tala' => ['تالا مازن', 9, 'Asia/Kuwait', ['tala.guardian', 'مازن خليل', 'father']],
        'sama' => ['سما رائد', 10, 'Asia/Qatar', ['sama.guardian', 'رائد يوسف', 'father']],
    ];

    /** @var array<string, string> user ids by demo key */
    private array $ids = [];

    public function run(): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->first(['id', 'name']);
        if ($owner === null || DB::table('users')->where('email', 'khaled@demo.test')->doesntExist()) {
            return;
        }
        $before = AuditActor::current();
        AuditActor::use(new AuditActor('user', (string) $owner->id, (string) $owner->name));
        try {
            $this->teachers();
            $this->plans();
            $this->payCards();
            if (DB::table('users')->where('email', 'tamer@demo.test')->doesntExist()) {
                $this->people();
                $this->groups((string) $owner->id);
            }
        } finally {
            CarbonImmutable::setTestNow();
            AuditActor::use($before);
        }
    }

    /** Khaled takes groups of up to five, Mona of up to six; everything else on their profiles stays. */
    private function teachers(): void
    {
        foreach (['khaled' => 5, 'mona' => 6] as $key => $size) {
            $id = $this->user($key);
            $profile = DB::table('teacher_profiles')->where('user_id', $id)->first();
            if ($id === null || $profile === null || $profile->accepts_groups) {
                continue;
            }
            app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData(
                $id, (string) $profile->gender, json_decode((string) $profile->languages, true) ?: ['ar'],
                DB::table('teacher_subjects')->where('teacher_id', $id)->pluck('subject_id')->all(), $profile->dialect,
                (bool) $profile->accepts_new, (bool) $profile->accepts_trials, $profile->max_weekly_min === null ? null : (int) $profile->max_weekly_min,
                null, $profile->max_daily_lessons === null ? null : (int) $profile->max_daily_lessons, [], acceptsGroups: true, maxGroupSize: $size,
            ));
        }
    }

    /** A group plan for each shape a private one is sold in, at about 60% of its price in every region; one already there stays. */
    private function plans(): void
    {
        $sort = (int) Plan::query()->max('sort');
        foreach (self::SHAPES as [$minutes, $perWeek, $block]) {
            $shape = fn ($q) => $q->where('session_minutes', $minutes)->where('sessions_per_week', $perWeek)->where('sessions_per_block', $block)->where('is_active', true);
            if (Plan::query()->where('lesson_format', Plan::GROUP)->where($shape)->exists()) {
                continue;
            }
            $private = Plan::query()->where('lesson_format', Plan::PRIVATE)->where('credit_scope', '!=', Plan::SHARED)->where($shape)->with('prices')->first();
            if ($private === null) {
                continue;
            }
            $times = match ($perWeek) {
                1 => ['حصة في الأسبوع', 'once a week'],
                2 => ['حصتان في الأسبوع', 'twice a week'],
                default => ["{$perWeek} في الأسبوع", "{$perWeek} a week"],
            };
            $plan = app(SavePlan::class)->handle(null, [
                'name_ar' => "مجموعة · {$block} حصص · {$times[0]} · {$minutes} دقيقة",
                'name_en' => "Group · {$block} sessions · {$times[1]} · {$minutes} min",
                'lesson_format' => Plan::GROUP,
                'session_minutes' => $minutes,
                'sessions_per_block' => $block,
                'sessions_per_week' => $perWeek,
                'prices' => $private->prices->map(fn ($price) => [
                    'price_region_id' => $price->price_region_id,
                    'amount' => (string) round($price->amount_minor / 100 * self::SHARE),
                ])->values()->all(),
            ]);
            $plan->forceFill(['sort' => ++$sort])->save();
        }
    }

    /** A group's lesson is paid at a group rate: a line on each pay card that has none (fresh demos write it with the cards). */
    private function payCards(): void
    {
        foreach (RateCard::query()->with('lines')->get() as $card) {
            if ($card->lines->contains(fn ($line) => $line->session_format === 'group')) {
                continue;
            }
            $hour = (int) ($card->lines->first(fn ($l) => $l->unit === 'hour' && $l->subject_id === null && $l->session_type === null)?->amount_minor ?? 0);
            if ($hour === 0) {
                continue;
            }
            app(SaveRateCard::class)->handle($card, [
                'name' => $card->name, 'currency' => $card->currency, 'is_default' => (bool) $card->is_default,
                'lines' => [
                    ...$card->lines->map(fn ($l) => ['subject_id' => $l->subject_id, 'session_type' => $l->session_type, 'session_format' => $l->session_format,
                        'unit' => $l->unit, 'amount' => (string) ($l->amount_minor / 100)])->all(),
                    ['session_format' => 'group', 'unit' => 'hour', 'amount' => (string) round($hour / 100 * 1.5)],
                ],
            ]);
        }
    }

    /** The group students, the children with their guardians (who agreed to their accounts). */
    private function people(): void
    {
        $identity = app(IdentityApi::class);
        $people = app(PeopleApi::class);
        $password = (string) config('app.demo.password');
        $now = CarbonImmutable::now();
        foreach (self::PEOPLE as $key => [$name, $age, $tz, $guardian]) {
            $this->ids[$key] = $identity->createUser(new NewUser(name: $name, roles: [Role::Student], email: "{$key}@demo.test", password: $password, locale: 'ar', timezone: $tz));
            $people->saveStudentProfile($this->ids[$key], $now->subYears($age)->subMonths(3)->startOfDay(), null);
            if ($guardian === null) {
                continue;
            }
            [$gKey, $gName, $relation] = $guardian;
            $this->ids[$gKey] ??= $this->user($gKey) ?? $identity->createUser(new NewUser(name: $gName, roles: [Role::Guardian], email: "{$gKey}@demo.test",
                password: $password, locale: 'ar', timezone: $tz));
            $people->linkGuardian($this->ids[$gKey], $this->ids[$key], $relation);
            app(GrantConsent::class)->handle($this->ids[$key], $this->ids[$gKey], 'family', '203.0.113.77', 'Demo');
        }
    }

    private function groups(string $owner): void
    {
        $subject = fn (string $en) => (string) DB::table('subjects')->where('name_en', $en)->value('id');
        $realNow = CarbonImmutable::now();

        // Khaled's adults: Arabic, Mondays and Thursdays at 16:00 Cairo, 45 minutes; Tamer and Dana from two weeks ago, Nader a week later.
        $arabic = $this->running($owner, $realNow->subDays(15), new NewGroup(
            teacherId: (string) $this->user('khaled'), subjectId: $subject('Arabic'), sessionMinutes: 45,
            times: [['weekday' => 1, 'start' => '16:00'], ['weekday' => 4, 'start' => '16:00']], ageGroup: 'adults', capacity: 5,
        ), [
            ['tamer', 0, 'SAR', null],
            ['dana', 0, 'AED', null],
            ['nader', 7, 'GBP', 'discount'],
        ], absent: ['dana' => 2]);

        // Mona's children: Arabic, Sundays and Thursdays at 17:00 Amman, 30 minutes, from ten days ago.
        $this->running($owner, $realNow->subDays(10), new NewGroup(
            teacherId: (string) $this->user('mona'), subjectId: $subject('Arabic'), sessionMinutes: 30,
            times: [['weekday' => 7, 'start' => '17:00'], ['weekday' => 4, 'start' => '17:00']], ageGroup: 'kids', capacity: 6,
        ), [
            ['lina', 0, 'SAR', null],
            ['yahya', 0, 'SAR', null],
            ['tala', 0, 'SAR', null],
        ], absent: ['tala' => 1]);

        // Mona's Quran group for children forms: Sama has her seat, it starts with four.
        $forming = app(CreateGroup::class)->handle(new NewGroup(
            teacherId: (string) $this->user('mona'), subjectId: $subject('Quran memorisation'), sessionMinutes: 30,
            times: [['weekday' => 6, 'start' => '20:00'], ['weekday' => 4, 'start' => '18:00']], ageGroup: 'kids', capacity: 6, minToStart: 4,
        ), $owner);
        $this->join($forming, 'sama', 'SAR', null, $owner, pay: true);

        $this->command?->info(sprintf('Demo groups: %s running (%d seats), one forming.', $arabic->id, $arabic->capacity));
    }

    /**
     * A group that started some days ago: made then, its students joining on their day (paying on the spot), and each
     * lesson since as it happened (teacher and students in a minute or two after the start, the absent ones not), the
     * attendance sweep closing it and the teacher's report handed in the next hour, all but the last (left to write).
     *
     * @param  list<array{0: string, 1: int, 2: string, 3: ?string}>  $members  key, days after the start, currency, late-join choice
     * @param  array<string, int>  $absent  a student and the lesson (from 0) they missed
     */
    private function running(string $owner, CarbonImmutable $from, NewGroup $new, array $members, array $absent = []): LessonGroup
    {
        CarbonImmutable::setTestNow($from->setTime(9, 0));
        $group = app(CreateGroup::class)->handle($new, $owner);
        CarbonImmutable::setTestNow();
        $realNow = CarbonImmutable::now();
        $held = 0;
        // Each lesson that ended before $until, as it happened.
        $play = function (CarbonImmutable $until) use ($group, $absent, $realNow, &$held) {
            $past = Session::query()->where('group_id', $group->id)->where('status', 'scheduled')
                ->where('ends_at', '<', $until->min($realNow->subMinutes(15)))->orderBy('starts_at')->with('participants')->get();
            foreach ($past as $session) {
                CarbonImmutable::setTestNow($session->starts_at->addMinute());
                app(RecordParticipantJoin::class)->handle($session, $session->teacher_id);
                foreach ($session->participants->filter(fn ($p) => $p->role->value === 'student' && $p->is_active) as $seat) {
                    $key = array_search($seat->user_id, $this->ids, true);
                    if (($absent[$key] ?? null) === $held) {
                        continue;
                    }
                    CarbonImmutable::setTestNow($session->starts_at->addMinutes(2));
                    app(RecordParticipantJoin::class)->handle($session->refresh(), $seat->user_id);
                }
                CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
                app(ApplyAttendanceDeadlines::class)->handle();
                $held++;
            }
        };
        foreach ($members as [$key, $after, $currency, $late]) {
            $at = $from->setTime(9, 0)->addDays($after);
            $play($at);
            CarbonImmutable::setTestNow($at);
            $this->join($group, $key, $currency, $late, $owner, pay: true);
        }
        $play($realNow);

        // The teacher's reports, handed in the hour after each lesson; the last one is left to write.
        $done = Session::query()->where('group_id', $group->id)->whereIn('status', ['completed', 'student_absent'])->orderBy('starts_at')->get();
        foreach ($done->slice(0, -1) as $session) {
            CarbonImmutable::setTestNow($session->ends_at->addHour());
            $this->report($session);
        }
        CarbonImmutable::setTestNow();
        app(MaterializeSessions::class)->groups([$group->id]);
        foreach ($group->members()->pluck('enrollment_id') as $enrollmentId) {
            app(FundSessions::class)->handle((string) $enrollmentId);
        }

        return $group->refresh();
    }

    /** A seat for the student, paid for at once (the first invoice, with any late-join discount on it). */
    private function join(LessonGroup $group, string $key, string $currency, ?string $late, string $owner, bool $pay): void
    {
        $plan = DB::table('plans')->where('lesson_format', Plan::GROUP)->where('is_active', true)
            ->where('session_minutes', $group->session_minutes)->where('sessions_per_week', $group->sessions_per_week)->orderBy('sort')->value('id');
        $region = DB::table('price_regions')->where('currency', $currency)->value('id');
        $guardian = self::PEOPLE[$key][3];
        $payer = $guardian === null ? $this->ids[$key] : $this->ids[$guardian[0]];
        $joined = app(JoinGroup::class)->handle($group->id, $this->ids[$key], new GroupJoin((string) $plan, $payer, (string) $region, lateJoin: $late), $owner);
        if ($pay) {
            app(BillingApi::class)->recordPayment($joined->invoice->id, $currency === 'SAR' ? 'bank_transfer' : 'card_terminal',
                $joined->invoice->total, 'GR-'.substr($joined->enrollmentId, 0, 6), CarbonImmutable::now(), $owner);
        }
    }

    /** The teacher's group report: a word for each student who came, a note for one who did not; left to write when the form asks for more. */
    private function report(Session $session): void
    {
        $report = SessionReport::query()->where('session_id', $session->id)->orderBy('student_id')->first();
        if ($report === null) {
            return;
        }
        $rows = SessionReport::query()->where('session_id', $session->id)->orderBy('student_id')->get()->map(fn (SessionReport $r) => $r->kind === 'lesson'
            ? ['student_id' => $r->student_id, 'engagement' => 4, 'overall' => 4, 'notes_for_student' => 'مشاركة جيدة اليوم، أحسنت.']
            : ['student_id' => $r->student_id, 'absence_note' => 'غاب عن الحصة، وسنراجع ما فاته في الحصة القادمة.'])->all();
        try {
            app(SubmitGroupReport::class)->handle($report, $session->teacher_id, ['items' => [], 'notes_internal' => null], $rows);
        } catch (ValidationException) {
            // The subject's form asks for more than the demo writes: the report waits for the teacher.
        }
    }

    private function user(string $key): ?string
    {
        return $this->ids[$key] ?? DB::table('users')->where('email', "{$key}@demo.test")->value('id');
    }
}
