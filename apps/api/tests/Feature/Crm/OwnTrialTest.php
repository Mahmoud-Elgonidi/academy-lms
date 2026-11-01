<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Jobs\SendContactDelivery;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Crm\Actions\MarkLeadWon;
use Modules\Crm\Actions\SaveCrmSettings;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Consent\ConsentSignInGate;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Scheduling\Actions\SendLessonReminders;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Http\FormToken;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Filament\Pages\SiteRequests;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The family's journey, asking and booking the trial (docs/architecture/02,
 * section 27). The fixture: Monday 5 October 2026, 10:00 in Riyadh; Noor's
 * page is on and families book their own trial. A Cairo teacher (a woman)
 * teaches Quran on Tuesdays and Saturdays 17:00–21:00 and has a lesson at
 * 18:00; a Riyadh teacher (a man) 18:00–22:00. Both clocks are three hours
 * ahead of UTC. Two people work leads: the admin and Salma from sales.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->sales = User::factory()->withRole(Role::Sales)->create(['name' => 'Salma Sales']);
    SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::defaults()]);
    CommunicationSettings::query()->create(['whatsapp_enabled' => true, 'email_enabled' => true, 'push_enabled' => true]);
    $this->codes = new class implements OtpSender
    {
        /** @var list<OtpMessage> */
        public array $sent = [];

        public function supports(string $channel): bool
        {
            return true;
        }

        public function send(OtpMessage $message): void
        {
            $this->sent[] = $message;
        }
    };
    app()->instance(OtpSender::class, $this->codes);
});

afterEach(fn () => CarbonImmutable::setTestNow());

const NOOR = 'http://noor.lms.test/api/v1/';

/** A request to Noor's API, then back in Noor for the reads that follow (the request's end leaves its academy). */
function noor(string $method, string $path, array $data = []): TestResponse
{
    $response = test()->json($method, str_starts_with($path, 'http') ? $path : NOOR.$path, $data);
    test()->actAsTenant(test()->academy);

    return $response;
}

/** The free-trial request a mother sends for her nine-year-old. */
/** A teacher's first name as families read it: with a title before it ("Dr. Emiliano", "أ. منى"). */
function teacherFirstName(string $name): string
{
    return preg_match('/^(?:\S+\.\s+)?\S+/u', trim($name), $first) === 1 ? $first[0] : '';
}

function ownTrialRequest(array $change = []): array
{
    return array_merge([
        'name' => 'Marwa Salem', 'phone' => '+966501234567', 'email' => 'marwa@example.test', 'country' => 'SA', 'timezone' => 'Asia/Riyadh',
        'subject_id' => test()->quran, 'for_child' => true, 'child_name' => 'Yara', 'child_birth_year' => 2017,
        'preferred_days' => [2, 6], 'preferred_from' => 17, 'preferred_to' => 21, 'teacher_gender' => null,
    ], $change);
}

/** @return list<string> the times offered, "Y-m-d H:i" on the Riyadh clock */
function trialSlots(array $query = []): array
{
    $answer = noor('GET', 'site/trial-slots?'.http_build_query($query + ['subject_id' => test()->quran, 'timezone' => 'Asia/Riyadh', 'birth_year' => 2017]))->assertOk();

    return array_map(fn (array $s) => substr(str_replace('T', ' ', $s['starts_at']['local']), 0, 16), $answer->json('data'));
}

/** A code to the number, as the family types it back. */
function trialCode(string $phone = '+966501234567'): array
{
    test()->travel(61)->seconds();     // one code a minute to a number
    $sent = noor('POST', 'site/trials/code', ['phone' => $phone])->assertStatus(202);

    return [$sent->json('data.challenge_id'), end(test()->codes->sent)->code];
}

