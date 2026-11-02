<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Discounts\CouponCheck;
use Modules\Billing\Filament\Resources\Coupons\CouponResource;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Privacy\BillingData;
use Modules\Communication\Jobs\SendContactDelivery;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\FollowUpLeads;
use Modules\Crm\Actions\IssueTrialOffer;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Actions\SaveCrmSettings;
use Modules\Crm\Actions\SaveTrialOffer;
use Modules\Crm\Actions\SendTrialReport;
use Modules\Crm\Database\Seeders\DemoTrialReportSeeder;
use Modules\Crm\Filament\Pages\LeadPage;
use Modules\Crm\Filament\Pages\Leads;
use Modules\Crm\Filament\Teacher\MyTrials;
use Modules\Crm\Models\CrmSettings;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Privacy\LeadsData;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Http\LinkToken;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * After the trial: the family's report and the offer (docs/architecture/02,
 * section 27, J2). The fixture: Monday 5 October 2026, 10:00 in Riyadh; a
 * Cairo teacher (a woman) and a Riyadh teacher (a man) teach Quran on
 * Tuesdays and Saturdays; the academy's one plan is 8 lessons for SAR 400 in
 * its default region (the Gulf). Noor's page is on and families book. Umm
 * Yara asks for her nine-year-old's trial on Tuesday 19:00 (Riyadh and Cairo
 * share the clock in October); sales is Salma.
 */

const J2_API = 'http://noor.lms.test/api/v1/';

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->sales = User::factory()->withRole(Role::Sales)->create(['name' => 'Salma Sales']);
    SiteSettings::query()->create(['enabled' => true, 'content' => SiteContent::defaults()]);
    CommunicationSettings::query()->create(['whatsapp_enabled' => true, 'email_enabled' => true, 'push_enabled' => true]);
    $this->plan = Plan::query()->sole();
    $this->actingAs($this->admin);
    app(SaveTrialOffer::class)->handle(true, 10, 72);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** A request to Noor's API (with a token when given), then back in Noor. */
function j2Api(string $method, string $path, array $data = [], ?string $token = null, array $headers = []): TestResponse
{
    app('auth')->forgetGuards();
    $request = test()->withHeaders($headers);
    if ($token !== null) {
        $request = $request->withToken($token);
    }
    $response = $request->json($method, str_starts_with($path, 'http') ? $path : J2_API.$path, $data);
    test()->actAsTenant(test()->academy);

    return $response;
}

/** Umm Yara's lead, the accounts PrepareLearner makes, and the trial booked with a teacher (as Find a teacher books it). */
function j2Trial(array $lead = [], string $riyadh = '2026-10-06 19:00', ?User $teacher = null): LeadTrial
{
    $lead = app(CreateLead::class)->handle($lead + [
        'contact_name' => 'Umm Yara', 'phone_e164' => '+966501234567', 'email' => 'umm.yara@example.test', 'country' => 'SA', 'timezone' => 'Asia/Riyadh',
        'for_child' => true, 'learner_name' => 'Yara Salem', 'learner_birth_year' => 2017, 'subject_id' => test()->quran, 'source' => 'website',
    ], test()->sales->id);
    $learner = app(PrepareLearner::class)->handle($lead, test()->sales->id);
    $batch = app(PlaceBookingHold::class)->handle(($teacher ?? test()->teacher)->id, $learner, [CarbonImmutable::parse($riyadh, 'Asia/Riyadh')->getTimestamp()], 30, test()->admin->id);
    $session = app(ConfirmBooking::class)->trial($batch, $learner, test()->quran, test()->admin->id);

    return LeadTrial::query()->with('lead')->where('session_id', $session->id)->sole();
}

/** The lesson ends as it went (completed, student_absent, teacher_absent). */
function j2Held(LeadTrial $trial, string $outcome = 'completed'): LeadTrial
{
    CarbonImmutable::setTestNow($trial->starts_at->addMinutes(40));
    DB::table('sessions')->where('id', $trial->session_id)->update(['status' => $outcome]);
    event(new SessionFinalized(test()->academy->id, $trial->session_id, $outcome, []));

    return $trial->refresh();
}

