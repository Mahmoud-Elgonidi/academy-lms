<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Actions\Proofs\ApprovePaymentProof;
use Modules\Billing\Actions\Proofs\RejectPaymentProof;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Models\PaymentProof;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\Room;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Models\CrmSettings;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Support\ReportLinks;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\Courses\EndEnrollment;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Actions\Groups\GroupSeats;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Support\TrialLinks;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;
use Modules\Site\Support\PickLinks;
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
 * printed as one line, `E2E_FIXTURES {json}` (with `group`: the children's group, a lesson of it to excuse,
 * a report of it to write, and the family's sign-ins; with `quran`: Yousef's Quran course, a lesson of it to record
 * now, his family's sign-ins and his certificate, its code as printed). What depends on the moment a
 * test runs is done when it asks, through artisan tinker: a lesson to go
 * into now (`lessonNow`), staff approving a receipt (`approveProof`), the academy
 * booking free trials itself for a while (`trialBooking`) and a free trial's time
 * taken by someone else between seeing it and confirming it (`takeTrialSlot`).
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
                    $this->reviewLastWeek($student);
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
                $group = $this->group();
                $quran = $this->quran();
                // Families book their own free trials, as the demo has it (a test may have switched the academy to booking them).
                SiteSettings::query()->where('trial_booking', '!=', 'family')->update(['trial_booking' => 'family']);
            } finally {
                CarbonImmutable::setTestNow();
                AuditActor::forget();
            }

            return ['homework' => ['hand_in' => self::HAND_IN, 'to_mark' => self::TO_MARK], 'group' => $group, 'quran' => $quran];
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

    /**
     * Who books the demo's free trials from its page: `staff` (the family sends a request) or `family` (it picks
     * its own time), as the owner would set it in Settings › The academy's page; the reset puts back `family`.
     */
    public static function trialBooking(string $mode): string
    {
        if (! in_array($mode, ['family', 'staff'], true)) {
            throw new RuntimeException("Not family or staff: {$mode}.");
        }
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($mode): string {
            SiteSettings::query()->update(['trial_booking' => $mode]);

            return $mode;
        });
    }

    /**
     * Someone else takes a free trial's time (UTC) while a family looks at it: every teacher free then is held for
     * ten minutes, as a coordinator's booking hold does, so the time leaves the list and booking it is refused as
     * taken; the holds lapse by themselves. Returns how many teachers were held.
     */
    public static function takeTrialSlot(string $utc, int $minutes = 30): int
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($utc, $minutes): int {
            $at = CarbonImmutable::parse($utc)->utc()->getTimestamp();
            $held = 0;
            foreach (DB::table('user_roles')->where('role', 'teacher')->distinct()->pluck('user_id') as $teacher) {
                try {
                    app(PlaceBookingHold::class)->handle((string) $teacher, null, [$at], $minutes, (string) Str::uuid());
                    $held++;
                } catch (ValidationException) {
                    // Busy then already.
                }
            }

            return $held;
        });
    }

    /**
     * The links a family's messages carry for the free trial asked for with this number (the latest request):
     * `pick` (the instant reply's "pick a time", while it waits for one), and `change`/`cancel` (the booked
     * trial's own), as the tests' stand-in for reading WhatsApp. Returns them as JSON, paths on the academy's host.
     */
    public static function trialLinks(string $phone, string $locale = 'ar'): string
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($phone, $locale): string {
            $request = JoinRequest::query()->where('phone', $phone)->where('kind', 'trial')->latest('created_at')->first()
                ?? throw new RuntimeException("No free trial request from {$phone}.");
            $path = fn (string $url) => (string) parse_url($url, PHP_URL_PATH);
            $links = ['pick' => $path(app(PickLinks::class)->url($request->forceFill(['locale' => $locale])))];
            $session = $request->session_id !== null ? Session::query()->find($request->session_id) : null;
            if ($session !== null) {
                foreach (app(TrialLinks::class)->links($session, $locale) as $kind => $url) {
                    $links[$kind] = $path($url);
                }
            }

            return (string) json_encode($links);
        });
    }

    /**
     * After a free trial (docs/architecture/02, section 27, J2), for the portal's trial-report tests: a family new
     * to this run (its own number) whose child (9, the kids' look) took a Tajweed trial with Sara two days ago, as
     * the screens would have it (booked, both came, the attendance sweep closed it, Sara assessed it with what
     * the family reads), so its report is ready with an open offer and a second free trial to book; and another
     * family whose child missed a Quran trial yesterday, for the "book again" link. The academy gives both the
     * offer (10%, 72 hours) and the second trial, as the demo does. Returns JSON: the links' paths (`report`,
     * `report_en`, `rebook`), the report's trial (its lesson's id, `session`) and a token each for the first
     * family's guardian and child (the portal's session cookie; neither has a password).
     */
    public static function trialAfter(): string
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function (): string {
            $id = fn (string $email) => (string) DB::table('users')->where('email', $email)->value('id');
            [$sara, $sales] = [$id('sara@demo.test'), $id('sales@demo.test') ?: $id('owner@demo.test')];
            $subject = fn (string $en) => (string) DB::table('subjects')->where('name_en', $en)->value('id');
            $settings = CrmSettings::query()->first() ?? new CrmSettings;
            $settings->fill(['trial_offer' => true, 'trial_offer_percent' => 10, 'trial_offer_hours' => 72, 'second_trial' => true])->save();
            SiteSettings::query()->where('trial_booking', '!=', 'family')->update(['trial_booking' => 'family']);
            $number = substr((string) hrtime(true), -8);

            $family = function (string $phone, string $parent, string $child, string $subjectId, int $daysAgo, bool $came) use ($sara, $sales): LeadTrial {
                $lead = app(CreateLead::class)->handle(['contact_name' => $parent, 'phone_e164' => $phone, 'country' => 'EG', 'timezone' => 'Africa/Cairo',
                    'for_child' => true, 'learner_name' => $child, 'learner_birth_year' => (int) CarbonImmutable::now()->year - 9, 'subject_id' => $subjectId,
                    'source' => 'website', 'locale' => 'ar'], $sales);
                $learner = app(PrepareLearner::class)->handle($lead, $sales);
                // That day, else the days before it (a day fills up after many runs): any half hour Sara is free.
                foreach (range($daysAgo, $daysAgo + 4) as $back) {
                    $day = CarbonImmutable::now()->subDays($back)->setTimezone('Africa/Cairo')->toDateString();
                    foreach (range(16, 45) as $half) {
                        $at = CarbonImmutable::parse($day, 'Africa/Cairo')->addMinutes($half * 30)->utc();
                        try {
                            $session = app(ScheduleSession::class)->handle(new NewSession($sara, $learner, $subjectId, $at, 30, 'Africa/Cairo', 'trial', 'free'), $sales);
                        } catch (ValidationException) {
                            continue;
                        }
                        event(new TrialLessonBooked($session->academy_id, $session->id, $learner, $sara, $subjectId, $at->toIso8601ZuluString(), $sales));
                        app(RecordParticipantJoin::class)->handle($session->refresh(), $sara, $at->subMinute());
                        if ($came) {
                            app(RecordParticipantJoin::class)->handle($session->refresh(), $learner, $at->addMinute());
                        }
                        app(ApplyAttendanceDeadlines::class)->handle($at->addMinutes(45));

                        return LeadTrial::query()->with('lead')->where('session_id', $session->id)->firstOrFail();
                    }
                }
                throw new RuntimeException('Sara has no free half hour in the days before for a trial.');
            };

            $trial = $family("+2010{$number}", 'أم ليان', 'ليان', $subject('Tajweed'), 2, true);
            $plan = collect(app(BillingApi::class)->planOptions(30))->first(fn ($p) => $p->sessionsPerWeek === 2);
            app(AssessTrial::class)->handle($trial, [
                'level' => 'المستوى الأول: تقرأ قراءة صحيحة',
                'plan_id' => $plan?->id,
                'per_week' => 2,
                'notes' => 'للأكاديمية: تحتاج معلّمة صبورة.',
                'strengths' => "تقرأ بصوت واضح وثقة.\nتحفظ بسرعة، وتسأل حين لا تفهم.",
                'to_work_on' => "أحكام النون الساكنة والتنوين.\nالمدود، وأن تتمهّل في آخر الآية.",
                'family_note' => 'سعدت بالتعرف إلى ليان. أساسها جيد، وبحصتين في الأسبوع ستتقن أحكام المستوى الأول خلال شهرين.',
            ], $sara);
            $trial->refresh();
            $missed = $family("+2011{$number}", 'أبو آدم', 'آدم', $subject('Quran memorisation'), 1, false);
            if ($missed->refresh()->status !== 'missed' || $trial->status !== 'attended') {
                throw new RuntimeException("The trials did not close as expected: {$trial->status}, {$missed->status}.");
            }

            $links = app(ReportLinks::class);
            $path = fn (string $url) => (string) parse_url($url, PHP_URL_PATH);
            // A personal access token as signing in would give one (the accounts have no password; the guard's own model, never Identity's internals).
            $accounts = (string) config('auth.providers.users.model');
            $token = fn (?string $user) => $accounts::query()->findOrFail($user)->createToken('e2e')->plainTextToken;

            return (string) json_encode([
                'report' => $path($links->report($trial, 'ar')),
                'report_en' => $path($links->report($trial, 'en')),
                'rebook' => $path($links->rebook($missed, 'ar')),
                'session' => $trial->session_id,
                'guardian_token' => $token($trial->lead->contact_id),
                'learner_token' => $token($trial->lead->learner_id),
            ]);
        });
    }

    /** The offer of the trial with this lesson ends now (its moment just passed), as it would on its own after its hours. */
    public static function endTrialOffer(string $sessionId): string
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($sessionId): string {
            $coupon = (string) LeadTrial::query()->where('session_id', $sessionId)->value('offer_coupon_id');
            $past = CarbonImmutable::now()->subMinute();
            DB::table('coupons')->where('id', $coupon)->update(['expires_at' => $past]);

            return $coupon;
        });
    }

    /**
     * A plan's price in the region of this currency changes, as the office would change it while a family looks at it
     * (J3's price_changed): the new price in minor units; returns the old one, for the test to put back.
     */
    public static function planPrice(string $planId, string $currency, int $minor): int
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($planId, $currency, $minor): int {
            $row = DB::table('plan_prices')->where('plan_id', $planId)->where('currency', strtoupper($currency))->first(['id', 'amount_minor'])
                ?? throw new RuntimeException("No {$currency} price for plan {$planId}.");
            DB::table('plan_prices')->where('id', $row->id)->update(['amount_minor' => $minor, 'updated_at' => now()]);

            return (int) $row->amount_minor;
        });
    }

    /**
     * The courses the end-to-end families started on their own (J3; trialAfter's children, all named ليان) end today,
     * as the office would end them (EndEnrollment), so the demo's teachers keep their weekly room from run to run.
     * Returns how many ended.
     */
    public static function endTestStarts(): int
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function (): int {
            $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');
            $today = CarbonImmutable::now()->setTimezone((string) (DB::table('academies')->value('default_timezone') ?: 'UTC'))->toDateString();
            $ids = DB::table('course_starts as c')->join('users as u', 'u.id', '=', 'c.learner_id')->join('enrollments as e', 'e.id', '=', 'c.enrollment_id')
                ->where('u.name', 'ليان')->whereNull('c.cancelled_at')->whereNotIn('e.status', ['ended', 'cancelled'])->whereNull('e.ends_on')
                ->pluck('c.enrollment_id');
            $ended = 0;
            foreach ($ids as $id) {
                try {
                    app(EndEnrollment::class)->handle((string) $id, $today, 'E2E', $owner ?: null);
                    $ended++;
                } catch (ValidationException) {
                    // Ended or ending already.
                }
            }

            return $ended;
        });
    }

    /**
     * The free trials earlier end-to-end runs booked for families of their own (their numbers: +2010/+2011 from
     * trialAfter, +9665 from the trial booking tests) and that are still to come are cancelled by the academy
     * (CancelSession), so they stop filling the demo teachers' days (their daily cap) run after run. Returns how many.
     */
    public static function freeTestTrials(): int
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function (): int {
            $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');
            $learners = DB::table('leads')->whereNotNull('learner_id')
                ->where(fn ($q) => $q->where('phone_e164', 'like', '+2010%')->orWhere('phone_e164', 'like', '+2011%')->orWhere('phone_e164', 'like', '+9665%'))
                ->pluck('learner_id')->all();
            $sessions = Session::query()->where('type', 'trial')->where('status', 'scheduled')->where('starts_at', '>', now())
                ->whereHas('participants', fn ($q) => $q->whereIn('user_id', $learners)->where('role', 'student'))->get();
            $done = 0;
            foreach ($sessions as $session) {
                try {
                    app(CancelSession::class)->handle($session, 'academy', 'other', 'E2E', $owner ?: null, true);
                    $done++;
                } catch (ValidationException) {
                    // Past its time to cancel.
                }
            }

            return $done;
        });
    }

    /** A general coupon the academy hands out (a percentage off one block, any plan), saved as the office would (SaveCoupon); returns its code. */
    public static function coupon(string $code, int $percent): string
    {
        $academy = self::demo(app(TenantDirectory::class));

        return app(TenantContext::class)->run($academy, function () use ($code, $percent): string {
            $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');
            $found = DB::table('coupons')->where('code', strtoupper($code))->value('id');
            $saved = app(SaveCoupon::class)->handle($found === null ? null : (string) $found, [
                'code' => strtoupper($code), 'name' => 'E2E', 'kind' => 'percent', 'percent' => $percent, 'blocks' => 1, 'is_active' => true,
            ], $owner ?: null);

            return (string) $saved->code;
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

    /**
     * Last week's lessons left to review, decided as held (the owner, as staff would on the board). The demo leaves
     * one lesson to review an hour before it was seeded; just after midnight on the student's clock on a Friday
     * that lesson falls in last week, and last week's chest would wait on it.
     */
    private function reviewLastWeek(string $student): void
    {
        $id = $this->ids[$student];
        $week = $this->now->setTimezone($this->zone($id))->startOfWeek(ChallengeCatalog::WEEK_STARTS)->startOfDay();
        $waiting = Session::query()->where('status', SessionStatus::NeedsReview->value)
            ->where('starts_at', '>=', $week->subWeek()->utc())->where('starts_at', '<', $week->utc())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $id)->where('role', 'student'))->get();
        foreach ($waiting as $session) {
            // A disputed one needs the academy's answer, which its author reads.
            app(ResolveReview::class)->handle($session, 'completed', 'راجعنا الحصة: حضرها الطرفان كاملة.', $this->ids['owner']);
        }
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

    /**
     * The children's group (DemoGroupsSeeder: Mona's Arabic group, Lina and Yahya whose mother Huda pays, and Tala),
     * as the group tests need it: lessons since the demo was seeded happen (everyone comes, the sweep closes them);
     * the next lesson far enough ahead to excuse in good time has Huda's children seated again where a run excused
     * them; and the last lesson held has its reports to write again where a run handed them in. Its ids and the
     * family's sign-ins (the demo password) go into the fixtures; null on a demo without groups.
     *
     * @return ?array{id: string, lesson_id: ?string, report_id: ?string, guardian: string, student: string, teacher: string}
     */
    private function group(): ?array
    {
        $lina = $this->ids['lina'] ?? null;
        $group = $lina === null ? null : DB::table('group_members as m')->join('lesson_groups as g', 'g.id', '=', 'm.group_id')
            ->where('m.student_id', $lina)->whereNull('m.left_on')->where('g.status', 'running')->first(['g.id', 'g.teacher_id']);
        if ($group === null) {
            return null;
        }
        $family = array_values(array_filter([$lina, $this->ids['yahya'] ?? null]));

        // Lessons since the demo was seeded, held.
        $past = Session::query()->where('group_id', $group->id)->where('status', 'scheduled')->where('ends_at', '<', $this->now->subMinutes(15))
            ->orderBy('starts_at')->with('participants')->get();
        foreach ($past as $session) {
            CarbonImmutable::setTestNow($session->starts_at->addMinute());
            app(RecordParticipantJoin::class)->handle($session->refresh(), $session->teacher_id);
            foreach ($session->participants->filter(fn ($p) => $p->role->value === 'student' && $p->is_active) as $seat) {
                CarbonImmutable::setTestNow($session->starts_at->addMinutes(2));
                app(RecordParticipantJoin::class)->handle($session->refresh(), $seat->user_id);
            }
            CarbonImmutable::setTestNow($session->ends_at->addMinutes(11));
            app(ApplyAttendanceDeadlines::class)->handle();
        }
        CarbonImmutable::setTestNow();

        // The next lesson to excuse in good time: the family's seats a run excused are taken again (a new reservation).
        $notice = (int) PolicyVersion::current()->policy()->get('student_notice_hours');
        $lesson = Session::query()->where('group_id', $group->id)->where('status', 'scheduled')
            ->where('starts_at', '>', $this->now->addHours($notice + 2))->orderBy('starts_at')->first();
        if ($lesson !== null) {
            $excused = SessionParticipant::query()->where('session_id', $lesson->id)->whereIn('user_id', $family)
                ->where('outcome', GroupOutcomes::EXCUSED)->get();
            foreach ($excused as $seat) {
                DB::transaction(function () use ($lesson, $seat) {
                    SessionParticipant::query()->where('session_id', $lesson->id)->where('user_id', $seat->user_id)->delete();
                    SessionEvent::record($lesson, SessionEventKind::DecisionLogged, ['decision' => 'student_removed', 'user_id' => $seat->user_id, 'reason' => 'e2e_reset']);
                    app(GroupSeats::class)->seat($lesson, $seat->user_id, (string) $seat->enrollment_id, null);
                });
                app(FundSessions::class)->handle((string) $seat->enrollment_id);
            }
        }

        // The last lesson held: its reports to write again where a run handed them in.
        $held = Session::query()->where('group_id', $group->id)->whereIn('status', ['completed', 'student_absent'])->orderByDesc('starts_at')->first();
        $report = null;
        if ($held !== null) {
            $reports = SessionReport::query()->where('session_id', $held->id)->get();
            $redo = $reports->filter(fn (SessionReport $r) => $r->status === 'submitted' && $r->submitted_at?->greaterThan($this->seeded));
            foreach ($redo as $r) {
                Homework::query()->where('report_id', $r->id)->where('created_at', '>', $this->seeded)->get()->each->delete();
                $r->items()->delete();
                $r->forceFill(['status' => $r->due_at->isPast() ? 'overdue' : 'due', 'submitted_at' => null, 'late' => false, 'engagement' => null, 'overall' => null,
                    'notes_for_student' => null, 'notes_internal' => null, 'absence_note' => null, 'answers' => [], 'template_id' => null])->save();
            }
            $report = $reports->sortBy(fn (SessionReport $r) => [$r->kind !== 'lesson', $r->student_id])->first()?->id;
        }
        $email = fn (?string $id) => $id === null ? null : (string) DB::table('users')->where('id', $id)->value('email');

        return [
            'id' => (string) $group->id,
            'lesson_id' => $lesson?->id,
            'report_id' => $report,
            'guardian' => 'lina.guardian@demo.test',
            'student' => 'lina@demo.test',
            'teacher' => $email($group->teacher_id),
        ];
    }

    /**
     * Yousef's Quran course (DemoQuranPlansSeeder: Juz Amma with Nour, his certificate for the first surah he was
     * examined on), as the Quran tests need it: the academy holds exams and shows certificates to families, and a
     * lesson of the course to record now (from the room's opening, ten minutes before its start, as
     * RecordQuranLesson allows; a lesson held may still be recorded): one of today's not yet recorded, else one booked
     * now when Nour and Yousef are both free, else earlier today (the demo's live board has them together now). Null on a demo without the Quran (its area, its text or the plan).
     *
     * @return ?array{teacher: string, guardian: string, student: string, lesson_id: string, enrollment_id: string, certificate_code: ?string, certificate_number: ?string}
     */
    private function quran(): ?array
    {
        $yousef = $this->ids['yousef'];
        $course = DB::table('enrollments as e')->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.student_id', $yousef)->where('s.kind', 'quran')->orderBy('e.created_at')->first(['e.id', 'e.teacher_id', 'e.subject_id']);
        if (! app(QuranArea::class)->on() || $course === null || app(Plans::class)->forEnrollment((string) $course->id) === null) {
            return null;
        }
        app(SaveQuranSettings::class)->handle(['exams_enabled' => true, 'certificates_enabled' => true, 'certificates_family' => true]);

        $recorded = QuranLessonResult::query()->select('session_id');
        $today = collect(['Africa/Cairo', $this->zone($yousef)])->map(fn (string $zone) => $this->now->setTimezone($zone)->startOfDay()->utc())->max();
        $lesson = Session::query()->where('enrollment_id', $course->id)->where('type', 'regular')->whereIn('status', ['scheduled', 'in_progress', 'completed'])
            ->where('starts_at', '>=', $today)->where('starts_at', '<=', $this->now->addMinutes(10))
            ->whereNotIn('id', $recorded)->orderByDesc('starts_at')->first();
        if ($lesson === null) {
            // Now, or a few minutes on; else earlier today, when one of them is in another lesson now (held or not, it may be recorded).
            $starts = collect([...range(0, 10), ...range(-1, -5), ...range(-10, -600, 5)])->map(fn (int $m) => $this->now->addMinutes($m)->startOfMinute())->filter(fn ($at) => $at >= $today);
            foreach ([30, 20] as $minutes) {
                foreach ($starts as $at) {
                    try {
                        $lesson = app(ScheduleSession::class)->handle(new NewSession(
                            teacherId: (string) $course->teacher_id, studentId: $yousef, subjectId: (string) $course->subject_id, startsAt: $at,
                            durationMin: $minutes, anchorTimezone: 'Africa/Cairo', funding: 'unfunded', enrollmentId: (string) $course->id,
                        ));
                        break 2;
                    } catch (ValidationException) {
                        // One of them has a lesson then.
                    }
                }
            }
            if ($lesson === null) {
                throw new RuntimeException('No Quran lesson could be booked now for Yousef: he or his teacher is busy.');
            }
            app(FundSessions::class)->handle((string) $course->id);
        }
        $certificate = QuranCertificate::query()->where('student_id', $yousef)->whereNull('revoked_at')->orderByDesc('issued_at')->first();

        return [
            'teacher' => (string) DB::table('users')->where('id', $course->teacher_id)->value('email'),
            'guardian' => 'yousef.guardian@demo.test',
            'student' => 'yousef@demo.test',
            'lesson_id' => (string) $lesson->id,
            'enrollment_id' => (string) $course->id,
            'certificate_code' => $certificate === null ? null : CertificateNumber::display($certificate->code),
            'certificate_number' => $certificate?->number,
        ];
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
