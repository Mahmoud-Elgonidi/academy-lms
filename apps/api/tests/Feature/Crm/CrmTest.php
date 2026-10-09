<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Communication\Models\Notification;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\LogLeadContact;
use Modules\Crm\Actions\MarkLeadLost;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Actions\ReopenLead;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Filament\Pages\LeadPage;
use Modules\Crm\Filament\Pages\Leads;
use Modules\Crm\Filament\Teacher\MyTrials;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TrialConversion;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Queries\FindTeachersQuery;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The CRM and trial lessons (docs/architecture/01, section 14 and flow "أ";
 * 02, "الحصة التجريبية"). The fixture: Monday 5 October 2026; a Cairo
 * teacher and a Riyadh teacher teach Quran on Tuesdays and Saturdays
 * evenings; an enrolled London student has Tuesdays and Saturdays 18:00
 * Cairo. A salesperson works the leads.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->sales = User::factory()->withRole(Role::Sales)->create(['name' => 'Salma Sales']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function newLead(array $over = []): Lead
{
    return app(CreateLead::class)->handle($over + [
        'contact_name' => 'Umm Yousef', 'phone_e164' => '+966501234567', 'country' => 'SA', 'timezone' => 'Asia/Riyadh',
        'for_child' => true, 'learner_name' => 'Yousef', 'learner_birth_year' => 2017, 'subject_id' => test()->quran,
        'preferred_days' => [2, 6], 'preferred_from' => 17, 'preferred_to' => 21, 'source' => 'whatsapp',
    ], test()->sales->id);
}

function bookTrialFor(string $learnerId, string $cairo = '2026-10-13 19:00', ?User $teacher = null): Session
{
    $batch = app(PlaceBookingHold::class)->handle(($teacher ?? test()->teacher)->id, $learnerId, [CarbonImmutable::parse($cairo, 'Africa/Cairo')->getTimestamp()], 30, test()->admin->id);

    return app(ConfirmBooking::class)->trial($batch, $learnerId, test()->quran, test()->admin->id);
}

function crmSent(string $userId, string $template): int
{
    return Notification::query()->where('user_id', $userId)->where('template_key', $template)->count();
}

it('adds a lead from what sales hear, and refuses the same number twice while the first is followed', function () {
    $lead = newLead(['phone_e164' => '00966 50-123 4567']);

    expect($lead->phone_e164)->toBe('+966501234567')
        ->and([$lead->stage, $lead->owner_id, $lead->learnerName()])->toBe([LeadStage::New, $this->sales->id, 'Yousef'])
        ->and($lead->activities()->pluck('kind')->all())->toBe(['created'])
        ->and(fn () => newLead(['contact_name' => 'Abu Yousef']))->toThrow(ValidationException::class)
        ->and(fn () => newLead(['phone_e164' => '0501234567']))->toThrow(ValidationException::class)
        ->and(fn () => newLead(['phone_e164' => '+966500000001', 'learner_name' => null]))->toThrow(ValidationException::class);

    // Once lost, the number can come back as a new lead; reopening the old one is then refused.
    app(MarkLeadLost::class)->handle($lead, 'price', 'Too expensive for now', $this->sales->id);
    $again = newLead(['contact_name' => 'Umm Yousef again']);
    expect($again->stage)->toBe(LeadStage::New)
        ->and(fn () => app(ReopenLead::class)->handle($lead->refresh(), $this->sales->id))->toThrow(ValidationException::class);
});

it('moves a new lead on at the first call, keeps notes on the timeline, and can lose and reopen it', function () {
    $lead = newLead();

    app(LogLeadContact::class)->handle($lead, 'note', 'Asked about the price', $this->sales->id);
    expect($lead->refresh()->stage)->toBe(LeadStage::New)
        ->and(fn () => app(LogLeadContact::class)->handle($lead, 'note', ' ', $this->sales->id))->toThrow(ValidationException::class);

    app(LogLeadContact::class)->handle($lead, 'whatsapp', 'Sent the trial times', $this->sales->id);
    expect($lead->refresh()->stage)->toBe(LeadStage::Contacted)
        ->and($lead->last_contact_at)->not->toBeNull();

    app(MarkLeadLost::class)->handle($lead, 'times', null, $this->sales->id);
    expect([$lead->refresh()->stage, $lead->lost_reason])->toBe([LeadStage::Lost, 'times'])
        ->and(fn () => app(MarkLeadLost::class)->handle($lead, 'price', null, $this->sales->id))->toThrow(ValidationException::class);
    app(ReopenLead::class)->handle($lead, $this->sales->id);
    expect([$lead->refresh()->stage, $lead->lost_reason])->toBe([LeadStage::Contacted, null]);
});

it('gives the learner an account only when needed: a child under the parent, an adult on their own, a parent already here reused', function () {
    $child = newLead();
    $learner = app(PrepareLearner::class)->handle($child, $this->sales->id);
    $parent = $child->refresh()->contact_id;

    expect($learner)->not->toBe($parent)
        ->and(DB::table('user_roles')->where('user_id', $learner)->pluck('role')->all())->toBe(['student'])
        ->and(DB::table('users')->where('id', $parent)->value('phone_e164'))->toBe('+966501234567')
        ->and(DB::table('guardian_student')->where('guardian_id', $parent)->where('student_id', $learner)->exists())->toBeTrue()
        ->and(DB::table('student_profiles')->where('user_id', $learner)->value('birth_date'))->toStartWith('2017')
        ->and(app(PrepareLearner::class)->handle($child, $this->sales->id))->toBe($learner);

    // A second child of the same parent, later: the parent's account is reused.
    app(MarkLeadLost::class)->handle($child, 'not_now', null, $this->sales->id);
    $sister = newLead(['learner_name' => 'Mariam']);
    app(PrepareLearner::class)->handle($sister, $this->sales->id);
    expect($sister->refresh()->contact_id)->toBe($parent);

    // An adult is their own learner; a number that belongs to a student here cannot become a parent.
    $adult = newLead(['contact_name' => 'Omar', 'phone_e164' => '+447700900123', 'timezone' => 'Europe/London', 'for_child' => false]);
    $omar = app(PrepareLearner::class)->handle($adult, $this->sales->id);
    expect($adult->refresh()->contact_id)->toBe($omar)
        ->and(fn () => app(PrepareLearner::class)->handle(newLead(['contact_name' => 'Omar for his son', 'phone_e164' => '+447700900123', 'learner_name' => 'Ali']), $this->sales->id))
        ->toThrow(ValidationException::class);
});

it('books the trial from Find a teacher in trial mode as sales, and the family gets their own link to join', function () {
    $lead = newLead();
    $learner = app(PrepareLearner::class)->handle($lead, $this->sales->id);
    $this->actingAs($this->sales);
    Filament::setCurrentPanel('academy');

    $page = Livewire::withQueryParams([
        'mode' => 'trial', 'student' => $learner, 'subject' => $this->quran, 'tz' => 'Asia/Riyadh',
        'days' => '2', 'from' => '19', 'to' => '21', 'return' => '/admin/leads/'.$lead->id,
    ])->test(FindTeacher::class);
    $result = collect($page->instance()->results)->first(fn (array $r) => $r['teacher']->id === $this->teacher->id);
    $chip = collect($result['candidates'])->first();

    $page->call('pick', $this->teacher->id, $chip->key())
        ->call('pick', $this->teacher->id, collect($result['candidates'])->last()->key())     // trial mode: one time only
        ->assertSet('picked', [collect($result['candidates'])->last()->key()])
        ->call('hold')
        ->call('confirm', 'trial')
        ->assertRedirect('/admin/leads/'.$lead->id);

    $trial = LeadTrial::query()->where('lead_id', $lead->id)->firstOrFail();
    $session = Session::query()->findOrFail($trial->session_id);
    $link = Notification::query()->where('user_id', $lead->refresh()->contact_id)->where('template_key', 'scheduling.trial_booked')->value('body');

    expect($session->type->value)->toBe('trial')
        ->and($session->starts_at->setTimezone('Asia/Riyadh')->format('D'))->toBe('Tue')
        ->and($lead->stage)->toBe(LeadStage::TrialBooked)
        ->and($link)->toContain('/class/'.$session->id)->toContain('signature=')
        ->and(crmSent($this->teacher->id, 'scheduling.trial_booked_teacher'))->toBe(1)
        // A weekly series is not sales' to book.
        ->and(Livewire::test(FindTeacher::class)->set('holdBatch', 'x')->set('data.student_id', $learner)->call('confirm', 'series')->assertNotified(__('scheduling::find.errors.cannot_book')))->not->toBeNull();
});

it('follows the trial: attended asks the teacher to assess, and the assessment moves the lead and tells sales', function () {
    $lead = newLead();
    $session = bookTrialFor(app(PrepareLearner::class)->handle($lead, $this->sales->id));
    $trial = LeadTrial::query()->where('session_id', $session->id)->firstOrFail();

    event(new SessionFinalized($this->academy->id, $session->id, 'completed', []));

    expect($trial->refresh()->status)->toBe('attended')
        ->and($lead->refresh()->stage)->toBe(LeadStage::Attended)
        ->and(crmSent($this->teacher->id, 'crm.assess_trial'))->toBe(1)
        // The assessment replaces the lesson report.
        ->and(SessionReport::query()->where('session_id', $session->id)->exists())->toBeFalse()
        ->and(fn () => app(AssessTrial::class)->handle($trial, ['level' => 'x', 'notes' => 'y'], $this->other->id))->toThrow(ValidationException::class)
        ->and(fn () => app(AssessTrial::class)->handle($trial, ['level' => 'Beginner'], $this->teacher->id))->toThrow(ValidationException::class);

    $plan = Plan::query()->value('id');
    app(AssessTrial::class)->handle($trial, ['level' => 'Beginner: letters', 'plan_id' => $plan, 'per_week' => 2, 'notes' => 'Bright, shy at first.'], $this->teacher->id);

    expect([$trial->refresh()->level, $trial->recommended_plan_id, $trial->recommended_per_week])->toBe(['Beginner: letters', $plan, 2])
        ->and($lead->refresh()->stage)->toBe(LeadStage::Assessed)
        ->and(crmSent($this->sales->id, 'crm.trial_assessed'))->toBe(1);
});

it('sends the lead back to sales when the trial does not take place or is cancelled', function () {
    $lead = newLead();
    $learner = app(PrepareLearner::class)->handle($lead, $this->sales->id);
    $first = bookTrialFor($learner);

    event(new SessionFinalized($this->academy->id, $first->id, 'student_absent', []));
    expect($lead->refresh()->stage)->toBe(LeadStage::Contacted)
        ->and(LeadTrial::query()->where('session_id', $first->id)->value('status'))->toBe('missed')
        ->and(crmSent($this->sales->id, 'crm.trial_missed'))->toBe(1);

    $second = bookTrialFor($learner, '2026-10-17 19:00');
    expect($lead->refresh()->stage)->toBe(LeadStage::TrialBooked);
    app(CancelSession::class)->handle($second, 'student', 'school', null, $this->admin->id);
    expect($lead->refresh()->stage)->toBe(LeadStage::Contacted)
        ->and(LeadTrial::query()->where('session_id', $second->id)->value('status'))->toBe('cancelled');
});

it('wins the lead when its learner is enrolled, and counts it for the trial\'s teacher', function () {
    $lead = newLead();
    $learner = app(PrepareLearner::class)->handle($lead, $this->sales->id);
    $trial = bookTrialFor($learner);
    event(new SessionFinalized($this->academy->id, $trial->id, 'completed', []));

    $times = [];
    foreach (['2026-10-20 19:00', '2026-10-24 19:00'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse($first, 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $batch = app(PlaceBookingHold::class)->handle($this->teacher->id, $learner, $times, 30, $this->admin->id);
    $booked = app(EnrollStudent::class)->handle($batch, $learner, $this->quran, new EnrollmentTerms(Plan::query()->value('id'), $lead->refresh()->contact_id, PriceRegion::query()->value('id')), $this->admin->id);

    expect([$lead->refresh()->stage, $lead->enrollment_id])->toBe([LeadStage::Won, $booked->enrollmentId])
        ->and(app(TrialConversion::class)->counts([$this->teacher->id]))->toBe([$this->teacher->id => ['attended' => 1, 'won' => 1]])
        ->and(app(TrialConversion::class)->forTeachers([$this->teacher->id]))->toBe([]);          // fewer than three trials say nothing yet
});

it('puts the teachers whose trials become subscriptions first in a trial search', function () {
    // Three attended trials for the Cairo teacher, two of them won; none yet for the Riyadh teacher.
    foreach (['+966500000001', '+966500000002', '+966500000003'] as $i => $phone) {
        $lead = newLead(['phone_e164' => $phone, 'learner_name' => 'Child '.$i]);
        $session = bookTrialFor(app(PrepareLearner::class)->handle($lead, $this->sales->id), '2026-10-'.(13 + $i * 7).' 19:30');
        event(new SessionFinalized($this->academy->id, $session->id, 'completed', []));
        if ($i < 2) {
            $lead->refresh()->moveTo(LeadStage::Won, null);
        }
    }
    expect(app(TrialConversion::class)->forTeachers([$this->teacher->id, $this->other->id]))->toBe([$this->teacher->id => 2 / 3]);

    $pref = new StudentPreference('Asia/Riyadh', StudentPreference::windowsFromHours([2 => [19, 20]]), 1, 30, CarbonImmutable::parse('2026-10-06'), 2, 1);
    $trial = app(FindTeachersQuery::class)->run(new TeacherSearch($this->quran, $pref, forTrial: true));
    $series = app(FindTeachersQuery::class)->run(new TeacherSearch($this->quran, $pref));

    expect(collect($trial)->firstWhere('teacher.id', $this->teacher->id)['conversion'])->toBe(2 / 3)
        ->and(collect($trial)->firstWhere('teacher.id', $this->other->id)['conversion'])->toBeNull()
        ->and(collect($series)->firstWhere('teacher.id', $this->teacher->id)['conversion'])->toBeNull();
});

it('runs from the back office: the board, a new lead, a call on the timeline, and the way to Find a teacher', function () {
    $this->actingAs($this->sales);
    Filament::setCurrentPanel('academy');

    Livewire::test(Leads::class)
        ->callAction('create', data: [
            'contact_name' => 'Abu Khaled', 'phone_e164' => '+971501112233', 'country' => 'AE', 'timezone' => 'Asia/Dubai', 'locale' => 'ar',
            'for_child' => false, 'subject_id' => $this->quran, 'preferred_days' => [6], 'preferred_from' => 18, 'preferred_to' => 20, 'source' => 'instagram',
        ])
        ->assertHasNoActionErrors();
    $lead = Lead::query()->where('phone_e164', '+971501112233')->firstOrFail();

    Livewire::test(Leads::class)->assertSee('Abu Khaled')->set('search', 'nobody')->assertDontSee('Abu Khaled');

    Livewire::test(LeadPage::class, ['lead' => $lead->id])
        ->set('kind', 'call')->set('body', 'Wants Saturdays')->call('log')
        ->assertSee('Wants Saturdays')
        ->callAction('bookTrial')
        ->assertRedirectContains('mode=trial');

    expect($lead->refresh()->stage)->toBe(LeadStage::Contacted)
        ->and($lead->learner_id)->not->toBeNull();
});

it('lets the teacher assess only their own trials, from their area', function () {
    $lead = newLead();
    $session = bookTrialFor(app(PrepareLearner::class)->handle($lead, $this->sales->id));
    event(new SessionFinalized($this->academy->id, $session->id, 'completed', []));
    $trial = LeadTrial::query()->where('session_id', $session->id)->firstOrFail();

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(MyTrials::class)
        ->assertSee('Yousef')
        ->callAction('assess', data: ['level' => 'Can read short surahs', 'per_week' => 2, 'notes' => 'Ready for the 8-lesson plan.'], arguments: ['trial' => $trial->id, 'name' => 'Yousef'])
        ->assertHasNoActionErrors();
    expect($trial->refresh()->level)->toBe('Can read short surahs');

    $this->actingAs($this->other);
    // Another teacher does not see it (and AssessTrial refuses them, above).
    Livewire::test(MyTrials::class)->assertDontSee('Yousef')->assertSee(__('crm::trials.empty.to_assess'));
});

it('keeps leads to sales and the managers', function () {
    $lead = newLead();
    $accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->clearTenant();

    // Sales and the accountant do not watch the live board: the back office opens on their own first screen.
    $this->actingAs($this->sales)->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/leads');
    $this->get('http://noor.lms.test/admin/leads')->assertOk();
    $this->get('http://noor.lms.test/admin/leads/'.$lead->id)->assertOk();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($accountant)->get('http://noor.lms.test/admin/leads')->assertForbidden();
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/revenue');
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/teach/trials')->assertOk();
});