function j2Assess(LeadTrial $trial, array $data = []): LeadTrial
{
    app(AssessTrial::class)->handle($trial, $data + [
        'level' => 'Beginner: reads the letters', 'plan_id' => test()->plan->id, 'per_week' => 2,
        'strengths' => 'Clear voice, asks questions.', 'to_work_on' => 'The long vowels.', 'family_note' => 'Yara did really well today.',
        'notes' => 'Mother is price-sensitive; mention the sibling discount.',
    ], $trial->teacher_id);

    return $trial->refresh();
}

/** The link token in the family's latest message of this template. */
function j2Token(LeadTrial $trial, string $template = 'crm.trial_report_ready', string $path = 'report'): string
{
    $body = (string) Notification::query()->where('user_id', $trial->lead->contact_id)->where('template_key', $template)->latest('created_at')->value('body');
    expect(preg_match('#/trial/'.$path.'/([A-Za-z0-9_\-.]+)#', $body, $m))->toBe(1, "No {$path} link in: {$body}");

    return $m[1];
}

/** The office enrols the trial's learner with the Riyadh teacher, Tuesdays and Saturdays 20:00 (the offer's code when given). */
function j2Enrol(LeadTrial $trial, ?string $coupon = null): string
{
    $times = array_map(fn (string $at) => CarbonImmutable::parse($at, 'Asia/Riyadh')->getTimestamp(), ['2026-10-13 20:00', '2026-10-17 20:00']);
    $batch = app(PlaceBookingHold::class)->handle(test()->other->id, $trial->lead->learner_id, $times, 30, test()->admin->id);

    return app(EnrollStudent::class)->handle($batch, $trial->lead->learner_id, test()->quran,
        new EnrollmentTerms(test()->plan->id, $trial->lead->contact_id, PriceRegion::query()->where('is_default', true)->value('id'), $coupon), test()->admin->id)->enrollmentId;
}

function j2Sent(?string $userId, string $template): int
{
    return Notification::query()->where('user_id', $userId)->where('template_key', $template)->count();
}

it('asks the teacher what the family reads apart from the notes for the academy, and keeps a family field an older app leaves out', function () {
    $trial = j2Held(j2Trial());
    $token = $this->teacher->createToken('phone')->plainTextToken;

    $index = j2Api('GET', 'teacher/trials', token: $token)->assertOk();
    expect($index->json('family_reads'))->toBe(['level', 'strengths', 'to_work_on', 'family_note', 'plan_id', 'per_week']);

    j2Api('PUT', "teacher/trials/{$trial->id}/assessment", [
        'level' => 'Beginner', 'plan_id' => $this->plan->id, 'per_week' => 2, 'notes' => 'For sales only',
        'strengths' => 'Reads clearly', 'to_work_on' => 'Long vowels', 'family_note' => 'Welcome, Yara!',
    ], $token)->assertOk()->assertJsonPath('data.family_note', 'Welcome, Yara!');

    // The app from before J2 corrects the level: the family's fields stay.
    j2Api('PUT', "teacher/trials/{$trial->id}/assessment", ['level' => 'Beginner, letters', 'notes' => 'For sales only'], $token)->assertOk();
    expect($trial->refresh()->only(['level', 'strengths', 'to_work_on', 'family_note']))
        ->toBe(['level' => 'Beginner, letters', 'strengths' => 'Reads clearly', 'to_work_on' => 'Long vowels', 'family_note' => 'Welcome, Yara!'])
        ->and(fn () => app(AssessTrial::class)->handle($trial, ['level' => 'x', 'notes' => 'y', 'family_note' => str_repeat('a', 1001)], $this->teacher->id))
        ->toThrow(ValidationException::class);
});