function bookOwnTrial(string $riyadh = '2026-10-06 19:00', array $change = []): TestResponse
{
    [$challenge, $code] = trialCode($change['phone'] ?? '+966501234567');

    return noor('POST', 'site/trials', ownTrialRequest($change) + [
        'challenge_id' => $challenge, 'code' => $code, 'starts_at' => CarbonImmutable::parse($riyadh, 'Asia/Riyadh')->toIso8601String(),
    ]);
}

function trialForm2(array $change = []): array
{
    $token = FormToken::issue(SubmitJoinRequest::FORM);
    test()->travel(5)->seconds();

    return ['kind' => 'trial', 'form_token' => $token] + ownTrialRequest($change);
}

it('offers the times the teachers are free across the subject\'s teachers, without their lessons, on the family\'s clock', function () {
    $all = trialSlots();
    $women = trialSlots(['teacher_gender' => 'female']);

    expect($all)->toContain('2026-10-06 17:00', '2026-10-06 18:00', '2026-10-06 21:30', '2026-10-10 19:00', '2026-10-17 20:00')
        ->not->toContain('2026-10-05 17:00', '2026-10-07 18:00', '2026-10-06 22:00')
        // Only the Cairo teacher is a woman: her 18:00 lesson is taken, her shift ends at 21:00.
        ->and($women)->toContain('2026-10-06 17:00', '2026-10-06 17:30', '2026-10-06 18:30', '2026-10-06 20:30')
        ->not->toContain('2026-10-06 18:00', '2026-10-06 21:00')
        // Never sooner than three hours from now.
        ->and(trialSlots(['from' => '2026-10-05']))->each->toBeGreaterThan('2026-10-05 13:00');
});

it('leaves out a teacher\'s full days and, for a child, a teacher not cleared for minors', function () {
    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['max_daily_lessons' => 1]);
    expect(trialSlots(['teacher_gender' => 'female']))->not->toContain('2026-10-06 17:00', '2026-10-10 17:00');

    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['max_daily_lessons' => null]);
    app(PeopleApi::class)->pauseForMinors($this->teacher->id, 'Concern #7 is being looked into', $this->admin->id);
    expect(trialSlots(['teacher_gender' => 'female']))->toBe([])
        // An adult may still take her lessons.
        ->and(trialSlots(['teacher_gender' => 'female', 'birth_year' => 1990]))->toContain('2026-10-06 17:00');
});

