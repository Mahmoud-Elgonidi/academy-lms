<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\MarkLeadLost;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Actions\SaveCrmSettings;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Http\LinkToken;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * One more free trial from an earlier one (docs/architecture/02, section 27,
 * J2): the second trial with another teacher from the report ("not the right
 * teacher?"), and booking again after a trial that did not take place. The
 * fixture: Monday 5 October 2026; a Cairo teacher (a woman) and a Riyadh
 * teacher (a man, 18:00–22:00 Riyadh) teach Quran on Tuesdays and Saturdays.
 * Noor's page is on, families book, and the academy gives a second trial.
 */

const J2_NEXT = 'http://noor.lms.test/api/v1/';

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->sales = User::factory()->withRole(Role::Sales)->create();
    SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::defaults()]);
    CommunicationSettings::query()->create(['whatsapp_enabled' => true, 'email_enabled' => true, 'push_enabled' => true]);
    $this->actingAs($this->admin);
    app(SaveCrmSettings::class)->handle(true, secondTrial: true);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function ntApi(string $method, string $path, array $data = [], ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();
    $request = $token === null ? test() : test()->withToken($token);
    $response = $request->withHeaders(['Idempotency-Key' => (string) Str::uuid()])->json($method, J2_NEXT.$path, $data);
    test()->actAsTenant(test()->academy);

    return $response;
}

function ntTrial(string $riyadh = '2026-10-06 19:00', ?User $teacher = null, array $lead = []): LeadTrial
{
    $lead = app(CreateLead::class)->handle($lead + [
        'contact_name' => 'Umm Yara', 'phone_e164' => '+966501234567', 'country' => 'SA', 'timezone' => 'Asia/Riyadh',
        'for_child' => true, 'learner_name' => 'Yara', 'learner_birth_year' => 2017, 'subject_id' => test()->quran, 'source' => 'website',
    ], test()->sales->id);
    $learner = app(PrepareLearner::class)->handle($lead, test()->sales->id);
    $batch = app(PlaceBookingHold::class)->handle(($teacher ?? test()->teacher)->id, $learner, [CarbonImmutable::parse($riyadh, 'Asia/Riyadh')->getTimestamp()], 30, test()->admin->id);
    $session = app(ConfirmBooking::class)->trial($batch, $learner, test()->quran, test()->admin->id);

    return LeadTrial::query()->with('lead')->where('session_id', $session->id)->sole();
}

function ntHeld(LeadTrial $trial, string $outcome = 'completed'): LeadTrial
{
    CarbonImmutable::setTestNow($trial->starts_at->addMinutes(40));
    DB::table('sessions')->where('id', $trial->session_id)->update(['status' => $outcome]);
    event(new SessionFinalized(test()->academy->id, $trial->session_id, $outcome, []));

    return $trial->refresh();
}

function ntReport(LeadTrial $trial): string
{
    app(AssessTrial::class)->handle($trial, ['level' => 'Beginner', 'notes' => 'For the academy'], $trial->teacher_id);

    return ntLink($trial, 'crm.trial_report_ready', 'report');
}

function ntLink(LeadTrial $trial, string $template, string $path): string
{
    $body = (string) Notification::query()->where('user_id', $trial->lead->contact_id)->where('template_key', $template)->latest('created_at')->value('body');
    expect(preg_match('#/trial/'.$path.'/([A-Za-z0-9_\-.]+)#', $body, $m))->toBe(1, "No {$path} link in: {$body}");

    return $m[1];
}

/** @return list<string> "Y-m-d H:i" on the Riyadh clock */
function ntTimes(TestResponse $response): array
{
    return array_map(fn (array $s) => substr(str_replace('T', ' ', $s['starts_at']['local']), 0, 16), $response->assertOk()->json('data'));
}

it('books the second trial from the report with another teacher, once, and staff see it on the lead', function () {
    $first = ntHeld(ntTrial());
    $token = ntReport($first);

    $next = ntApi('GET', "site/trials/report/{$token}")->assertOk()->json('data.next_trial');
    expect([$next['kind'], $next['available'], $next['why_not']])->toBe(['second', true, null])
        ->and($next['until']['utc'])->toBe($first->starts_at->addDays(14)->toIso8601ZuluString());

    // Saturday: the Cairo teacher (the first one) from 17:00 is left out; the Riyadh teacher from 18:00.
    $times = ntTimes(ntApi('GET', "site/trials/report/{$token}/slots?from=2026-10-10"));
    expect($times)->toContain('2026-10-10 18:00', '2026-10-10 21:30')->not->toContain('2026-10-10 17:00', '2026-10-10 17:30');

    $booked = ntApi('POST', "site/trials/report/{$token}/book", ['starts_at' => CarbonImmutable::parse('2026-10-10 19:00', 'Asia/Riyadh')->toIso8601String()])->assertCreated();
    $second = LeadTrial::query()->where('session_id', $booked->json('data.id'))->sole();
    expect([$second->kind, $second->follows_trial_id, $second->teacher_id, $second->status])->toBe(['second', $first->id, $this->other->id, 'booked'])
        ->and(Session::query()->find($second->session_id)->funding_status->value)->toBe('free')
        ->and($first->lead->refresh()->stage)->toBe(LeadStage::TrialBooked)
        ->and(LeadActivity::query()->where('lead_id', $first->lead_id)->pluck('kind')->all())->toContain('second_trial', 'trial_booked')
        ->and(Notification::query()->where('user_id', $first->lead->contact_id)->where('template_key', 'scheduling.trial_booked')->count())->toBe(2);

    // Once: with one to come, and once it took place.
    ntApi('POST', "site/trials/report/{$token}/book", ['starts_at' => CarbonImmutable::parse('2026-10-13 19:00', 'Asia/Riyadh')->toIso8601String()])
        ->assertStatus(422)->assertJsonPath('errors.starts_at.0', __('crm::report.next.why.second_used'));
    ntHeld($second);
    expect(ntApi('GET', "site/trials/report/{$token}")->json('data.next_trial.why_not'))->toBe(__('crm::report.next.why.second_used'));
});

