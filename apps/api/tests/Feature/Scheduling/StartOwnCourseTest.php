<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\SaveSelfServeEnrol;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Filament\Resources\Plans\ManagePlans;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Actions\SaveTrialOffer;
use Modules\Crm\Database\Seeders\DemoSelfStartSeeder;
use Modules\Crm\Models\LeadTrial;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\Events\EnrollmentActivated;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\Groups\CreateGroup;
use Modules\Scheduling\Actions\Holidays\DeclareHoliday;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\NewGroup;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\CourseStart;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Money\Money;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The family starts a course and pays on its own (docs/architecture/02,
 * section 27, J3). The fixture: Monday 5 October 2026 in Riyadh; a Cairo
 * teacher (a woman) and a Riyadh teacher (a man) teach Quran on Tuesdays and
 * Saturdays; the plan is 8 lessons, two a week, 30 minutes, SAR 400 in the
 * Gulf. Umm Yara's nine-year-old had her free trial with the Cairo teacher
 * on Tuesday 6 October and was assessed: the report recommends the plan,
 * with a 10% offer for 72 hours. It is now 40 minutes after the trial.
 */

const J3_API = 'http://noor.lms.test/api/v1/';

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->sales = User::factory()->withRole(Role::Sales)->create();
    SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::defaults()]);
    CommunicationSettings::query()->create(['whatsapp_enabled' => true, 'email_enabled' => true, 'push_enabled' => true]);
    $this->plan = Plan::query()->sole();
    $this->region = PriceRegion::query()->sole();
    $this->actingAs($this->admin);
    app(SaveTrialOffer::class)->handle(true, 10, 72);
    $this->trial = j3Trial();
    $this->yara = $this->trial->lead->learner_id;
    $this->mother = User::query()->findOrFail($this->trial->lead->contact_id);
    $this->token = $this->mother->createToken('phone')->plainTextToken;
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/**
 * Umm Yara's lead, the trial with the Cairo teacher on Tuesday 19:00 Riyadh, held and assessed (the offer is made);
 * or another family's ($lead over hers), with this teacher at this moment.
 */
function j3Trial(array $lead = [], ?string $teacherId = null, string $at = '2026-10-06 19:00'): LeadTrial
{
    $lead = app(CreateLead::class)->handle($lead + [
        'contact_name' => 'Umm Yara', 'phone_e164' => '+966501234567', 'email' => 'umm.yara@example.test', 'country' => 'SA', 'timezone' => 'Asia/Riyadh',
        'for_child' => true, 'learner_name' => 'Yara Salem', 'learner_birth_year' => 2017, 'subject_id' => test()->quran, 'source' => 'website',
    ], test()->sales->id);
    $learner = app(PrepareLearner::class)->handle($lead, test()->sales->id);
    $batch = app(PlaceBookingHold::class)->handle($teacherId ?? test()->teacher->id, $learner, [CarbonImmutable::parse($at, 'Asia/Riyadh')->getTimestamp()], 30, test()->admin->id);
    $session = app(ConfirmBooking::class)->trial($batch, $learner, test()->quran, test()->admin->id);
    $trial = LeadTrial::query()->with('lead')->where('session_id', $session->id)->sole();

    CarbonImmutable::setTestNow($trial->starts_at->addMinutes(40));
    DB::table('sessions')->where('id', $trial->session_id)->update(['status' => 'completed']);
    event(new SessionFinalized(test()->academy->id, $trial->session_id, 'completed', []));
    app(AssessTrial::class)->handle($trial->refresh(), [
        'level' => 'Beginner', 'plan_id' => test()->plan->id, 'per_week' => 2, 'strengths' => 'Clear voice.', 'notes' => 'For the academy.',
    ], $trial->teacher_id);

    return $trial->refresh();
}

/** A request to Noor's API as this token (Umm Yara's by default), then back in Noor. */
function j3Api(string $method, string $path, array $data = [], ?string $token = null, ?string $key = null): TestResponse
{
    app('auth')->forgetGuards();
    $request = test()->withToken($token ?? test()->token)->withHeaders(array_filter(['Accept-Language' => 'ar', 'Idempotency-Key' => $key ?? ($method === 'GET' ? null : 'k-'.bin2hex(random_bytes(6)))]));
    $response = $request->json($method, J3_API.$path, $data);
    test()->actAsTenant(test()->academy);

    return $response;
}

function j3Consent(): void
{
    app(GrantConsent::class)->handle(test()->yara, test()->mother->id, 'family');
}

/** The Cairo teacher's weekly times for Yara on the plan, from the API. */
function j3Slots(?string $teacherId = null, ?string $token = null): array
{
    return j3Api('GET', 'family/start/slots?'.http_build_query(['learner_id' => test()->yara, 'subject_id' => test()->quran, 'plan_id' => test()->plan->id,
        'teacher_id' => $teacherId ?? test()->teacher->id, 'timezone' => 'Asia/Riyadh']), token: $token)->assertOk()->json('data');
}

/** Starts the plan with the suggested pattern (or these keys). */
function j3Start(array $over = [], ?string $token = null, ?string $key = null): TestResponse
{
    $times = $over['times'] ?? j3Slots($over['teacher_id'] ?? null)['suggested'];

    return j3Api('POST', 'family/courses', $over + [
        'learner_id' => test()->yara, 'subject_id' => test()->quran, 'plan_id' => test()->plan->id, 'price_minor' => 40000, 'currency' => 'SAR',
        'teacher_id' => test()->teacher->id, 'times' => $times, 'timezone' => 'Asia/Riyadh',
    ], $token, $key);
}