it('sends the family its report once, with its own link, and the report reads what the assessment says now', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $parent = $trial->lead->contact_id;

    expect(j2Sent($parent, 'crm.trial_report_ready'))->toBe(1)
        ->and(j2Sent($trial->lead->learner_id, 'crm.trial_report_ready'))->toBe(0)     // a child's own account: never
        ->and($trial->report_sent_at)->not->toBeNull()
        ->and(Notification::query()->where('user_id', $parent)->where('template_key', 'crm.trial_report_ready')->value('body'))
        ->toContain('/ar/trial/report/')->toContain('10%');

    $report = j2Api('GET', 'site/trials/report/'.j2Token($trial))->assertOk()->json('data');
    expect($report)->toMatchArray([
        'id' => $trial->session_id, 'learner' => 'Yara', 'level' => 'Beginner: reads the letters', 'strengths' => 'Clear voice, asks questions.',
        'to_work_on' => 'The long vowels.', 'note' => 'Yara did really well today.', 'second' => false, 'course' => null, 'offer_state' => 'open',
    ])
        ->and($report['subject']['name'])->toBe('Quran')
        ->and($report['teacher']['name'])->toBe(preg_match('/^(?:\S+\.\s+)?\S+/u', $this->teacher->name, $m) ? $m[0] : '')
        ->and($report['teacher']['card'])->toBeNull()
        ->and($report['recommendation']['plan']['id'])->toBe($this->plan->id)
        ->and($report['recommendation']['per_week'])->toBe(2)
        ->and($report['recommendation']['price']['amount_minor'])->toBe(40000)
        ->and($report['recommendation']['region']['currency'])->toBe('SAR')
        ->and($report['held_at']['timezone'])->toBe('Asia/Riyadh')
        // Never the teacher's notes for the academy.
        ->and(json_encode($report))->not->toContain('price-sensitive');

    // Corrected later: the report says it, and no second message goes.
    j2Assess($trial, ['level' => 'Beginner: reads with vowels']);
    expect(j2Api('GET', 'site/trials/report/'.j2Token($trial))->json('data.level'))->toBe('Beginner: reads with vowels')
        ->and(j2Sent($parent, 'crm.trial_report_ready'))->toBe(1);

    // Whoever follows the lead may send it again (a new link), once an hour.
    app(SendTrialReport::class)->handle($trial->refresh(), $this->sales->id, again: true);
    app(SendTrialReport::class)->handle($trial->refresh(), $this->sales->id, again: true);
    expect(j2Sent($parent, 'crm.trial_report_ready'))->toBe(2)
        ->and(LeadActivity::query()->where('lead_id', $trial->lead_id)->pluck('kind')->all())->toContain('offer_made', 'report_sent', 'report_resent');
});

it('has no report until the trial took place and was assessed', function () {
    $trial = j2Trial();
    expect(fn () => app(SendTrialReport::class)->handle($trial, $this->sales->id, again: true))->toThrow(ValidationException::class);

    $missed = j2Held($trial, 'student_absent');
    expect(fn () => app(AssessTrial::class)->handle($missed, ['level' => 'x', 'notes' => 'y'], $this->teacher->id))->toThrow(ValidationException::class)
        ->and(j2Sent($missed->lead->contact_id, 'crm.trial_report_ready'))->toBe(0);
    $guardian = User::query()->find($missed->lead->contact_id)->createToken('phone')->plainTextToken;
    j2Api('GET', "family/trials/{$missed->session_id}", token: $guardian)->assertNotFound();
});

it('makes the offer once per learner and subject, only while the academy has one and the learner has not started', function () {
    // Off: no offer, and the message says nothing of one.
    $this->actingAs($this->admin);
    app(SaveTrialOffer::class)->handle(false, 10, 72);
    $off = j2Assess(j2Held(j2Trial()));
    expect($off->offer_coupon_id)->toBeNull()
        ->and(Notification::query()->where('user_id', $off->lead->contact_id)->where('template_key', 'crm.trial_report_ready')->value('body'))->not->toContain('%');

    // On again: a second assessment of the same trial makes none (only the first one does).
    app(SaveTrialOffer::class)->handle(true, 15, 48);
    expect(j2Assess($off)->offer_coupon_id)->toBeNull();

    // Another learner gets one: 15% for 48 hours from the report, for the parent alone, once.
    $ali = j2Assess(j2Held(j2Trial(['phone_e164' => '+966507777777', 'learner_name' => 'Ali'], '2026-10-10 19:00')));
    $coupon = Coupon::query()->findOrFail($ali->offer_coupon_id);
    expect([$ali->offer_percent_bp, $ali->offer_ends_at->toIso8601ZuluString()])->toBe([1500, CarbonImmutable::now()->addHours(48)->toIso8601ZuluString()])
        ->and([$coupon->purpose->value, $coupon->payer_id, $coupon->max_uses, $coupon->blocks, $coupon->with_sibling, $coupon->plan_ids])
        ->toBe(['trial_offer', $ali->lead->contact_id, 1, 1, false, []])
        ->and($coupon->code)->toStartWith('TRIAL-');

    // Settings changed later: the offer made keeps what it promised.
    app(SaveTrialOffer::class)->handle(true, 30, 24);
    expect(app(BillingApi::class)->personalCoupon($coupon->id)?->percentBp)->toBe(1500);
});