it('books only with the right code to the number, and the family gets the time with its own links', function () {
    [$challenge] = trialCode();
    noor('POST', 'site/trials', ownTrialRequest() + ['challenge_id' => $challenge, 'code' => '000000', 'starts_at' => '2026-10-06T16:00:00Z'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
    noor('POST', 'site/trials', ownTrialRequest() + ['starts_at' => '2026-10-06T16:00:00Z'])->assertStatus(422)->assertJsonValidationErrors(['challenge_id', 'code']);
    expect(Session::query()->where('type', 'trial')->count())->toBe(0)
        ->and(JoinRequest::query()->count())->toBe(0);

    Queue::fake([SendContactDelivery::class]);
    $booked = bookOwnTrial('2026-10-06 19:00', ['teacher_gender' => 'female'])->assertCreated();

    $session = Session::query()->where('type', 'trial')->sole();
    $lead = Lead::query()->sole();
    $request = JoinRequest::query()->sole();
    $body = Notification::query()->where('user_id', $lead->contact_id)->where('template_key', 'scheduling.trial_booked')->value('body');
    expect($booked->json('data.starts_at.local'))->toStartWith('2026-10-06T19:00')
        ->and($booked->json('data.teacher'))->toBe(teacherFirstName($this->teacher->name))
        ->and($booked->json('data.minutes'))->toBe(30)
        ->and([$session->teacher_id, $session->funding_status->value])->toBe([$this->teacher->id, 'free'])
        ->and($lead->stage)->toBe(LeadStage::TrialBooked)
        ->and($lead->teacher_gender)->toBe('female')
        ->and([$lead->preferred_days, $lead->preferred_from, $lead->preferred_to])->toBe([[2, 6], 17, 21])
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->pluck('kind')->all())->toContain('booked_online', 'trial_booked')
        ->and([$request->lead_id, $request->session_id, $request->status])->toBe([$lead->id, $session->id, 'contacted'])
        ->and($body)->toContain('/class/'.$session->id)->toContain('/trial/lesson/')->toContain('/change')->toContain('/cancel')
        // The booking says it all: no "we will answer you" on top of it.
        ->and(Queue::pushed(SendContactDelivery::class))->toHaveCount(0);
});

it('takes a code once, for the number it went to only', function () {
    $this->travel(2)->minutes();
    [$challenge, $code] = trialCode('+966501234567');
    expect(fn () => app(IdentityApi::class)->verifyPhoneCode($challenge, $code, '+966509999999'))->toThrow(ValidationException::class);

    $this->travel(2)->minutes();
    [$challenge, $code] = trialCode('+966501234567');
    app(IdentityApi::class)->verifyPhoneCode($challenge, $code, '+966501234567');
    expect(fn () => app(IdentityApi::class)->verifyPhoneCode($challenge, $code, '+966501234567'))->toThrow(ValidationException::class);
});

it('keeps one open trial per number and subject', function () {
    bookOwnTrial()->assertCreated();
    $this->travel(2)->minutes();

    bookOwnTrial('2026-10-10 19:00')->assertStatus(422)->assertJsonValidationErrors('starts_at');
    expect(Session::query()->where('type', 'trial')->count())->toBe(1);
});

it('lets no family book while the academy books its trials itself, and says so', function () {
    SiteSettings::query()->update(['trial_booking' => 'staff']);

    noor('GET', 'site/trial-slots?subject_id='.$this->quran)->assertNotFound();
    noor('POST', 'site/trials/code', ['phone' => '+966501234567'])->assertNotFound();
    noor('POST', 'site/trials', ownTrialRequest() + ['challenge_id' => (string) Str::uuid(), 'code' => '123456', 'starts_at' => '2026-10-06T16:00:00Z'])
        ->assertStatus(422)->assertJsonValidationErrors('starts_at');
    expect(Session::query()->where('type', 'trial')->count())->toBe(0);
});

it('answers a request at once: the hours, and the link to pick a time while families book their own', function () {
    Queue::fake([SendContactDelivery::class]);
    noor('POST', 'site/requests', trialForm2())->assertCreated();

    $sent = Queue::pushed(SendContactDelivery::class);
    $whatsapp = $sent->first(fn (SendContactDelivery $j) => $j->channel === 'whatsapp');
    expect($sent->pluck('channel')->sort()->values()->all())->toBe(['email', 'whatsapp'])
        ->and($whatsapp->address)->toBe('+966501234567')
        ->and($whatsapp->body)->toContain('4')->toContain('noor.lms.test')->toContain('/en/trial/');

    // The link proves the number: the request's details, then a time with no code.
    preg_match('#/trial/([A-Za-z0-9_\-.]+)#', $whatsapp->body, $m);
    noor('GET', 'site/trials/pick/'.$m[1])->assertOk()->assertJsonPath('data.child_name', 'Yara')->assertJsonPath('data.subject.id', $this->quran);
    noor('POST', 'site/trials/pick/'.$m[1], ['starts_at' => '2026-10-06T16:00:00Z'])->assertCreated();
    expect(JoinRequest::query()->sole()->session_id)->not->toBeNull()
        ->and(Lead::query()->sole()->stage)->toBe(LeadStage::TrialBooked);
    noor('GET', 'site/trials/pick/'.$m[1])->assertNotFound();
    noor('GET', 'site/trials/pick/'.$m[1].'x')->assertNotFound();

    // While the academy books trials itself: the hours only.
    SiteSettings::query()->update(['trial_booking' => 'staff']);
    noor('POST', 'site/requests', trialForm2(['phone' => '+966509999999', 'name' => 'Huda']))->assertCreated();
    $staff = Queue::pushed(SendContactDelivery::class)->last(fn (SendContactDelivery $j) => $j->channel === 'whatsapp');
    expect($staff->address)->toBe('+966509999999')
        ->and($staff->body)->toContain(__('site::notifications.request_received.wait', [], 'en'))->not->toContain('/trial/');
});

it('hands leads from the page out in turn to whoever works leads, unless the academy does it itself', function () {
    $staff = collect([$this->admin->id, $this->sales->id])->sort()->values()->all();
    foreach (['+966500000001', '+966500000002', '+966500000003'] as $phone) {
        noor('POST', 'site/requests', trialForm2(['phone' => $phone]))->assertCreated();
    }
    $owners = Lead::query()->orderBy('created_at')->orderBy('id')->pluck('owner_id')->all();
    expect($owners)->toBe([$staff[0], $staff[1], $staff[0]])
        ->and(Notification::query()->where('template_key', 'crm.lead_assigned')->count())->toBe(3);

    $this->actingAs($this->admin);
    app(SaveCrmSettings::class)->handle(false);
    noor('POST', 'site/requests', trialForm2(['phone' => '+966500000004']))->assertCreated();
    expect(Lead::query()->where('phone_e164', '+966500000004')->value('owner_id'))->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'crm.settings_saved')->value('data'))->toContain('off');
});