it('offers the family its learners, the subjects, the plans priced for it, the trial\'s teacher first and the open offer', function () {
    $data = j3Api('GET', 'family/start/options?'.http_build_query(['learner_id' => $this->yara, 'subject_id' => $this->quran]))->assertOk()->json('data');

    expect($data['self_serve'])->toBeTrue()
        ->and($data['learner']['id'])->toBe($this->yara)
        // PrepareLearner linked her without asking: the consent is asked when she starts a course.
        ->and(collect($data['learners'])->firstWhere('id', $this->yara)['consent_waiting'])->toBeTrue()
        ->and(collect($data['subjects'])->firstWhere('id', $this->quran))->toMatchArray(['can_start' => true, 'course' => null, 'why_not' => null])
        ->and($data['region']['currency'])->toBe('SAR')
        ->and($data['plans'][0])->toMatchArray(['id' => $this->plan->id, 'format' => 'private', 'session_minutes' => 30, 'sessions_per_week' => 2, 'sessions_per_block' => 8, 'renews_at_lessons_left' => 2])
        ->and($data['plans'][0]['price'])->toMatchArray(['amount_minor' => 40000, 'currency' => 'SAR'])
        ->and($data['plans'][0]['per_lesson']['amount_minor'])->toBe(5000)
        // The offer would take 10% off the first invoice.
        ->and($data['plans'][0]['first_invoice']['total']['amount_minor'])->toBe(36000)
        ->and($data['plans'][0]['first_invoice']['offer_applied'])->toBeTrue()
        ->and($data['offer'])->toMatchArray(['state' => 'open', 'percent' => 10])
        ->and($data['teachers'][0])->toMatchArray(['id' => $this->teacher->id, 'trial_teacher' => true, 'private' => true])
        ->and(array_column($data['teachers'], 'id'))->toContain($this->other->id)
        ->and($data['trial'])->toMatchArray(['session_id' => $this->trial->session_id, 'teacher_available' => true])
        ->and($data['payment'])->toBe(['unpaid_cancel_hours' => 24, 'notice_hours' => 36])
        // Names only: never an email or a number.
        ->and(json_encode($data))->not->toContain('@')->not->toContain($this->teacher->email);
});

it('gives the teacher\'s weekly times on the family\'s clock, on half hours, far enough ahead to pay, never when the learner is busy', function () {
    $slots = j3Slots();
    $times = collect($slots['days'])->flatMap(fn ($d) => $d['times']);

    expect($slots['format'])->toBe('private')
        ->and($slots['per_week'])->toBe(2)
        ->and($slots['timezone'])->toBe('Asia/Riyadh')
        ->and(array_column($slots['days'], 'weekday'))->toEqualCanonicalizing([2, 6])
        ->and($times->every(fn ($t) => in_array(substr($t['start'], 3), ['00', '30'], true)))->toBeTrue()
        ->and($slots['suggested'])->toHaveCount(2)
        // The other student's lessons with her at 18:00 are not offered.
        ->and($times->pluck('start')->all())->not->toContain('18:00');
    // The first lesson leaves 36 hours: 24 before rule 16 would cancel it unpaid, and 12 to pay.
    $now = CarbonImmutable::now();
    foreach ($times as $t) {
        expect(CarbonImmutable::parse($t['first']['utc'])->greaterThan($now->addHours(36)))->toBeTrue();
    }

    // Yara has a lesson with the other teacher at one of these times: that weekly time goes.
    $busy = $times->first(fn ($t) => $t['start'] >= '19:00' && $t['start'] <= '21:00');
    $at = CarbonImmutable::parse($busy['first']['utc']);
    $batch = app(PlaceBookingHold::class)->handle($this->other->id, $this->yara, [$at->getTimestamp()], 30, $this->admin->id);
    app(ConfirmBooking::class)->trial($batch, $this->yara, $this->quran, $this->admin->id);
    expect(collect(j3Slots()['days'])->flatMap(fn ($d) => $d['times'])->pluck('key')->all())->not->toContain($busy['key']);
});

it('starts the course: its lessons waiting to be paid, the invoice with the offer on it, the pay link and the window to pay in', function () {
    j3Consent();
    $answer = j3Start()->assertCreated()->json('data');

    $invoice = $answer['invoice'];
    expect($answer['status'])->toBe('pending_payment')
        ->and($answer['teacher']['id'])->toBe($this->teacher->id)
        ->and($answer['weekly_times'])->toHaveCount(2)
        ->and($answer['lessons'])->toBeGreaterThan(0)
        ->and($answer['offer'])->toMatchArray(['state' => 'applied', 'percent' => 10])
        ->and($invoice['currency'])->toBe('SAR')
        ->and(array_column($invoice['lines'], 'kind'))->toBe(['block', 'discount'])
        ->and($invoice['lines'][1]['amount_minor'])->toBe(-4000)
        ->and($invoice['total']['amount_minor'])->toBe(36000)
        ->and($invoice['vat'])->toMatchArray(['rate' => 0, 'amount_minor' => 0])
        ->and($invoice['coupon'])->toMatchArray(['personal' => true])
        ->and($answer['pay_url'])->toContain('/pay/invoice/'.$invoice['id'])->toContain('signature=')
        ->and($answer['payment']['unpaid_cancel_hours'])->toBe(24)
        ->and($answer['payment']['renews'])->toContain('2')
        ->and($answer['can_cancel'])->toBeTrue();
    // Pay before the first lesson's unpaid deadline: 24 hours before it. The invoice is due at that same moment,
    // not the academy's general days (nor the offer's end): one deadline on every screen.
    $payBy = CarbonImmutable::parse($answer['payment']['pay_by']['utc']);
    expect($payBy->equalTo(CarbonImmutable::parse($answer['first_lesson']['utc'])->subHours(24)))->toBeTrue()
        ->and(CarbonImmutable::parse($invoice['due_at']['utc'])->equalTo($payBy))->toBeTrue()
        ->and(Invoice::query()->findOrFail($invoice['id'])->due_at->equalTo($payBy))->toBeTrue();
    // The lessons wait unpaid; the course is the family's own start, audited.
    expect(DB::table('sessions')->where('enrollment_id', $answer['enrollment_id'])->where('funding_status', 'unfunded')->count())->toBe($answer['lessons'])
        ->and(CourseStart::query()->where('enrollment_id', $answer['enrollment_id'])->sole()->only(['payer_id', 'price_minor', 'offer_applied']))
        ->toBe(['payer_id' => $this->mother->id, 'price_minor' => 40000, 'offer_applied' => true])
        ->and(DB::table('audit_logs')->where('action', 'enrollment.started_own')->count())->toBe(1);

    // The trial's report now says the course began: no offer, and its start points at the course.
    $report = j3Api('GET', "family/trials/{$this->trial->session_id}?learner_id={$this->yara}")->assertOk()->json('data');
    expect($report['offer_state'])->toBe('started')
        ->and($report['start'])->toMatchArray(['available' => false, 'reason' => 'course_pending_own', 'enrollment_id' => $answer['enrollment_id']]);
});

it('asks for the guardian\'s consent first, then starts once it is given', function () {
    $refused = j3Start()->assertStatus(409);
    expect($refused->json('type'))->toBe('guardian-consent-needed')
        ->and($refused->json('reason'))->toBe('consent')
        ->and($refused->json('student_id'))->toBe($this->yara)
        ->and($refused->json('detail'))->toContain('موافقتكم');

    $version = j3Api('GET', 'family/consents')->assertOk()->json('children.0.text.version');
    j3Api('POST', "family/consents/{$this->yara}", ['agree' => true, 'version' => $version])->assertOk();
    j3Start()->assertCreated();
});