it('makes no offer for a learner who already studies the subject, and the report says they started', function () {
    $trial = j2Held(j2Trial());
    // The office enrols Yara before the teacher writes the assessment.
    j2Enrol($trial);

    $trial = j2Assess($trial);
    $report = j2Api('GET', 'site/trials/report/'.j2Token($trial))->assertOk()->json('data');
    expect($trial->offer_coupon_id)->toBeNull()
        ->and($report['course'])->toBe(['status' => 'pending_payment'])
        ->and([$report['offer_state'], $report['offer'], $report['next_trial']])->toBe(['none', null, null]);
});

it('hides the offer once the learner starts, ends it at its moment, and shows the plain price after', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $token = j2Token($trial);
    $offer = j2Api('GET', "site/trials/report/{$token}")->json('data.offer');
    expect($offer['percent'])->toEqual(10)
        ->and($offer['ends_at']['utc'])->toBe($trial->offer_ends_at->toIso8601ZuluString())
        ->and([$offer['price']['amount_minor'], $offer['saving']['amount_minor'], $offer['family_discount_bigger']])->toBe([36000, 4000, false]);

    // The plan's price changes: the offer is a percentage, so its price follows.
    DB::table('plan_prices')->where('plan_id', $this->plan->id)->update(['amount_minor' => 50000]);
    expect(j2Api('GET', "site/trials/report/{$token}")->json('data.offer.price.amount_minor'))->toBe(45000);

    // A minute after its end: the plain price, no offer; the coupon is refused at its moment, not only its day.
    CarbonImmutable::setTestNow($trial->offer_ends_at->addMinute());
    $after = j2Api('GET', "site/trials/report/{$token}")->json('data');
    expect([$after['offer_state'], $after['offer'], $after['recommendation']['price']['amount_minor']])->toBe(['ended', null, 50000])
        ->and(fn () => app(CouponCheck::class)->usable((string) Coupon::query()->whereKey($trial->offer_coupon_id)->value('code'), $trial->lead->contact_id, 'SAR', $this->plan->id))
        ->toThrow(ValidationException::class, __('billing::discounts.errors.expired', ['code' => Coupon::query()->whereKey($trial->offer_coupon_id)->value('code')]));
});

it('applies the bigger of the offer and the family discount, never both', function () {
    // Yara's mother already pays for Zaid here: a 20% family discount for a second child beats the 10% offer.
    $guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $guardian->forceFill(['phone_e164' => '+966501234567'])->save();
    BillingSettings::current()->fill(['sibling_second_bp' => 2000])->save();

    $trial = j2Assess(j2Held(j2Trial()));
    expect($trial->lead->contact_id)->toBe($guardian->id);
    $offer = j2Api('GET', 'site/trials/report/'.j2Token($trial))->json('data.offer');
    expect([$offer['price']['amount_minor'], $offer['saving']['amount_minor'], $offer['family_discount_bigger']])->toBe([32000, 0, true]);
});

it('prices the report in the region the family pays in, else the payer\'s or the learner\'s country\'s, else the default', function () {
    $levant = PriceRegion::query()->create(['name_ar' => 'الشام', 'name_en' => 'Levant', 'currency' => 'USD', 'is_default' => false, 'countries' => ['JO']]);
    $this->plan->prices()->create(['price_region_id' => $levant->id, 'amount_minor' => 9000, 'currency' => 'USD']);

    $jordan = j2Assess(j2Held(j2Trial(['country' => 'JO'])));
    $report = j2Api('GET', 'site/trials/report/'.j2Token($jordan))->json('data');
    expect([$report['recommendation']['price']['amount_minor'], $report['recommendation']['region']['currency'], $report['offer']['price']['amount_minor']])
        ->toBe([9000, 'USD', 8100]);

    // The lead's country went on the learner and the parent (PrepareLearner), which is what prices them.
    $people = app(PeopleApi::class);
    expect([$people->countryOf((string) $jordan->lead->learner_id), $people->countryOf((string) $jordan->lead->contact_id)])->toBe(['JO', 'JO']);

    // A country no region names: the default one; the learner's when the payer has none; a payer who pays here
    // already: their account's, whatever their country.
    $billing = app(BillingApi::class);
    $gulf = PriceRegion::query()->where('is_default', true)->value('id');
    $guardian = DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id');
    $people->fillCountry($this->student->id, 'FR');
    expect($billing->priceRegionFor(null, $this->student->id)?->id)->toBe($gulf)
        ->and($billing->priceRegionFor(null, (string) $jordan->lead->learner_id)?->id)->toBe($levant->id)
        ->and($billing->priceRegionFor((string) $jordan->lead->contact_id, null)?->id)->toBe($levant->id);
    $people->fillCountry($guardian, 'JO');
    expect($billing->priceRegionFor($guardian, (string) $jordan->lead->learner_id)?->id)->toBe($gulf);
});