it('reminds whoever follows a lead once when nobody answered in time, and once about a trial with no assessment', function () {
    noor('POST', 'site/requests', trialForm2())->assertCreated();
    $lead = Lead::query()->sole();

    expect(Artisan::call('crm:follow-up'))->toBe(0);
    expect(Notification::query()->where('template_key', 'crm.lead_waiting')->count())->toBe(0);
    $this->travel(5)->hours();
    Artisan::call('crm:follow-up');
    Artisan::call('crm:follow-up');
    expect(Notification::query()->where('template_key', 'crm.lead_waiting')->pluck('user_id')->all())->toBe([$lead->owner_id]);

    // A trial booked for another family took place, and a day later nobody has assessed it.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    bookOwnTrial(change: ['phone' => '+966501234560'])->assertCreated();
    $session = Session::query()->where('type', 'trial')->sole();
    event(new SessionFinalized($this->academy->id, $session->id, 'completed', []));
    $this->travelTo($session->starts_at->addHours(25));
    Artisan::call('crm:follow-up');
    Artisan::call('crm:follow-up');
    $trialLead = Lead::query()->where('phone_e164', '+966501234560')->sole();
    expect(Notification::query()->where('template_key', 'crm.trial_unassessed')->pluck('user_id')->all())->toBe([$trialLead->owner_id]);
});

it('asks for the child\'s consent only when it is first needed, not for the trial', function () {
    bookOwnTrial()->assertCreated();
    $lead = Lead::query()->sole();

    expect(Notification::query()->where('template_key', 'people.consent_request')->count())->toBe(0)
        ->and(app(PeopleApi::class)->consentState($lead->learner_id)->waiting())->toBeTrue();

    // The child tries a way in of their own: the guardian is asked now.
    expect(app(ConsentSignInGate::class)->refusal($lead->learner_id)?->type)->toBe('guardian-consent-required')
        ->and(Notification::query()->where('template_key', 'people.consent_request')->pluck('user_id')->all())->toBe([$lead->contact_id]);
});