it('refuses a price that changed since the family saw it, with the new price, and a plan no longer offered', function () {
    j3Consent();
    DB::table('plan_prices')->where('plan_id', $this->plan->id)->update(['amount_minor' => 44000]);
    $refused = j3Start()->assertStatus(409);
    expect($refused->json('reason'))->toBe('price_changed')
        ->and($refused->json('price.amount_minor'))->toBe(44000)
        ->and($refused->json('detail'))->toContain('440')
        // The Arabic amount ends in "ر.س." already: the sentence reads it once, never "ر.س..".
        ->and(preg_replace('/[\x{2068}\x{2069}]/u', '', (string) $refused->json('detail')))->toContain('ر.س.)')->not->toContain('..');

    j3Start(['price_minor' => 44000])->assertCreated();
    expect(Invoice::query()->where('status', 'issued')->latest('issued_at')->first()->total_minor)->toBe(39600);
});

it('refuses a withdrawn plan kindly', function () {
    j3Consent();
    $times = j3Slots()['suggested'];
    $this->plan->update(['is_active' => false]);
    expect(j3Start(['times' => $times])->assertStatus(422)->json('reason'))->toBe('no_plan');
});

it('refuses a time taken meanwhile and gives the times free now', function () {
    j3Consent();
    $slots = j3Slots();
    $key = $slots['suggested'][0];
    $first = collect($slots['days'])->flatMap(fn ($d) => $d['times'])->firstWhere('key', $key)['first']['utc'];
    // The office books someone else at that time a moment before.
    app(PlaceBookingHold::class)->handle($this->teacher->id, null, [CarbonImmutable::parse($first)->getTimestamp()], 30, $this->sales->id);

    $refused = j3Start(['times' => $slots['suggested']])->assertStatus(409);
    expect($refused->json('reason'))->toBe('time_taken')
        ->and(array_column($refused->json('times'), 'key'))->not->toContain($key)
        ->and($refused->json('times.0.label'))->not->toBeEmpty()
        ->and(DB::table('enrollments')->where('student_id', $this->yara)->count())->toBe(0);
});

it('starts once for a double tap: the same key answers again, another key reads the course already started', function () {
    j3Consent();
    $times = j3Slots()['suggested'];
    $first = j3Start(['times' => $times], key: 'tap-1')->assertCreated();
    $again = j3Start(['times' => $times], key: 'tap-1')->assertCreated();
    expect($again->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($again->json('data.enrollment_id'))->toBe($first->json('data.enrollment_id'));

    $second = j3Start(['times' => $times], key: 'tap-2')->assertStatus(409);
    expect($second->json('reason'))->toBe('course_pending_own')
        ->and($second->json('enrollment_id'))->toBe($first->json('data.enrollment_id'))
        ->and(DB::table('enrollments')->where('student_id', $this->yara)->count())->toBe(1);
});

it('starts at the plain price and says so when the offer ended before the family confirmed', function () {
    j3Consent();
    $times = j3Slots()['suggested'];
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(73));
    $answer = j3Start(['times' => j3Slots()['suggested']])->assertCreated()->json('data');

    expect($answer['offer']['state'])->toBe('ended')
        ->and($answer['offer']['message'])->toContain('بسعره العادي')
        ->and($answer['invoice']['total']['amount_minor'])->toBe(40000)
        ->and($times)->toHaveCount(2);
});

it('lets the bigger of the sibling discount and the offer apply, never both', function () {
    j3Consent();
    app(SaveSiblingDiscount::class)->handle(20, 20, 7);
    // Umm Yara already pays for Yara's brother's course (the office enrolled him): Yara is her second child.
    $brother = User::factory()->withRole(Role::Student)->create(['timezone' => 'Asia/Riyadh']);
    app(PeopleApi::class)->saveStudentProfile($brother->id, CarbonImmutable::parse('2000-01-01'), null);
    app(PeopleApi::class)->linkGuardian($this->mother->id, $brother->id, askConsent: false);
    $times = array_map(fn (string $at) => CarbonImmutable::parse($at, 'Asia/Riyadh')->getTimestamp(), ['2026-10-13 21:00', '2026-10-17 21:00']);
    $batch = app(PlaceBookingHold::class)->handle($this->other->id, $brother->id, $times, 30, $this->admin->id);
    app(EnrollStudent::class)->handle($batch, $brother->id, $this->quran, new EnrollmentTerms($this->plan->id, $this->mother->id, $this->region->id), $this->admin->id);

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinute());     // he came first
    $answer = j3Start()->assertCreated()->json('data');
    expect($answer['offer']['state'])->toBe('family_discount_bigger')
        ->and($answer['offer']['message'])->toContain('الإخوة')
        ->and($answer['invoice']['total']['amount_minor'])->toBe(32000)
        ->and(collect($answer['invoice']['lines'])->where('kind', 'discount')->count())->toBe(1);
});

it('refuses when the academy\'s plan has no room for another student', function () {
    j3Consent();
    $times = j3Slots()['suggested'];
    $plan = SaasPlan::query()->create(['code' => 'tiny', 'name_ar' => 'صغيرة', 'name_en' => 'Tiny', 'max_teachers' => 5, 'max_active_students' => 1, 'features' => ['crm', 'online_payments']]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');

    $refused = j3Start(['times' => $times])->assertStatus(422);
    expect($refused->json('reason'))->toBe('academy_full')
        ->and($refused->json('detail'))->toContain('راسلوها');
});

it('refuses a teacher who stopped taking new students, and one not cleared for a child, without saying why', function () {
    j3Consent();
    $times = j3Slots()['suggested'];
    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['accepts_new' => false]);
    expect(j3Start(['times' => $times])->assertStatus(422)->json('reason'))->toBe('teacher_unavailable');
    j3Api('GET', 'family/start/slots?'.http_build_query(['learner_id' => $this->yara, 'subject_id' => $this->quran, 'plan_id' => $this->plan->id, 'teacher_id' => $this->teacher->id]))
        ->assertStatus(422)->assertJsonPath('reason', 'teacher_unavailable');

    // A teacher paused for minors is not offered for Yara, and never named why.
    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['accepts_new' => true]);
    app(PeopleApi::class)->pauseForMinors($this->teacher->id, 'A concern is being looked into', $this->admin->id);
    $options = j3Api('GET', 'family/start/options?'.http_build_query(['learner_id' => $this->yara, 'subject_id' => $this->quran]))->assertOk()->json('data');
    expect(array_column($options['teachers'], 'id'))->not->toContain($this->teacher->id)
        ->and($options['trial']['teacher_available'])->toBeFalse();
    $refused = j3Start(['times' => $times])->assertStatus(422);
    expect($refused->json('reason'))->toBe('teacher_unavailable')
        ->and(json_encode($refused->json()))->not->toContain('concern');
});