it('reminds the family of its offer once in its last day, and never once it was used or the learner started', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $parent = $trial->lead->contact_id;
    $follow = fn (CarbonImmutable $at) => app(FollowUpLeads::class)->handle($at);

    $follow($trial->offer_ends_at->subHours(25));
    expect(j2Sent($parent, 'crm.trial_offer_ending'))->toBe(0);
    $follow($trial->offer_ends_at->subHours(23));
    $follow($trial->offer_ends_at->subHours(22));
    expect(j2Sent($parent, 'crm.trial_offer_ending'))->toBe(1)
        ->and(Notification::query()->where('user_id', $parent)->where('template_key', 'crm.trial_offer_ending')->value('body'))->toContain('/trial/report/')
        ->and($trial->refresh()->offer_reminded_at)->not->toBeNull();

    // Another family started a course with theirs (the office applied it): no reminder, and the report says they started.
    $ali = j2Assess(j2Held(j2Trial(['phone_e164' => '+966507777777', 'learner_name' => 'Ali'], '2026-10-10 19:00')));
    j2Enrol($ali, (string) Coupon::query()->whereKey($ali->offer_coupon_id)->value('code'));
    expect(app(BillingApi::class)->personalCoupon($ali->offer_coupon_id)?->used)->toBeTrue();
    $follow($ali->refresh()->offer_ends_at->subHours(20));
    expect(j2Sent($ali->lead->contact_id, 'crm.trial_offer_ending'))->toBe(0)
        ->and(j2Api('GET', 'site/trials/report/'.j2Token($ali))->json('data.offer_state'))->toBe('started');
});

it('keeps a personal offer to its payer: refused to anyone else as no such code, never edited by staff, kept off the coupons screen', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $coupon = Coupon::query()->findOrFail($trial->offer_coupon_id);
    $someone = User::factory()->withRole(Role::Guardian)->create()->id;

    expect(fn () => app(CouponCheck::class)->usable($coupon->code, $someone, 'SAR', $this->plan->id))->toThrow(ValidationException::class, $coupon->code)
        ->and(app(CouponCheck::class)->usable($coupon->code, $trial->lead->contact_id, 'SAR', $this->plan->id)->id)->toBe($coupon->id)
        ->and(fn () => app(SaveCoupon::class)->handle($coupon->id, ['code' => $coupon->code, 'kind' => 'percent', 'percent' => 50], $this->admin->id))
        ->toThrow(ValidationException::class)
        ->and(CouponResource::getEloquentQuery()->pluck('id')->all())->not->toContain($coupon->id);
});

it('opens the report from its link only: another academy\'s, a forged or another purpose\'s token is not found; one that ended has ended', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $token = j2Token($trial);

    j2Api('GET', 'site/trials/report/'.substr($token, 0, -3).'abc')->assertNotFound();
    j2Api('GET', 'site/trials/report/'.LinkToken::issue('trial-rebook', $trial->id, CarbonImmutable::now()->addDay()))->assertNotFound();
    $this->createAcademy(['slug' => 'other']);
    $this->actAsTenant($this->academy);
    j2Api('GET', 'http://other.lms.test/api/v1/site/trials/report/'.$token)->assertNotFound();

    $this->travelTo(CarbonImmutable::now()->addDays(31));
    j2Api('GET', "site/trials/report/{$token}", headers: ['Accept-Language' => 'ar'])->assertStatus(410)
        ->assertJsonPath('type', 'link-expired')
        ->assertJsonPath('detail', __('crm::report.errors.link_expired', [], 'ar'));
});