it('lets the family move its trial from its own link until six hours before, and cancel it, which tells sales', function () {
    bookOwnTrial('2026-10-06 19:00', ['teacher_gender' => 'female'])->assertCreated();
    $lead = Lead::query()->sole();
    $body = Notification::query()->where('user_id', $lead->contact_id)->where('template_key', 'scheduling.trial_booked')->value('body');
    preg_match('#/trial/lesson/([A-Za-z0-9_\-.]+)/change#', $body, $m);
    $lesson = NOOR.'site/trials/lesson/'.$m[1];

    noor('GET', $lesson.'?timezone=Asia/Riyadh')->assertOk()->assertJsonPath('data.can_move', true)->assertJsonPath('data.moves_left', 2)->assertJsonPath('data.teacher', teacherFirstName($this->teacher->name));
    $times = array_map(fn (array $s) => $s['starts_at']['utc'], noor('GET', $lesson.'/slots?timezone=Asia/Riyadh')->assertOk()->json('data'));
    expect($times)->toContain('2026-10-06T17:00:00Z')->not->toContain('2026-10-06T15:00:00Z');

    noor('POST', $lesson.'/move', ['starts_at' => '2026-10-06T17:00:00Z'])->assertOk()->assertJsonPath('data.starts_at.utc', '2026-10-06T17:00:00Z');
    expect(LeadTrial::query()->sole()->starts_at->toIso8601ZuluString())->toBe('2026-10-06T17:00:00Z');

    // Less than six hours before: not moved, but "we cannot come" still works, and whoever follows the lead hears of it.
    $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));
    noor('POST', $lesson.'/move', ['starts_at' => '2026-10-10T16:00:00Z'])->assertStatus(422)->assertJsonValidationErrors('starts_at');
    noor('POST', $lesson.'/cancel', ['note' => str_repeat('x', 501)])->assertStatus(422)->assertJsonValidationErrors('note');
    noor('POST', $lesson.'/cancel', ['note' => '  Travelling that day  '])->assertOk();

    expect(Session::query()->where('type', 'trial')->sole()->status->value)->toBe('cancelled')
        ->and(Session::query()->where('type', 'trial')->sole()->decision['note'] ?? null)->toBe('Travelling that day')
        ->and($lead->refresh()->stage)->toBe(LeadStage::Contacted)
        ->and(Notification::query()->where('template_key', 'crm.trial_cancelled')->pluck('user_id')->all())->toBe([$lead->owner_id])
        ->and(JoinRequest::query()->sole()->status)->toBe('contacted');
    noor('GET', $lesson)->assertNotFound();
});

it('reminds the family of its trial the day before by WhatsApp too, and other lessons by push and email', function () {
    bookOwnTrial()->assertCreated();
    $trialId = Session::query()->where('type', 'trial')->value('id');
    // The adult student's Tuesday lesson (15:00 UTC, paid for here) is a day away first, then the trial (16:00 UTC).
    DB::table('sessions')->where('type', 'regular')->update(['funding_status' => 'funded']);
    $this->travelTo(CarbonImmutable::parse('2026-10-05 15:30', 'UTC'));
    app(SendLessonReminders::class)->handle();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 16:30', 'UTC'));
    app(SendLessonReminders::class)->handle();

    $contact = Lead::query()->sole()->contact_id;
    $trial = Notification::query()->where('user_id', $contact)->where('template_key', 'scheduling.reminder_day')->sole();
    $lesson = Notification::query()->where('template_key', 'scheduling.reminder_day')->where('user_id', $this->student->id)->sole();
    expect($trial->action['id'])->toBe($trialId)->and($lesson->action['id'])->not->toBe($trialId);
    expect($trial->deliveries()->pluck('channel')->all())->toContain('whatsapp')
        ->and($lesson->deliveries()->pluck('channel')->all())->not->toContain('whatsapp');
});