it('needs the plan\'s number of weekly times, one a day', function () {
    j3Consent();
    $slots = j3Slots();
    $saturday = collect($slots['days'])->firstWhere('weekday', 6)['times'];
    expect(j3Start(['times' => [$slots['suggested'][0]]])->assertStatus(422)->json('reason'))->toBe('times_count')
        ->and(j3Start(['times' => [$saturday[0]['key'], $saturday[1]['key']]])->assertStatus(422)->json('reason'))->toBe('times_count')
        ->and(j3Start(['times' => [], 'teacher_id' => null])->assertStatus(422)->json('reason'))->toBe('times_needed');
});

it('refuses a second course in a subject the learner studies, is paused in, or is ending, and names the course', function () {
    j3Consent();
    $times = j3Slots()['suggested'];
    // The office enrolled the adult student of the fixture; for him, his guardian starts nothing new in Quran.
    $father = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $token = $father->createToken('phone')->plainTextToken;
    $refused = j3Api('POST', 'family/courses', ['learner_id' => $this->student->id, 'subject_id' => $this->quran, 'plan_id' => $this->plan->id,
        'price_minor' => 40000, 'currency' => 'SAR', 'teacher_id' => $this->other->id, 'times' => $times], $token)->assertStatus(409);
    expect($refused->json('reason'))->toBe('course_pending')
        ->and($refused->json('enrollment_id'))->toBe($this->booked->enrollmentId);

    app(BillingApi::class)->recordPayment($this->booked->invoice->id, 'cash', Money::of(40000, 'SAR'), null, CarbonImmutable::now(), $this->admin->id);
    expect(j3Api('POST', 'family/courses', ['learner_id' => $this->student->id, 'subject_id' => $this->quran, 'plan_id' => $this->plan->id,
        'price_minor' => 40000, 'currency' => 'SAR', 'teacher_id' => $this->other->id, 'times' => $times], $token)->assertStatus(409)->json('reason'))->toBe('has_course');

    app(PauseEnrollment::class)->handle($this->booked->enrollmentId, CarbonImmutable::now()->toDateString(), CarbonImmutable::now()->addDays(20)->toDateString(), 'Travel', $this->admin->id);
    $paused = j3Api('GET', 'family/start/options?'.http_build_query(['learner_id' => $this->student->id]), token: $token)->assertOk()->json('data.subjects');
    expect(collect($paused)->firstWhere('id', $this->quran))->toMatchArray(['can_start' => false, 'course' => ['enrollment_id' => $this->booked->enrollmentId, 'status' => 'paused']])
        ->and(collect($paused)->firstWhere('id', $this->quran)['why_not'])->toContain('موقوف');
});

it('keeps money out of a child\'s own screen, and says the office enrols when the academy turned self-serve off', function () {
    // Yara signed in herself (a child's look): 403, kindly.
    j3Consent();
    $own = User::query()->findOrFail($this->yara)->createToken('phone')->plainTextToken;
    $child = j3Api('GET', 'family/start/options', token: $own)->assertForbidden();
    expect($child->json('reason'))->toBe('family_only')->and($child->json('type'))->toBe('start-refused');
    j3Start(token: $own)->assertForbidden();

    $this->actingAs($this->admin, 'web');
    app(SaveSelfServeEnrol::class)->handle(false);
    $off = j3Api('GET', 'family/start/options')->assertOk()->json('data');
    expect($off['self_serve'])->toBeFalse()->and($off['why_off'])->toContain('راسلوها')->and($off['plans'])->toBe([]);
    $times = ['2|17:00', '6|17:00'];
    expect(j3Start(['times' => $times])->assertForbidden()->json('reason'))->toBe('self_serve_off')
        ->and(DB::table('audit_logs')->where('action', 'billing.self_serve_off')->count())->toBe(1)
        ->and(BillingSettings::current()->self_serve_saved_by)->toBe($this->admin->id);

    // The report's start says so too.
    $report = j3Api('GET', "family/trials/{$this->trial->session_id}?learner_id={$this->yara}")->assertOk()->json('data.start');
    expect($report)->toMatchArray(['available' => false, 'reason' => 'self_serve_off']);
});