it('offers the second trial only within 14 days, while the academy gives it and families book, and never to a learner who started', function () {
    $first = ntHeld(ntTrial());
    $token = ntReport($first);

    // The academy's staff book trials: the report says how to ask.
    SiteSettings::query()->update(['trial_booking' => 'staff']);
    expect(ntApi('GET', "site/trials/report/{$token}")->json('data.next_trial'))->toMatchArray(['available' => false, 'why_not' => __('crm::report.next.why.staff_books')]);
    ntApi('GET', "site/trials/report/{$token}/slots")->assertStatus(422);
    SiteSettings::query()->update(['trial_booking' => 'family']);

    // The academy stops giving it: the report does not show it.
    $this->actingAs($this->admin);
    app(SaveCrmSettings::class)->handle(true, secondTrial: false);
    expect(ntApi('GET', "site/trials/report/{$token}")->json('data.next_trial'))->toBeNull();
    $this->actingAs($this->admin);
    app(SaveCrmSettings::class)->handle(true, secondTrial: true);

    // Fifteen days after the first trial: over.
    $this->travelTo($first->starts_at->addDays(14)->addMinute());
    expect(ntApi('GET', "site/trials/report/{$token}")->json('data.next_trial.why_not'))->toBe(__('crm::report.next.why.second_late'));
});

it('lets a signed-in parent book it from the app too, but not a child\'s own look', function () {
    $first = ntHeld(ntTrial());
    ntReport($first);
    $parent = User::query()->find($first->lead->contact_id)->createToken('phone')->plainTextToken;
    $child = User::query()->find($first->lead->learner_id)->createToken('phone')->plainTextToken;

    expect(ntApi('GET', 'family/trials', token: $parent)->json('data.0.next'))->toBe(['kind' => 'second', 'available' => true, 'why_not' => null]);
    expect(ntTimes(ntApi('GET', "family/trials/{$first->session_id}/slots?from=2026-10-10&timezone=Asia/Riyadh", token: $parent)))->toContain('2026-10-10 18:00');
    ntApi('GET', "family/trials/{$first->session_id}/slots", token: $child)->assertForbidden();
    ntApi('POST', "family/trials/{$first->session_id}/book", ['starts_at' => CarbonImmutable::parse('2026-10-10 19:00', 'Asia/Riyadh')->toIso8601String()], $parent)
        ->assertCreated()->assertJsonPath('data.minutes', 30);
});

it('tells the family when the learner missed the trial, with its own link to book again, until a trial took place or two were missed', function () {
    $missed = ntHeld(ntTrial(), 'student_absent');
    expect($missed->outcome)->toBe('student_absent')
        ->and(Notification::query()->where('user_id', $missed->lead->contact_id)->where('template_key', 'crm.trial_missed_family')->count())->toBe(1);
    $token = ntLink($missed, 'crm.trial_missed_family', 'again');

    $info = ntApi('GET', "site/trials/rebook/{$token}")->assertOk()->json('data');
    expect([$info['learner'], $info['outcome'], $info['available'], $info['timezone']])->toBe(['Yara', 'student_absent', true, 'Asia/Riyadh']);
    expect(ntTimes(ntApi('GET', "site/trials/rebook/{$token}/slots?from=2026-10-10")))->toContain('2026-10-10 17:00', '2026-10-10 18:00');

    $again = ntApi('POST', "site/trials/rebook/{$token}/book", ['starts_at' => CarbonImmutable::parse('2026-10-10 19:00', 'Asia/Riyadh')->toIso8601String()])->assertCreated();
    $rebooked = LeadTrial::query()->with('lead')->where('session_id', $again->json('data.id'))->sole();
    expect([$rebooked->kind, $rebooked->follows_trial_id])->toBe(['rebook', $missed->id])
        ->and(LeadActivity::query()->where('lead_id', $missed->lead_id)->pluck('kind')->all())->toContain('rebooked');
    // The link has done its work.
    ntApi('GET', "site/trials/rebook/{$token}")->assertStatus(410)->assertJsonPath('detail', __('crm::report.errors.rebooked'));

    // Missed again: the family is asked to talk to the academy instead (no link).
    $twice = ntHeld($rebooked, 'student_absent');
    $body = (string) Notification::query()->where('user_id', $twice->lead->contact_id)->where('template_key', 'crm.trial_missed_family')->latest('created_at')->value('body');
    expect($body)->toContain(__('crm::notifications.next.call', [], 'ar'))->not->toContain('/trial/again/');
    $parent = User::query()->find($twice->lead->contact_id)->createToken('phone')->plainTextToken;
    expect(ntApi('GET', 'family/trials', token: $parent)->json('data.0.next.why_not'))->toBe(__('crm::report.next.why.missed_twice'));
});