it('follows the lead with the request\'s status, and flags a request the CRM could not make a lead of', function () {
    bookOwnTrial()->assertCreated();
    $lead = Lead::query()->sole();
    expect(JoinRequest::query()->sole()->status)->toBe('contacted');
    app(MarkLeadWon::class)->handle($lead->learner_id, $this->booked->enrollmentId, $this->teacher->id);
    expect(JoinRequest::query()->sole()->status)->toBe('accepted');

    // Details the CRM refuses (a birth year out of its range): flagged, never told to the visitor.
    $id = (string) Str::uuid();
    DB::table('site_requests')->insert(['id' => $id, 'academy_id' => $this->academy->id, 'kind' => 'trial', 'name' => 'Old', 'phone' => '+966500000077',
        'for_child' => true, 'child_name' => 'X', 'child_birth_year' => 1900, 'created_at' => now(), 'updated_at' => now()]);
    event(new JoinRequested($this->academy->id, $id, 'trial'));
    expect(JoinRequest::query()->find($id)->only(['lead_id', 'lead_failure']))->toBe(['lead_id' => null, 'lead_failure' => 'invalid']);

    // The requests screen says so, for someone to add it by hand.
    $this->actingAs($this->sales);
    Filament\Facades\Filament::setCurrentPanel('academy');
    Livewire\Livewire::test(SiteRequests::class)
        ->assertSee(__('site::site.requests.lead_failed', ['why' => __('site::site.requests.lead_failures.invalid')]));
});

it('needs a subject unless the family asks for help choosing, which starts with the academy\'s own trial subject', function () {
    noor('POST', 'site/requests', trialForm2(['subject_id' => null]))->assertStatus(422)->assertJsonValidationErrors('subject_id');

    noor('POST', 'site/requests', trialForm2(['subject_id' => null, 'help_choose' => true]))->assertCreated();
    expect(JoinRequest::query()->sole()->only(['subject_id', 'help_choose']))->toBe(['subject_id' => null, 'help_choose' => true]);

    SiteSettings::query()->update(['default_trial_subject_id' => $this->quran]);
    noor('POST', 'site/requests', trialForm2(['subject_id' => null, 'help_choose' => true, 'phone' => '+966500000008']))->assertCreated();
    expect(JoinRequest::query()->where('phone', '+966500000008')->value('subject_id'))->toBe($this->quran)
        ->and(Lead::query()->where('phone_e164', '+966500000008')->value('subject_id'))->toBe($this->quran);
    noor('GET', 'site')->assertOk()->assertJsonPath('data.trials', ['booking' => 'family', 'response_hours' => 4, 'notice_hours' => 3, 'help_choose_subject' => true, 'help_choose_subject_id' => $this->quran]);
});

it('refuses a time someone took between seeing it and confirming it, kindly, and stops offering it', function () {
    // Only the Riyadh teacher teaches at 21:30; a family takes it first.
    bookOwnTrial('2026-10-06 21:30', ['phone' => '+966500000101', 'child_name' => 'Omar'])->assertCreated();

    $late = bookOwnTrial('2026-10-06 21:30')->assertStatus(422);
    expect($late->json('errors.starts_at.0'))->toBe(__('scheduling::trials.errors.taken'))
        ->and(trialSlots())->not->toContain('2026-10-06 21:30')
        // Nothing of the refused booking is kept: the family picks another time with a new code.
        ->and(JoinRequest::query()->where('phone', '+966501234567')->exists())->toBeFalse();
});

it('offers and books nothing sooner than the academy\'s notice, nor in the past', function () {
    bookOwnTrial('2026-10-05 08:00')->assertStatus(422)->assertJsonPath('errors.starts_at.0', __('scheduling::trials.errors.too_soon', ['hours' => 3]));

    SiteSettings::query()->update(['trial_notice_hours' => 48]);
    expect(trialSlots())->not->toContain('2026-10-06 17:00', '2026-10-06 21:30')->toContain('2026-10-10 17:00');
    bookOwnTrial('2026-10-06 19:00')->assertStatus(422)->assertJsonPath('errors.starts_at.0', __('scheduling::trials.errors.too_soon', ['hours' => 48]));
    noor('GET', 'site')->assertJsonPath('data.trials.notice_hours', 48);
});