it('prices in the payer\'s own region and currency', function () {
    j3Consent();
    $egypt = PriceRegion::query()->create(['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'is_default' => false, 'countries' => ['EG']]);
    $this->plan->prices()->create(['price_region_id' => $egypt->id, 'amount_minor' => 300000, 'currency' => 'EGP']);
    app(PeopleApi::class)->saveStudentDetails($this->yara, new StudentDetails(country: 'EG'));

    $plan = j3Api('GET', 'family/start/options?'.http_build_query(['learner_id' => $this->yara]))->assertOk()->json('data.plans.0');
    expect($plan['price'])->toMatchArray(['amount_minor' => 300000, 'currency' => 'EGP']);
    // The SAR price the family did not see is a changed price.
    expect(j3Start()->assertStatus(409)->json('reason'))->toBe('price_changed');
    $answer = j3Start(['price_minor' => 300000, 'currency' => 'EGP'])->assertCreated()->json('data');
    expect($answer['invoice']['currency'])->toBe('EGP');
});

it('seats the learner in an open group of the plan\'s shape, and refuses a full one', function () {
    j3Consent();
    $group = Plan::query()->create(['name_ar' => 'جماعي', 'name_en' => 'Group', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42, 'lesson_format' => 'group']);
    $group->prices()->create(['price_region_id' => $this->region->id, 'amount_minor' => 20000, 'currency' => 'SAR']);
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->other->id, 'male', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 4));
    $groupId = app(CreateGroup::class)->handle(new NewGroup(teacherId: $this->other->id, subjectId: $this->quran, sessionMinutes: 30,
        times: [['weekday' => 2, 'start' => '20:00'], ['weekday' => 6, 'start' => '20:00']], ageGroup: 'kids', capacity: 2, minToStart: 2), $this->admin->id)->id;

    $slots = j3Api('GET', 'family/start/slots?'.http_build_query(['learner_id' => $this->yara, 'subject_id' => $this->quran, 'plan_id' => $group->id]))->assertOk()->json('data');
    expect($slots['format'])->toBe('group')->and($slots['groups'][0])->toMatchArray(['id' => $groupId, 'status' => 'forming', 'min_to_start' => 2]);

    expect(j3Start(['plan_id' => $group->id, 'price_minor' => 20000, 'times' => []])->assertStatus(422)->json('reason'))->toBe('group_needed');
    $answer = j3Start(['plan_id' => $group->id, 'price_minor' => 20000, 'group_id' => $groupId, 'teacher_id' => null, 'times' => []])->assertCreated()->json('data');
    expect($answer['group'])->toMatchArray(['id' => $groupId, 'status' => 'forming', 'seat' => 1, 'capacity' => 2, 'started' => false])
        ->and($answer['payment']['text'])->toContain('يكتمل')
        ->and($answer['invoice']['total']['amount_minor'])->toBe(18000);     // the offer (10%) on the group plan too

    // Another family takes the last seat: the group starts; a third finds it full.
    $other = User::factory()->withRole(Role::Student)->create();
    app(PeopleApi::class)->saveStudentProfile($other->id, CarbonImmutable::parse('2017-03-01'), null);
    $otherMum = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->linkGuardian($otherMum->id, $other->id, askConsent: false);
    app(GrantConsent::class)->handle($other->id, $otherMum->id, 'family');
    $mumToken = $otherMum->createToken('phone')->plainTextToken;
    j3Api('POST', 'family/courses', ['learner_id' => $other->id, 'subject_id' => $this->quran, 'plan_id' => $group->id, 'price_minor' => 20000, 'currency' => 'SAR', 'group_id' => $groupId], $mumToken)
        ->assertCreated()->assertJsonPath('data.group.started', true);
    $third = User::factory()->withRole(Role::Student)->create();
    app(PeopleApi::class)->saveStudentProfile($third->id, CarbonImmutable::parse('2017-05-01'), null);
    $thirdDad = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->linkGuardian($thirdDad->id, $third->id, askConsent: false);
    app(GrantConsent::class)->handle($third->id, $thirdDad->id, 'family');
    expect(j3Api('POST', 'family/courses', ['learner_id' => $third->id, 'subject_id' => $this->quran, 'plan_id' => $group->id, 'price_minor' => 20000, 'currency' => 'SAR', 'group_id' => $groupId], $thirdDad->createToken('phone')->plainTextToken)
        ->assertStatus(422)->json('reason'))->toBe('group_full');
});

it('lets the family call off a course it started before paying, and not once money may be on its way', function () {
    j3Consent();
    $answer = j3Start()->assertCreated()->json('data');
    $id = $answer['enrollment_id'];

    // Another guardian cannot.
    $stranger = User::factory()->withRole(Role::Guardian)->create()->createToken('phone')->plainTextToken;
    j3Api('POST', "family/courses/{$id}/cancel", token: $stranger)->assertNotFound();

    // A transfer receipt waiting: not now.
    DB::table('manual_payment_proofs')->insert(['id' => (string) Str::uuid(), 'academy_id' => $this->academy->id, 'invoice_id' => $answer['invoice']['id'], 'payer_id' => $this->mother->id,
        'channel' => 'family', 'method' => 'bank_transfer', 'amount_minor' => 36000, 'currency' => 'SAR', 'paid_on' => now()->toDateString(), 'file_path' => 'x', 'file_name' => 'x.jpg',
        'mime' => 'image/jpeg', 'size' => 1, 'created_at' => now(), 'updated_at' => now()]);
    expect(j3Api('POST', "family/courses/{$id}/cancel")->assertStatus(409)->json('reason'))->toBe('cancel_proof');
    DB::table('manual_payment_proofs')->update(['status' => 'rejected', 'decision_note' => 'Not received', 'decided_at' => now()]);

    $done = j3Api('POST', "family/courses/{$id}/cancel")->assertOk()->json('data');
    expect($done['status'])->toBe('ended')
        ->and($done['lessons_cancelled'])->toBe($answer['lessons'])
        ->and(Invoice::query()->find($answer['invoice']['id'])->status)->toBe('void')
        ->and(DB::table('sessions')->where('enrollment_id', $id)->where('status', 'scheduled')->count())->toBe(0)
        ->and(CourseStart::query()->where('enrollment_id', $id)->value('cancelled_at'))->not->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'enrollment.own_start_cancelled')->count())->toBe(1);

    // The learner may start again, at the same times (freed).
    j3Start()->assertCreated();
});

it('welcomes the family once the first payment is in: the teacher, the weekly times on their clock, the first lesson and its link', function () {
    j3Consent();
    config(['platform.app.android_url' => 'https://play.example.test/app', 'platform.app.ios_url' => null]);
    $answer = j3Start()->assertCreated()->json('data');
    expect(Notification::query()->where('template_key', 'enrollment.course_started')->count())->toBe(0);

    app(BillingApi::class)->recordPayment($answer['invoice']['id'], 'cash', Money::of(36000, 'SAR'), null, CarbonImmutable::now(), $this->admin->id);

    $sent = Notification::query()->where('template_key', 'enrollment.course_started')->get();
    expect($sent->pluck('user_id')->all())->toBe([$this->mother->id])     // the guardian; never the child's own account
        ->and($sent->first()->body)->toContain('Yara')->toContain('/class/')->toContain('https://play.example.test/app')
        ->and(app(EnrollmentApi::class)->find($answer['enrollment_id'])->status->value)->toBe('active');
    // Her mother's account was made by the CRM with no way in yet: People's welcome too, once.
    expect(Notification::query()->where('user_id', $this->mother->id)->where('template_key', 'like', 'people.welcome%')->count())->toBeLessThanOrEqual(1);
});