it('apologises when the teacher missed it or the academy cancelled it, and that never counts as the family\'s free trial', function () {
    $teacherAway = ntHeld(ntTrial(), 'teacher_absent');
    expect($teacherAway->outcome)->toBe('teacher_absent')
        ->and(Notification::query()->where('user_id', $teacherAway->lead->contact_id)->where('template_key', 'crm.trial_sorry_family')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $teacherAway->lead->contact_id)->where('template_key', 'crm.trial_missed_family')->count())->toBe(0);
    $token = ntLink($teacherAway, 'crm.trial_sorry_family', 'again');
    expect(ntApi('GET', "site/trials/rebook/{$token}")->json('data'))->toMatchArray(['outcome' => 'teacher_absent', 'available' => true]);

    // Booked again and cancelled by the academy (the teacher's leave): another apology, another way to book.
    $again = LeadTrial::query()->with('lead')->where('session_id', ntApi('POST', "site/trials/rebook/{$token}/book", ['starts_at' => CarbonImmutable::parse('2026-10-10 19:00', 'Asia/Riyadh')->toIso8601String()])->assertCreated()->json('data.id'))->sole();
    app(CancelSession::class)->handle(Session::query()->findOrFail($again->session_id), 'academy', 'teacher_leave', null, $this->admin->id);
    expect([$again->refresh()->status, $again->outcome])->toBe(['cancelled', 'academy'])
        ->and(Notification::query()->where('user_id', $again->lead->contact_id)->where('template_key', 'crm.trial_sorry_family')->count())->toBe(2);
    expect(ntApi('GET', 'site/trials/rebook/'.ntLink($again, 'crm.trial_sorry_family', 'again'))->json('data.available'))->toBeTrue();
});

it('tells sales but not the family when the family cancelled, and nothing about a missed trial once the learner studies the subject', function () {
    $trial = ntTrial();
    app(CancelSession::class)->handle(Session::query()->findOrFail($trial->session_id), 'student', 'travel', null, $trial->lead->contact_id);
    expect($trial->refresh()->outcome)->toBe('family')
        ->and(Notification::query()->where('user_id', $trial->lead->contact_id)->whereIn('template_key', ['crm.trial_sorry_family', 'crm.trial_missed_family'])->count())->toBe(0);
    $parent = User::query()->find($trial->lead->contact_id)->createToken('phone')->plainTextToken;
    expect(ntApi('GET', 'family/trials', token: $parent)->json('data.0'))->toMatchArray(['status' => 'cancelled', 'outcome' => 'family', 'next' => null]);
});

it('brings a lost lead back when the family books again by itself', function () {
    $missed = ntHeld(ntTrial(), 'student_absent');
    $token = ntLink($missed, 'crm.trial_missed_family', 'again');
    app(MarkLeadLost::class)->handle($missed->lead->refresh(), 'no_answer', null, $this->sales->id);

    ntApi('POST', "site/trials/rebook/{$token}/book", ['starts_at' => CarbonImmutable::parse('2026-10-10 19:00', 'Asia/Riyadh')->toIso8601String()])->assertCreated();
    expect($missed->lead->refresh()->stage)->toBe(LeadStage::TrialBooked)
        ->and($missed->lead->lost_reason)->toBeNull()
        ->and(LeadActivity::query()->where('lead_id', $missed->lead_id)->pluck('kind')->all())->toContain('reopened');
});

it('refuses another academy\'s, a forged or another purpose\'s "book again" link as not found', function () {
    $missed = ntHeld(ntTrial(), 'student_absent');
    $token = ntLink($missed, 'crm.trial_missed_family', 'again');
    ntApi('GET', 'site/trials/rebook/'.substr($token, 0, -2).'zz')->assertNotFound();
    ntApi('POST', 'site/trials/rebook/'.LinkToken::issue('trial-report', $missed->id, CarbonImmutable::now()->addDay()).'/book', ['starts_at' => '2026-10-10T16:00:00Z'])->assertNotFound();
    // A report link for a trial that did not take place names no report.
    ntApi('GET', 'site/trials/report/'.LinkToken::issue('trial-report', $missed->id, CarbonImmutable::now()->addDay()))->assertNotFound();
});