it('lets a parent book for two children, never the same child twice in a subject, and at most three open trials a number', function () {
    bookOwnTrial('2026-10-06 19:00')->assertCreated();
    bookOwnTrial('2026-10-06 19:30', ['child_name' => ' yara '])->assertStatus(422)->assertJsonPath('errors.starts_at.0', __('crm::leads.errors.trial_open'));
    bookOwnTrial('2026-10-06 19:30', ['child_name' => 'Omar', 'child_birth_year' => 2015])->assertCreated();
    bookOwnTrial('2026-10-06 20:00', ['child_name' => 'Lina'])->assertCreated();
    bookOwnTrial('2026-10-06 20:30', ['child_name' => 'Sara'])->assertStatus(422)
        ->assertJsonPath('errors.starts_at.0', __('crm::leads.errors.too_many_open', ['count' => 3]));

    $leads = Lead::query()->orderBy('created_at')->get();
    $guardian = User::query()->where('phone_e164', '+966501234567')->sole();
    expect($leads->pluck('learner_name')->all())->toBe(['Yara', 'Omar', 'Lina'])
        ->and($leads->pluck('contact_id')->unique()->all())->toBe([$guardian->id])
        ->and(DB::table('guardian_student')->where('guardian_id', $guardian->id)->count())->toBe(3);
});

it('sends a learner who already studies the subject, or had the free trial in it, to the academy instead', function () {
    $this->student->forceFill(['phone_e164' => '+447700900555'])->save();
    bookOwnTrial('2026-10-06 19:00', ['phone' => '+447700900555', 'name' => $this->student->name, 'for_child' => false, 'child_name' => null, 'child_birth_year' => null])
        ->assertStatus(422)->assertJsonPath('errors.starts_at.0', __('crm::leads.errors.already_student'));

    bookOwnTrial('2026-10-06 19:00')->assertCreated();
    $first = Session::query()->where('type', 'trial')->sole();
    DB::table('sessions')->where('id', $first->id)->update(['status' => 'completed']);
    event(new SessionFinalized($this->academy->id, $first->id, 'completed', []));
    bookOwnTrial('2026-10-10 19:00')->assertStatus(422)->assertJsonPath('errors.starts_at.0', __('crm::leads.errors.had_trial'));
    // Another subject is another free trial.
    expect(Session::query()->where('type', 'trial')->count())->toBe(1);
});

it('refuses a number that is someone else\'s way in here without saying whose', function () {
    $this->teacher->forceFill(['phone_e164' => '+966500000222'])->save();
    $answer = bookOwnTrial('2026-10-06 19:00', ['phone' => '+966500000222'])->assertStatus(422);

    expect($answer->json('errors.starts_at.0'))->toBe(__('crm::leads.errors.number_in_use'))
        ->and((string) $answer->getContent())->not->toContain($this->teacher->name);
});

it('keeps the grid on the family\'s own clock through a clock change, and leaves out holidays and leave', function () {
    $london = array_map(fn (array $s) => substr($s['starts_at']['local'], 0, 16),
        noor('GET', 'site/trial-slots?'.http_build_query(['subject_id' => $this->quran, 'timezone' => 'Europe/London', 'from' => '2026-10-20', 'birth_year' => 2017]))->json('data'));
    expect($london)->each(fn ($t) => $t->toMatch('/:(00|30)$/'))
        // Summer time: Cairo 17:00 is London 15:00; after London's clocks went back (25 October), 14:00.
        ->and($london)->toContain('2026-10-20T15:00', '2026-10-27T14:00');

    AcademyHolidayFor('2026-10-06');
    expect(trialSlots())->not->toContain('2026-10-06 17:00', '2026-10-06 21:30')->toContain('2026-10-10 17:00');
});

function AcademyHolidayFor(string $day): void
{
    AcademyHoliday::query()->create([
        'name' => 'Holiday', 'timezone' => 'Asia/Riyadh', 'first_day' => $day, 'last_day' => $day,
        'starts_at' => CarbonImmutable::parse($day, 'Asia/Riyadh'), 'ends_at' => CarbonImmutable::parse($day, 'Asia/Riyadh')->addDay(), 'created_by' => test()->admin->id,
    ]);
}