it('joins a later subject to the learner\'s shared package: no invoice of its own, and nothing to pay now once the package is paid', function () {
    j3Consent();
    $shared = Plan::query()->create(['name_ar' => 'باقة مشتركة', 'name_en' => 'Shared', 'session_minutes' => 30, 'sessions_per_block' => 12, 'sessions_per_week' => 2,
        'validity_days' => 60, 'credit_scope' => Plan::SHARED]);
    $shared->prices()->create(['price_region_id' => $this->region->id, 'amount_minor' => 60000, 'currency' => 'SAR']);
    $arabic = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ['ar'], [$this->quran, $arabic]));

    $times = j3Slots()['suggested'];
    $first = j3Start(['plan_id' => $shared->id, 'price_minor' => 60000, 'times' => [$times[0]]])->assertCreated()->json('data');
    expect($first['joined_package'])->toBeFalse()->and($first['invoice']['total']['amount_minor'])->toBe(54000);     // the offer on the package's first invoice
    app(BillingApi::class)->recordPayment($first['invoice']['id'], 'cash', Money::of(54000, 'SAR'), null, CarbonImmutable::now(), $this->admin->id);

    $slots = j3Api('GET', 'family/start/slots?'.http_build_query(['learner_id' => $this->yara, 'subject_id' => $arabic, 'plan_id' => $shared->id, 'teacher_id' => $this->teacher->id, 'timezone' => 'Asia/Riyadh']))->assertOk()->json('data');
    $answer = j3Start(['subject_id' => $arabic, 'plan_id' => $shared->id, 'price_minor' => 60000, 'times' => [$slots['days'][0]['times'][0]['key']]])->assertCreated()->json('data');
    expect($answer['joined_package'])->toBeTrue()
        ->and($answer['invoice'])->toBeNull()
        ->and($answer['pay_url'])->toBeNull()
        ->and($answer['status'])->toBe('active')
        ->and($answer['payment']['text'])->toContain('رصيد باقتكم');
});

it('keeps the window to pay: a lesson still unpaid when it closes is cancelled as rule 16 says', function () {
    j3Consent();
    $answer = j3Start()->assertCreated()->json('data');
    CarbonImmutable::setTestNow(CarbonImmutable::parse($answer['payment']['pay_by']['utc'])->addMinute());
    app(CancelUnpaidSessions::class)->handle();

    $first = DB::table('sessions')->where('enrollment_id', $answer['enrollment_id'])->orderBy('starts_at')->first(['id', 'status']);
    expect($first->status)->toBe('cancelled')
        ->and(DB::table('session_events')->where('session_id', $first->id)->whereRaw("data->>'reason' = 'unpaid'")->exists())->toBeTrue()
        // The rest wait for payment as before.
        ->and(DB::table('sessions')->where('enrollment_id', $answer['enrollment_id'])->where('status', 'scheduled')->count())->toBe($answer['lessons'] - 1);
});

it('opens the start flow from the trial\'s report with the recommended plan and the trial\'s teacher', function () {
    $start = j3Api('GET', "family/trials/{$this->trial->session_id}?learner_id={$this->yara}")->assertOk()->json('data.start');
    expect($start)->toBe([
        'available' => true, 'why_not' => null, 'reason' => null, 'learner_id' => $this->yara, 'subject_id' => $this->quran,
        'plan_id' => $this->plan->id, 'teacher_id' => $this->teacher->id, 'enrollment_id' => null,
    ]);
    // The trial's teacher no longer takes new students: the family chooses another.
    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['accepts_new' => false]);
    expect(j3Api('GET', "family/trials/{$this->trial->session_id}?learner_id={$this->yara}")->json('data.start.teacher_id'))->toBeNull();
    // A child's own look reads no start at all.
    j3Consent();
    $own = User::query()->findOrFail($this->yara)->createToken('phone')->plainTextToken;
    expect(j3Api('GET', "family/trials/{$this->trial->session_id}", token: $own)->assertOk()->json('data.start'))->toBeNull();
});