it('shows a signed-in family its trials and reports, without money or booking in a child\'s own look', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $guardian = User::query()->find($trial->lead->contact_id)->createToken('phone')->plainTextToken;
    $child = User::query()->find($trial->lead->learner_id)->createToken('phone')->plainTextToken;

    $list = j2Api('GET', 'family/trials?learner_id='.$trial->lead->learner_id, token: $guardian)->assertOk();
    expect($list->json('viewer_stage'))->toBe('classic')
        ->and($list->json('data.0'))->toMatchArray(['id' => $trial->session_id, 'status' => 'attended', 'kind' => 'first', 'report_ready' => true, 'outcome' => null])
        ->and($list->json('data.0.next'))->toBeNull();      // the academy gives no second trial yet

    $own = j2Api('GET', "family/trials/{$trial->session_id}", token: $guardian)->assertOk();
    expect($own->json('data.offer_state'))->toBe('open')->and($own->json('data.recommendation.price.amount_minor'))->toBe(40000);

    $kids = j2Api('GET', "family/trials/{$trial->session_id}", token: $child)->assertOk();
    expect($kids->json('viewer_stage'))->toBe('kids')
        ->and([$kids->json('data.offer_state'), $kids->json('data.offer'), $kids->json('data.recommendation.price'), $kids->json('data.next_trial')])
        ->toBe(['none', null, null, null])
        ->and($kids->json('data.note'))->toBe('Yara did really well today.');
    j2Api('POST', "family/trials/{$trial->session_id}/book", ['starts_at' => '2026-10-10T16:00:00Z'], $child, ['Idempotency-Key' => 'kid-1'])->assertForbidden();

    // Someone who does not follow Yara: not found.
    $stranger = User::factory()->withRole(Role::Guardian)->create()->createToken('phone')->plainTextToken;
    j2Api('GET', "family/trials/{$trial->session_id}", token: $stranger)->assertNotFound();
});