it('counts wrong codes, closes the code after five, and lets a code run out', function () {
    $this->travel(2)->minutes();
    [$challenge] = trialCode();
    $try = fn () => noor('POST', 'site/trials', ownTrialRequest() + ['challenge_id' => $challenge, 'code' => '000000', 'starts_at' => '2026-10-06T16:00:00Z']);
    foreach (range(1, 4) as $_) {
        $try()->assertStatus(422);
    }
    $try()->assertJsonPath('errors.code.0', __('identity::otp.locked'));

    [$challenge, $code] = trialCode();
    $this->travel(11)->minutes();
    noor('POST', 'site/trials', ownTrialRequest() + ['challenge_id' => $challenge, 'code' => $code, 'starts_at' => '2026-10-06T16:00:00Z'])
        ->assertJsonPath('errors.code.0', __('identity::otp.expired'));
    expect(Session::query()->where('type', 'trial')->count())->toBe(0);
});

it('moves a trial twice at most, never once its teacher stopped taking trials, and cancels it until it starts', function () {
    bookOwnTrial('2026-10-06 19:00', ['teacher_gender' => 'female'])->assertCreated();
    $body = Notification::query()->where('template_key', 'scheduling.trial_booked')->where('user_id', Lead::query()->sole()->contact_id)->value('body');
    preg_match('#/trial/lesson/([A-Za-z0-9_\-.]+)/change#', $body, $m);
    $lesson = NOOR.'site/trials/lesson/'.$m[1];

    noor('POST', $lesson.'/move', ['starts_at' => '2026-10-06T17:00:00Z'])->assertOk();
    noor('POST', $lesson.'/move', ['starts_at' => '2026-10-06T17:30:00Z'])->assertOk();
    noor('POST', $lesson.'/move', ['starts_at' => '2026-10-06T14:00:00Z'])->assertStatus(422)
        ->assertJsonPath('errors.starts_at.0', __('scheduling::trials.errors.moved_enough', ['times' => 2]));
    noor('GET', $lesson)->assertJsonPath('data.can_move', false)->assertJsonPath('data.moves', 2);

    DB::table('sessions')->where('type', 'trial')->update(['reschedule_count' => 0]);
    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['accepts_trials' => false]);
    noor('GET', $lesson)->assertJsonPath('data.cannot_move', __('scheduling::trials.errors.teacher_not_taking'));
    noor('GET', $lesson.'/slots')->assertOk()->assertJsonPath('data', []);
    expect(Session::query()->where('type', 'trial')->sole()->status->value)->toBe('scheduled');

    // An hour before it starts: still "we cannot come"; once it started, the link is over.
    $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30', 'UTC'));
    noor('POST', $lesson.'/cancel')->assertOk();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 18:00', 'UTC'));
    noor('GET', $lesson)->assertNotFound();
});

it('takes no trial while the page is off, and books one while the academy\'s plan leaves the CRM\'s screens out', function () {
    SiteSettings::query()->update(['enabled' => false]);
    noor('GET', 'site/trial-slots?subject_id='.$this->quran)->assertNotFound();
    noor('POST', 'site/trials/code', ['phone' => '+966501234567'])->assertNotFound();
    noor('POST', 'site/requests', trialForm2())->assertNotFound();

    SiteSettings::query()->update(['enabled' => true]);
    $this->clearTenant();
    $plan = SaasPlan::query()->create(['code' => 'basic', 'name_ar' => 'أساسية', 'name_en' => 'Basic', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    $this->actAsTenant($this->academy);
    bookOwnTrial()->assertCreated();
    expect(Lead::query()->sole()->stage)->toBe(LeadStage::TrialBooked);
});