it('sends no welcome for a course brought over from before the platform, and says when a forming group starts', function () {
    Event::dispatch(new EnrollmentActivated($this->academy->id, $this->booked->enrollmentId, 'imported'));
    expect(Notification::query()->where('template_key', 'enrollment.course_started')->count())->toBe(0);

    Event::dispatch(new EnrollmentActivated($this->academy->id, $this->booked->enrollmentId, 'paid'));
    $sent = Notification::query()->where('template_key', 'enrollment.course_started')->get();
    // The fixture's adult learner and his guardian; his weekly times on each one's own clock.
    expect($sent->pluck('user_id')->sort()->values()->all())->toEqual(collect([$this->student->id, DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id')])->sort()->values()->all())
        ->and($sent->firstWhere('user_id', $this->student->id)->body)->toContain('/class/');
});

it('welcomes a course with no lesson ahead yet without a class link, and says its time and link follow', function () {
    // Every lesson of the course is behind: nothing to link to (as a shared package or a course whose lessons are not made yet).
    $this->travelTo(CarbonImmutable::now()->addYears(2));
    Event::dispatch(new EnrollmentActivated($this->academy->id, $this->booked->enrollmentId, 'paid'));

    $sent = Notification::query()->where('template_key', 'enrollment.course_started')->get();
    expect($sent)->not->toBeEmpty();
    foreach ($sent as $message) {
        expect($message->body)->not->toContain('/class/')
            ->and(str_contains($message->body, 'سنرسل لكم موعدها ورابطها قريبًا') || str_contains($message->body, 'we will send you its time and link soon'))->toBeTrue();
    }
});

it('signs no class link for a lesson that is not named', function () {
    $links = app(NotificationLinks::class);
    expect($links->personal(['screen' => 'session'], $this->student->id))->toBeNull()
        ->and($links->personal(['screen' => 'session', 'id' => ''], $this->student->id))->toBeNull()
        ->and($links->personal(['screen' => 'session', 'id' => 'not-a-lesson'], $this->student->id))->toBeNull()
        ->and(app(AttendanceApi::class)->lesson(''))->toBeNull();
});

it('lets the demo\'s Jana\'s mother sign in and start from the report, once', function () {
    // The demo's lead, its mother's account made by the CRM with her number alone.
    DB::table('leads')->where('id', $this->trial->lead_id)->update(['contact_name' => 'أم جنى', 'learner_name' => 'جنى']);
    DB::table('users')->where('id', $this->mother->id)->update(['email' => null, 'password' => null]);
    DB::table('billing_settings')->updateOrInsert(['academy_id' => $this->academy->id], ['self_serve_enrol' => false, 'self_serve_saved_by' => null]);

    app(DemoSelfStartSeeder::class)->run();
    app(DemoSelfStartSeeder::class)->run();
    expect(DB::table('users')->where('id', $this->mother->id)->value('email'))->toBe('jana.mother@demo.test')
        ->and(BillingSettings::current()->self_serve_enrol)->toBeTrue();

    app('auth')->forgetGuards();
    $token = $this->postJson(J3_API.'auth/login', ['login' => 'jana.mother@demo.test', 'password' => config('app.demo.password'), 'device_name' => 'phone'])->assertOk()->json('token');
    $this->actAsTenant($this->academy);
    expect(j3Api('GET', "family/trials/{$this->trial->session_id}", token: $token)->assertOk()->json('data.start.available'))->toBeTrue();

    // The owner's own choice stays.
    $this->actingAs($this->admin, 'web');
    app(SaveSelfServeEnrol::class)->handle(false);
    app(DemoSelfStartSeeder::class)->run();
    expect(BillingSettings::current()->self_serve_enrol)->toBeFalse();
});

it('switches self-serve from the plans screen, audited', function () {
    $accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->actingAs($accountant);
    Filament\Facades\Filament::setCurrentPanel('academy');
    Livewire\Livewire::test(ManagePlans::class)
        ->assertSee(__('billing::plans.self_serve.action'))
        ->callAction('selfServe', data: ['on' => false])->assertHasNoActionErrors();
    expect(BillingSettings::current()->self_serve_enrol)->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'billing.self_serve_off')->value('actor_id'))->toBe($accountant->id);
});

/** A region naming these countries, with the plan priced in it. */
function j3Region(string $name, string $currency, array $countries, int $amount): PriceRegion
{
    $region = PriceRegion::query()->create(['name_ar' => $name, 'name_en' => $name, 'currency' => $currency, 'is_default' => false, 'countries' => $countries]);
    test()->plan->prices()->create(['price_region_id' => $region->id, 'amount_minor' => $amount, 'currency' => $currency]);

    return $region;
}

it('prices a family from a lead in Egypt in Egypt\'s region on the report, the start flow and the first invoice alike', function () {
    $egypt = j3Region('Egypt', 'EGP', ['EG'], 220000);
    $trial = j3Trial(['contact_name' => 'Umm Omar', 'phone_e164' => '+201001234567', 'email' => 'umm.omar@example.test', 'country' => 'EG',
        'timezone' => 'Africa/Cairo', 'learner_name' => 'Omar Adel'], $this->other->id, '2026-10-10 19:00');
    $omar = (string) $trial->lead->learner_id;
    $mother = User::query()->findOrFail($trial->lead->contact_id);
    $token = $mother->createToken('phone')->plainTextToken;
    // PrepareLearner put the lead's country on the child's details and on the mother.
    expect(app(PeopleApi::class)->studentDetails($omar)->country)->toBe('EG')
        ->and(app(PeopleApi::class)->countryOf($mother->id))->toBe('EG');

    $report = j3Api('GET', "family/trials/{$trial->session_id}?learner_id={$omar}", token: $token)->assertOk()->json('data');
    expect($report['recommendation']['price'])->toMatchArray(['amount_minor' => 220000, 'currency' => 'EGP'])
        ->and($report['recommendation']['region']['id'])->toBe($egypt->id);

    $options = j3Api('GET', 'family/start/options?'.http_build_query(['learner_id' => $omar, 'subject_id' => $this->quran]), token: $token)->assertOk()->json('data');
    expect($options['region']['currency'])->toBe('EGP')
        ->and($options['plans'][0]['price'])->toMatchArray(['amount_minor' => 220000, 'currency' => 'EGP']);

    app(GrantConsent::class)->handle($omar, $mother->id, 'family');
    $slots = j3Api('GET', 'family/start/slots?'.http_build_query(['learner_id' => $omar, 'subject_id' => $this->quran, 'plan_id' => $this->plan->id,
        'teacher_id' => $this->other->id, 'timezone' => 'Africa/Cairo']), token: $token)->assertOk()->json('data');
    $answer = j3Start(['learner_id' => $omar, 'teacher_id' => $this->other->id, 'times' => $slots['suggested'], 'price_minor' => 220000, 'currency' => 'EGP',
        'timezone' => 'Africa/Cairo'], $token)->assertCreated()->json('data');
    expect($answer['invoice']['currency'])->toBe('EGP')
        ->and($answer['offer']['state'])->toBe('applied')
        ->and($answer['invoice']['total']['amount_minor'])->toBe(198000)
        // From now on she pays in Egypt's region.
        ->and(DB::table('billing_accounts')->where('payer_id', $mother->id)->value('price_region_id'))->toBe($egypt->id);
});

it('keeps a country the family already has, and the region a payer already pays in', function () {
    $emirates = j3Region('Emirates', 'AED', ['AE'], 41000);
    j3Region('Egypt', 'EGP', ['EG'], 220000);
    $people = app(PeopleApi::class);
    // Her mother put Yara's country right: the Emirates. A country from a lead never replaces it.
    $people->correctStudentDetails($this->yara, new StudentDetails(country: 'AE'));
    expect($people->fillCountry($this->yara, 'EG'))->toBe(0)
        ->and($people->countryOf($this->yara))->toBe('AE')
        ->and($people->countryOf($this->mother->id))->toBe('SA');
    // Her mother's Saudi Arabia names no region here, so Yara's country prices them, on the report and in the start flow.
    $report = j3Api('GET', "family/trials/{$this->trial->session_id}?learner_id={$this->yara}")->assertOk()->json('data.recommendation');
    $options = j3Api('GET', 'family/start/options?'.http_build_query(['learner_id' => $this->yara]))->assertOk()->json('data');
    expect($report['region']['id'])->toBe($emirates->id)
        ->and($report['price']['currency'])->toBe('AED')
        ->and($options['region']['id'])->toBe($emirates->id)
        ->and($options['plans'][0]['price']['amount_minor'])->toBe(41000);

    // A payer who already pays in the Gulf stays there, whatever their or the learner's country says.
    $guardian = (string) DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id');
    $people->fillCountry($guardian, 'EG');
    $people->fillCountry($this->student->id, 'EG');
    expect(app(BillingApi::class)->priceRegionFor($guardian, $this->student->id)?->id)->toBe($this->region->id);
});

it('catches up the learners and parents the CRM made before, from their lead, once and only where they have none', function () {
    // As before PrepareLearner gave them the lead's country.
    DB::table('student_profiles')->where('user_id', $this->yara)->update(['country' => null]);
    DB::table('guardian_profiles')->where('user_id', $this->mother->id)->delete();
    $people = app(PeopleApi::class);
    expect([$people->countryOf($this->yara), $people->countryOf($this->mother->id)])->toBe([null, null]);

    $this->artisan('crm:learner-countries')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect([$people->countryOf($this->yara), $people->countryOf($this->mother->id)])->toBe(['SA', 'SA']);

    // Put right since, the family's own country stays when it runs again.
    $people->correctStudentDetails($this->yara, new StudentDetails(country: 'AE'));
    $this->artisan('crm:learner-countries')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect($people->countryOf($this->yara))->toBe('AE');
});

/** The days (on the Cairo teacher's clock) of a course's lessons that are still on. */
function j3LessonDays(string $enrollmentId): array
{
    return DB::table('sessions')->where('enrollment_id', $enrollmentId)->where('status', 'scheduled')->orderBy('starts_at')->pluck('starts_at')
        ->map(fn ($at) => CarbonImmutable::parse($at)->setTimezone('Africa/Cairo')->toDateString())->all();
}

/** The weekly times offered on a weekday (on the family's clock). */
function j3TimesOn(array $slots, int $weekday): array
{
    return collect($slots['days'])->firstWhere('weekday', $weekday)['times'] ?? [];
}

it('keeps a weekday with an academy holiday in its first weeks: that week is skipped, never booked, and said', function () {
    j3Consent();
    // The first day is Friday 9 October: Tuesdays 13, 20, 27 October and 3 November; the academy is closed on the 20th.
    app(DeclareHoliday::class)->handle('Mid-term break', '2026-10-20', '2026-10-20', $this->admin->id);

    $slots = j3Slots();
    $tuesday = j3TimesOn($slots, 2);
    expect(array_column($slots['days'], 'weekday'))->toEqualCanonicalizing([2, 6])
        ->and($slots['suggested'])->toHaveCount(2)
        ->and($tuesday)->not->toBeEmpty();
    foreach ($tuesday as $time) {
        expect($time['skips'])->toHaveCount(1)
            ->and($time['skips'][0])->toMatchArray(['date' => '2026-10-20', 'reason' => 'holiday'])
            ->and($time['skips'][0]['text'])->toBe('لا حصة يوم الثلاثاء 20 أكتوبر: إجازة الأكاديمية')
            // The first lesson is the first week not skipped.
            ->and(CarbonImmutable::parse($time['first']['utc'])->setTimezone('Asia/Riyadh')->toDateString())->toBe('2026-10-13');
    }
    expect(collect(j3TimesOn($slots, 6))->every(fn ($t) => $t['skips'] === []))->toBeTrue();

    $answer = j3Start(['times' => $slots['suggested']])->assertCreated()->json('data');
    expect($answer['skipped_dates'])->toBe(['2026-10-20'])
        ->and($answer['skipped'])->toHaveCount(1)
        ->and($answer['skipped'][0])->toMatchArray(['date' => '2026-10-20', 'reason' => 'holiday'])
        ->and($answer['skipped'][0]['text'])->toContain('إجازة الأكاديمية');
    $days = j3LessonDays($answer['enrollment_id']);
    expect($days)->not->toContain('2026-10-20')->toContain('2026-10-13')->toContain('2026-10-27')
        ->and(DB::table('sessions')->where('enrollment_id', $answer['enrollment_id'])->count())->toBe($answer['lessons']);
    // The validity (42 days from now) reaches the eighth lesson anyway: nothing to give back.
    expect(CourseStart::query()->where('enrollment_id', $answer['enrollment_id'])->value('extra_validity_days'))->toBe(0);
});

it('keeps a weekday with the teacher\'s leave in its first weeks, never saying why, and never books that day, now or later', function () {
    j3Consent();
    app(RequestTimeOff::class)->handle($this->teacher->id, '2026-10-24', '2026-10-24', null, null, 'personal', 'My sister\'s wedding', $this->admin->id, grant: true);

    $slots = j3Slots();
    $saturday = j3TimesOn($slots, 6);
    expect($saturday)->not->toBeEmpty();
    foreach ($saturday as $time) {
        expect($time['skips'][0])->toMatchArray(['date' => '2026-10-24', 'reason' => 'teacher_leave'])
            ->and($time['skips'][0]['text'])->toBe('لا حصة يوم السبت 24 أكتوبر: إجازة المعلّم');
    }
    expect(json_encode($slots))->not->toContain('wedding')->not->toContain('personal');

    $answer = j3Start(['times' => $slots['suggested']])->assertCreated()->json('data');
    expect($answer['skipped'][0]['reason'])->toBe('teacher_leave')
        ->and($answer['skipped_dates'])->toBe(['2026-10-24'])
        ->and(json_encode($answer))->not->toContain('wedding')
        ->and(j3LessonDays($answer['enrollment_id']))->not->toContain('2026-10-24')->toContain('2026-10-17')->toContain('2026-10-31');

    // The nightly run (and any later one) leaves the day out too, while the teacher's other lessons that day are the office's to cover.
    app(MaterializeSessions::class)->handle();
    expect(j3LessonDays($answer['enrollment_id']))->not->toContain('2026-10-24')
        ->and(DB::table('slot_skips')->where('on_date', '2026-10-24')->value('reason'))->toBe('teacher_leave');
});

it('takes a weekday away when two of its first four weeks are lost, and an existing lesson still blocks its time', function () {
    j3Consent();
    app(DeclareHoliday::class)->handle('First break', '2026-10-13', '2026-10-13', $this->admin->id);
    app(DeclareHoliday::class)->handle('Second break', '2026-10-20', '2026-10-20', $this->admin->id);

    $slots = j3Slots();
    expect(array_column($slots['days'], 'weekday'))->toBe([6])
        ->and($slots['suggested'])->toBeNull();
    // The adult student's Saturday lessons with her at 18:00 Cairo stay hers: that time is not offered, holiday or not.
    expect(collect(j3TimesOn($slots, 6))->pluck('start')->all())->not->toContain('18:00');
});

it('gives the first block the days its skipped week takes, once paid, when the plan\'s validity would end before its last lesson', function () {
    j3Consent();
    // 28 days from now end on 3 November, the eighth lesson's day; the holiday moves the eighth to Saturday 7 November.
    $this->plan->update(['validity_days' => 28]);
    app(DeclareHoliday::class)->handle('Mid-term break', '2026-10-20', '2026-10-20', $this->admin->id);

    $answer = j3Start()->assertCreated()->json('data');
    expect(CourseStart::query()->where('enrollment_id', $answer['enrollment_id'])->value('extra_validity_days'))->toBe(4);

    app(BillingApi::class)->recordPayment($answer['invoice']['id'], 'cash', Money::of(36000, 'SAR'), null, CarbonImmutable::now(), $this->admin->id);
    $funded = DB::table('sessions')->where('enrollment_id', $answer['enrollment_id'])->where('funding_status', 'funded')->orderBy('starts_at')->pluck('starts_at')
        ->map(fn ($at) => CarbonImmutable::parse($at)->setTimezone('Africa/Cairo')->toDateString())->all();
    expect($funded)->toHaveCount(8)->toContain('2026-11-07')->not->toContain('2026-10-20');
    $account = app(EnrollmentApi::class)->find($answer['enrollment_id'])->creditAccountId;
    expect(DB::table('credit_entries')->where('account_id', $account)->where('type', 'extend')->count())->toBe(1);
});