it('keeps the report readable without the CRM in the academy\'s plan or with its page off', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $token = j2Token($trial);
    $plan = SaasPlan::query()->create(['code' => 'basic', 'name_ar' => 'أساسية', 'name_en' => 'Basic', 'max_teachers' => 5, 'max_active_students' => 50, 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    SiteSettings::query()->update(['enabled' => false]);

    j2Api('GET', "site/trials/report/{$token}")->assertOk()->assertJsonPath('data.offer_state', 'open');
});

it('saves the offer only within its bounds, for whoever manages leads, audited', function () {
    $this->actingAs($this->admin);
    try {
        app(SaveTrialOffer::class)->handle(true, 60, 12);
        $this->fail('Saved an offer out of bounds.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['trial_offer_percent', 'trial_offer_hours'])
            ->and($e->errors()['trial_offer_percent'][0])->toContain('5')->toContain('50');
    }
    app(SaveTrialOffer::class)->handle(true, 20, 96);
    app(SaveCrmSettings::class)->handle(true, secondTrial: true);
    expect(CrmSettings::current()->only(['trial_offer', 'trial_offer_percent', 'trial_offer_hours', 'second_trial']))
        ->toBe(['trial_offer' => true, 'trial_offer_percent' => 20, 'trial_offer_hours' => 96, 'second_trial' => true])
        ->and(DB::table('audit_logs')->whereIn('action', ['crm.trial_offer_saved', 'crm.second_trial_saved'])->pluck('action')->all())
        ->toContain('crm.trial_offer_saved', 'crm.second_trial_saved');

    $this->actingAs(User::factory()->withRole(Role::Teacher)->create());
    expect(fn () => app(SaveTrialOffer::class)->handle(true, 10, 72))->toThrow(AuthorizationException::class);
});

it('puts the reports and offers in the family\'s copy, and blanks them on erasure', function () {
    $trial = j2Assess(j2Held(j2Trial()));
    $parent = $trial->lead->contact_id;
    $token = j2Token($trial);

    $copy = app(LeadsData::class)->export($parent)->data;
    expect($copy['trial_reports'][0])->toMatchArray(['level' => 'Beginner: reads the letters', 'family_note' => 'Yara did really well today.'])
        ->and(app(BillingData::class)->export($parent)->data['offers'][0]['percent_bp'])->toBe(1000);

    app(LeadsData::class)->erase($parent);
    expect(app(BillingData::class)->erase($parent))->toBe(['offers' => 1])
        ->and($trial->refresh()->only(['level', 'strengths', 'to_work_on', 'family_note', 'assessment']))->each->toBeNull()
        ->and(app(BillingApi::class)->personalCoupon($trial->offer_coupon_id)?->open())->toBeFalse();
    j2Api('GET', "site/trials/report/{$token}")->assertNotFound();
});

it('tells a family with no account by WhatsApp and email at what they gave', function () {
    $trial = j2Held(j2Trial());
    $trial->lead->forceFill(['contact_id' => null, 'learner_id' => null])->save();
    Queue::fake([SendContactDelivery::class]);
    app(SendTrialReport::class)->handle(j2Assess($trial));
    expect(Queue::pushed(SendContactDelivery::class))->toHaveCount(2);
});

it('makes the demo\'s report with an open offer once, and leaves an owner\'s settings alone', function () {
    // An older demo: its trial assessed before the academy had an offer.
    CrmSettings::query()->update(['trial_offer' => false, 'updated_by' => null]);
    $trial = j2Held(j2Trial(['contact_name' => 'أم جنى', 'learner_name' => 'جنى']));
    app(AssessTrial::class)->handle($trial, ['level' => 'تقرأ جيدًا', 'notes' => 'للأكاديمية'], $trial->teacher_id);
    expect($trial->refresh()->offer_coupon_id)->toBeNull();

    app(DemoTrialReportSeeder::class)->run(app(IssueTrialOffer::class));
    $trial->refresh();
    expect([$trial->offer_coupon_id !== null, $trial->family_note !== null, CrmSettings::current()->second_trial])->toBe([true, true, true]);
    $coupon = $trial->offer_coupon_id;
    app(DemoTrialReportSeeder::class)->run(app(IssueTrialOffer::class));
    expect(Coupon::query()->where('purpose', 'trial_offer')->count())->toBe(1)->and($trial->refresh()->offer_coupon_id)->toBe($coupon);

    // An older demo's colloquial level reads in Modern Standard Arabic once the family sees it in the report.
    $trial->forceFill(['level' => 'بتقرأ كويس، ومحتاجة تشتغل على أحكام النون الساكنة والمدود.'])->save();
    app(DemoTrialReportSeeder::class)->run(app(IssueTrialOffer::class));
    expect($trial->refresh()->level)->toBe('تقرأ جيدًا، وتحتاج إلى العمل على أحكام النون الساكنة والمدود.');
});

it('runs from the panels: the teacher writes the family\'s part apart from the academy\'s, sales see the report and the offer and send it again, settings save the offer', function () {
    $trial = j2Held(j2Trial());
    $this->actingAs($this->teacher);
    Filament\Facades\Filament::setCurrentPanel('teach');
    Livewire\Livewire::test(MyTrials::class)
        ->callAction('assess', data: ['level' => 'Reads short surahs', 'strengths' => 'Confident', 'to_work_on' => 'Long vowels', 'family_note' => 'A joy to teach',
            'plan_id' => $this->plan->id, 'per_week' => 2, 'notes' => 'Ask about the sibling'], arguments: ['trial' => $trial->id, 'name' => 'Yara'])
        ->assertHasNoActionErrors()
        ->assertSee('A joy to teach')->assertSee(__('crm::trials.family_reads'));
    expect($trial->refresh()->only(['strengths', 'family_note']))->toBe(['strengths' => 'Confident', 'family_note' => 'A joy to teach']);

    $this->actingAs($this->sales);
    Filament\Facades\Filament::setCurrentPanel('academy');
    Livewire\Livewire::test(LeadPage::class, ['lead' => $trial->lead_id])
        ->assertSee('A joy to teach')->assertSee('10%')->assertSee(__('crm::leads.page.family_reads'))
        ->callAction('resendReport', arguments: ['trial' => $trial->id])
        ->assertHasNoActionErrors();
    expect(j2Sent($trial->lead->contact_id, 'crm.trial_report_ready'))->toBe(2);

    $this->actingAs($this->admin);
    Livewire\Livewire::test(Leads::class)
        ->callAction('settings', data: ['assign_website_leads' => true, 'trial_offer' => true, 'trial_offer_percent' => 25, 'trial_offer_hours' => 48, 'second_trial' => true])
        ->assertHasNoActionErrors();
    expect(CrmSettings::current()->only(['trial_offer_percent', 'trial_offer_hours', 'second_trial']))->toBe(['trial_offer_percent' => 25, 'trial_offer_hours' => 48, 'second_trial' => true]);
});
