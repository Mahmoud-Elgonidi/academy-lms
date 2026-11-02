<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\Collections\FollowUpCollections;
use Modules\Billing\Actions\Collections\SendPayLink;
use Modules\Billing\Actions\Proofs\RejectPaymentProof;
use Modules\Billing\Actions\Proofs\SubmitPaymentProof;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Models\Notification;
use Modules\Credits\Actions\ExpireCredits;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\LotSource;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The collection timeline and a clear balance (docs/architecture/02, section
 * 27, J5a). The fixture: Monday 5 October 2026, 07:00 UTC (10:00 in Riyadh,
 * where the paying guardian lives); an adult Quran student's first block
 * (8 × 30 minutes, SAR 400) waits to be paid, due Thursday 8 October 07:00
 * UTC; the first lesson is Tuesday 6 October 18:00 Cairo (15:00 UTC), so
 * rule 16 (24 hours) would cancel it unpaid at Monday 15:00 UTC.
 */

const J5_API = 'http://noor.lms.test/api/v1/';

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $this->payer = User::query()->findOrFail(DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id'));
    $this->payer->forceFill(['timezone' => 'Asia/Riyadh'])->save();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function j5At(string $utc): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($utc, 'UTC'));
}

function j5Follow(): int
{
    return app(FollowUpCollections::class)->handle();
}

/** @return list<string> the timeline's templates each person heard, in order */
function j5Heard(string $userId): array
{
    return Notification::query()->where('user_id', $userId)->where('template_key', 'like', 'billing.%')
        ->orderBy('created_at')->orderBy('id')->pluck('template_key')->all();
}

function j5Api(string $method, string $path, User $as, array $data = []): TestResponse
{
    app('auth')->forgetGuards();
    $response = test()->withToken($as->createToken('phone')->plainTextToken)
        ->withHeaders(array_filter(['Accept-Language' => 'ar', 'Idempotency-Key' => $method === 'GET' ? null : 'k-'.bin2hex(random_bytes(6))]))
        ->json($method, J5_API.$path, $data);
    test()->actAsTenant(test()->academy);

    return $response;
}

/** Two lessons' minutes bought and not booked yet, lapsing on that day: the lessons that would be lost. */
function j5Spare(string $expiresAt): string
{
    $account = DB::table('enrollments')->where('id', test()->booked->enrollmentId)->value('credit_account_id');

    return app(CreditsApi::class)->grant($account, LotSource::Purchase, null, 60, CarbonImmutable::parse($expiresAt, 'UTC'), 'spare-'.bin2hex(random_bytes(4)));
}

function j5Pay(): void
{
    app(BillingApi::class)->recordPayment(test()->invoice->id, 'bank_transfer', test()->invoice->refresh()->outstanding(), 'T-1', CarbonImmutable::now(), test()->accountant->id);
}

it('walks the timeline once each: the lesson at risk, the day before, the day, two days late with the office told', function () {
    // The invoice message itself went when it was issued, and is the timeline's first step.
    expect(CollectionStep::query()->where('invoice_id', $this->invoice->id)->pluck('step')->all())->toBe(['issued'])
        ->and(j5Heard($this->payer->id))->toBe(['billing.invoice_issued']);

    // Monday 10:00 Riyadh: rule 16 would cancel Tuesday's lesson at 18:00 Riyadh today: pay to keep it.
    expect(j5Follow())->toBe(1)->and(j5Follow())->toBe(0);
    $risk = Notification::query()->where('template_key', 'billing.lesson_at_risk')->sole();
    expect($risk->user_id)->toBe($this->payer->id)
        ->and($risk->body)->toContain('الثلاثاء 6 أكتوبر')->toContain('400')
        ->and($risk->action)->toMatchArray(['screen' => 'invoice', 'id' => $this->invoice->id]);

    // Tuesday: nothing (not the day before yet).
    j5At('2026-10-06 07:00');
    expect(j5Follow())->toBe(0);
    // Wednesday before nine in Riyadh: not yet; then the day before.
    j5At('2026-10-07 05:30');
    expect(j5Follow())->toBe(0);
    j5At('2026-10-07 06:00');
    expect(j5Follow())->toBe(1)->and(j5Follow())->toBe(0);
    // Thursday 09:30 Riyadh, before its 10:00 moment: due today.
    j5At('2026-10-08 06:30');
    expect(j5Follow())->toBe(1);
    // Friday: nothing; Saturday, two days late: the payer, and whoever collects (not the teacher).
    j5At('2026-10-09 07:00');
    expect(j5Follow())->toBe(0);
    j5At('2026-10-10 07:00');
    expect(j5Follow())->toBe(1)->and(j5Follow())->toBe(0);
    j5At('2026-10-11 07:00');
    expect(j5Follow())->toBe(0);

    expect(j5Heard($this->payer->id))->toBe(['billing.invoice_issued', 'billing.lesson_at_risk', 'billing.due_tomorrow', 'billing.due_today', 'billing.overdue'])
        ->and(Notification::query()->where('template_key', 'billing.overdue_staff')->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->admin->id, $this->accountant->id])->sort()->values()->all())
        // Never the learner's own account: the payer hears it.
        ->and(Notification::query()->where('user_id', $this->student->id)->where('template_key', 'like', 'billing.%')->count())->toBe(0)
        ->and(CollectionStep::query()->where('invoice_id', $this->invoice->id)->count())->toBe(5)
        // The office's copy says how late in words that agree with the number.
        ->and(Notification::query()->where('template_key', 'billing.overdue_staff')->pluck('title')->unique()->all())->toBe(['فاتورة متأخرة يومين']);
});

it('stops at once when the office records the payment by hand mid-timeline', function () {
    j5At('2026-10-07 06:00');
    // Tuesday's lesson has gone by (rule 16 would have cancelled it); Saturday's is not a day away yet: the day before only.
    expect(j5Follow())->toBe(1);
    j5Pay();
    foreach (['2026-10-08 06:30', '2026-10-10 07:00'] as $at) {
        j5At($at);
        expect(j5Follow())->toBe(0);
    }
    expect(j5Heard($this->payer->id))->not->toContain('billing.due_today')->not->toContain('billing.overdue');
});

it('waits while a transfer receipt is being checked, and goes on once it is turned down', function () {
    $proof = app(SubmitPaymentProof::class)->handle($this->invoice, $this->payer->id, 'family', 'instapay', '400', '2026-10-05', 'IP-1', null,
        UploadedFile::fake()->create('receipt.jpg', 120, 'image/jpeg'));
    j5At('2026-10-07 06:00');
    expect(j5Follow())->toBe(0);

    app(RejectPaymentProof::class)->handle($proof->id, 'لم يصل المبلغ.', $this->accountant->id);
    expect(j5Follow())->toBe(1)->and(j5Heard($this->payer->id))->toContain('billing.due_tomorrow');
});

it('is quiet for a course paused, and stops for an invoice voided', function () {
    j5Pay();
    $extra = app(BillingApi::class)->sellExtraLessons($this->booked->enrollmentId, 2, $this->admin->id);
    app(PauseEnrollment::class)->handle($this->booked->enrollmentId, '2026-10-05', '2026-10-20', 'Travel', $this->admin->id);

    j5At(CarbonImmutable::parse(Invoice::query()->findOrFail($extra->id)->due_at)->subDay()->setTimezone('Asia/Riyadh')->setTime(10, 0)->utc()->toDateTimeString());
    expect(j5Follow())->toBe(0);
    j5At('2026-10-12 07:00');
    expect(j5Follow())->toBe(0)
        ->and(CollectionStep::query()->where('invoice_id', $extra->id)->pluck('step')->all())->toBe(['issued']);
});

it('stops for an invoice voided', function () {
    app(VoidInvoice::class)->handle($this->invoice->id, 'Wrong plan', $this->accountant->id);
    j5At('2026-10-07 06:00');
    expect(j5Follow())->toBe(0);
});

it('previews a renewal a few days ahead, once per block, with the plan, its price and the day', function () {
    j5Pay();
    // 8 lessons left; with a mark of 7 at three a week the invoice is three days away.
    Plan::query()->whereKey(Plan::query()->sole()->id)->update(['renew_at_sessions' => 7, 'sessions_per_week' => 3]);

    expect(j5Follow())->toBe(1)->and(j5Follow())->toBe(0);
    $preview = Notification::query()->where('template_key', 'billing.renewal_preview')->sole();
    expect($preview->user_id)->toBe($this->payer->id)
        ->and($preview->body)->toContain('8 حصص')->toContain('يصل الرصيد إلى 7 حصص تصدر')->toContain('400')->toContain('الخميس 8 أكتوبر')
        ->and($preview->action)->toMatchArray(['screen' => 'course_balance', 'id' => $this->booked->enrollmentId]);
});

it('tells the payer once a week before paid-for lessons lapse, again only for a new date', function () {
    j5Pay();
    // The block's minutes are all booked for its lessons: nothing of them can be lost. Two more bought, not booked, can.
    // (They lapse before the lessons still unpaid, so none draws on them.)
    $lot = j5Spare('2026-11-02 12:00');
    j5At('2026-10-25 12:00');
    expect(app(ExpireCredits::class)->notice())->toBe(0);
    j5At('2026-10-27 12:00');
    expect(app(ExpireCredits::class)->notice())->toBe(1)->and(app(ExpireCredits::class)->notice())->toBe(0);
    $told = Notification::query()->where('template_key', 'billing.credits_expiring')->sole();
    expect($told->user_id)->toBe($this->payer->id)
        ->and($told->body)->toContain('حصتان دون موعد')->toContain('الاثنين 2 نوفمبر')
        ->and($told->action)->toMatchArray(['screen' => 'course_balance', 'id' => $this->booked->enrollmentId]);

    // A date moved later (a pause, a holiday given back) is told again when it comes near.
    DB::table('credit_lots')->where('id', $lot)->update(['expires_at' => CarbonImmutable::parse('2026-11-03 08:00')]);
    j5At('2026-10-27 13:00');
    expect(app(ExpireCredits::class)->notice())->toBe(1);
});

it('gives each family course its balance: lessons left, expiry, the next invoice, by the look and only to its family', function () {
    // Before paying: the first block waits; its id only to the payer.
    $before = j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $this->payer)->assertOk()->json('data');
    expect($before['waiting_invoice'])->toMatchArray(['id' => $this->invoice->id, 'outstanding_minor' => 40000])
        ->and($before['next_invoice'])->toBeNull()
        ->and($before['auto_renew'])->toBeNull();

    j5Pay();
    $data = j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $this->payer)->assertOk()->json('data');
    expect($data)->toMatchArray(['status' => 'active', 'viewer_stage' => 'classic', 'lessons_left' => 8, 'is_payer' => true, 'renews' => true, 'waiting_invoice' => null])
        ->and($data['plan'])->toMatchArray(['session_minutes' => 30, 'sessions_per_week' => 2, 'sessions_per_block' => 8, 'renews_at_lessons_left' => 2, 'format' => 'private'])
        // Every minute is booked for a lesson: none can lapse.
        ->and($data['expiry'])->toBeNull()
        // 6 lessons to go at two a week: three weeks, about the block's price (the forecast's reckoning).
        ->and($data['next_invoice'])->toMatchArray(['at_lessons_left' => 2, 'lessons_until' => 6, 'amount_minor' => 40000, 'currency' => 'SAR'])
        ->and($data['next_invoice']['on']['local'])->toStartWith('2026-10-26')
        ->and(json_encode($data))->not->toContain('@');

    // The adult learner reads the same course in the classic look, without the payer's invoice id.
    $own = j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $this->student)->assertOk()->json('data');
    expect($own['is_payer'])->toBeFalse()->and($own['lessons_left'])->toBe(8);

    // Two lessons bought and not booked, lapsing in six days: a warning.
    j5Spare('2026-10-11 12:00');
    $late = j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $this->payer)->assertOk()->json('data');
    expect($late['lessons_left'])->toBe(10)
        ->and($late['lessons_text'])->toBe('بقيت 10 حصص')
        ->and($late['expiry'])->toMatchArray(['lessons' => 2, 'soon' => true])
        ->and($late['expiry']['text'])->toBe('تنتهي صلاحية حصتين في الأحد 11 أكتوبر. احجزوا موعديهما قبل ذلك حتى لا تضيعا.')
        ->and($late['next_invoice']['text'])->toContain('عندما تبقى حصتان،');

    // The learner in the kids' look reads lessons left only: no plan, no money.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2017-03-01'), null);
    app(GrantConsent::class)->handle($this->student->id, $this->payer->id, 'family');
    app()->forgetInstance(FamilyView::class);    // its looks are kept for a request
    $kid = j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $this->student)->assertOk()->json('data');
    expect($kid)->toMatchArray(['viewer_stage' => 'kids', 'lessons_left' => 10, 'plan' => null, 'expiry' => null, 'next_invoice' => null, 'waiting_invoice' => null, 'is_payer' => null]);

    // Another family: not found.
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $stranger)->assertNotFound();
});

it('never shows a passed day as the coming expiry: lapsed minutes are out of the balance before credits:expire sweeps them', function () {
    j5Pay();
    $account = DB::table('enrollments')->where('id', $this->booked->enrollmentId)->value('credit_account_id');
    $credits = app(CreditsApi::class);
    // A makeup lesson given back, lapsing Thursday 8 October; two bought lessons lapsing Sunday 11 October (before the lessons
    // still unpaid, so none draws on them); one more lasting to late November, which funds the next lesson waiting (booked: safe).
    $credits->grantMakeup($account, 30, 3, null, 'makeup-j5');
    j5Spare('2026-10-11 12:00');
    $credits->grant($account, LotSource::Purchase, null, 30, CarbonImmutable::parse('2026-11-20 12:00', 'UTC'), 'later-j5');
    $read = fn () => j5Api('GET', "family/courses/{$this->booked->enrollmentId}/balance", $this->payer)->assertOk()->json('data');

    $now = $read();
    expect($now)->toMatchArray(['lessons_left' => 11, 'lessons_text' => 'بقيت 11 حصة', 'makeup_lessons' => 1])
        ->and($now['expiry'])->toMatchArray(['lessons' => 1, 'soon' => true])
        ->and($now['expiry']['at']['local'])->toStartWith('2026-10-09')            // 23:59:59 UTC on the 8th: early on the 9th in Riyadh
        ->and($now['expiry']['text'])->toStartWith('تنتهي صلاحية حصة واحدة في');

    // The makeup lot's day has passed, nothing swept it: the makeup lesson is gone, and the next date is Sunday's.
    j5At('2026-10-09 07:00');
    $after = $read();
    expect($after)->toMatchArray(['lessons_left' => 11, 'makeup_lessons' => 0])
        ->and($after['expiry'])->toMatchArray(['lessons' => 2, 'soon' => true])
        ->and($after['expiry']['at']['local'])->toStartWith('2026-10-11');

    // Sunday's lessons lapsed too, still unswept: they are not counted, and with every minute left booked nothing can lapse:
    // no expiry at all, never Sunday's.
    j5At('2026-10-12 07:00');
    $unswept = $read();
    expect($unswept)->toMatchArray(['lessons_left' => 9, 'lessons_text' => 'بقيت 9 حصص', 'minutes_left' => 270, 'makeup_lessons' => 0, 'expiry' => null]);

    // The hourly sweep then agrees with what the family already read.
    expect(app(ExpireCredits::class)->handle())->toBe(2);
    expect($read())->toMatchArray(['lessons_left' => 9, 'makeup_lessons' => 0, 'minutes_left' => 270, 'expiry' => null]);

    // Two more bought, lapsing tomorrow before the next lesson (so none draws on them): the expiry is theirs, still to come.
    j5Spare('2026-10-13 06:00');
    $later = $read();
    expect($later['expiry'])->toMatchArray(['lessons' => 2, 'soon' => true])
        ->and($later['expiry']['at']['local'])->toStartWith('2026-10-13')
        ->and($later['expiry']['text'])->toStartWith('تنتهي صلاحية حصتين في الثلاثاء 13 أكتوبر.');
});

it('sends the payment link from the live board and the invoice, audited, once an hour, and says why not', function () {
    $sent = app(BillingApi::class)->sendPayLink($this->booked->enrollmentId, $this->admin->id);
    expect($sent)->toBe($this->invoice->number)
        ->and(Notification::query()->where('template_key', 'billing.pay_link')->pluck('user_id')->all())->toBe([$this->payer->id])
        ->and(DB::table('audit_logs')->where('action', 'billing.pay_link_sent')->count())->toBe(1);

    j5At('2026-10-05 07:30');
    expect(fn () => app(SendPayLink::class)->handle($this->invoice->id, $this->admin->id))
        ->toThrow(ValidationException::class, '30');
    j5At('2026-10-05 08:01');
    app(SendPayLink::class)->handle($this->invoice->id, $this->admin->id);

    // The board's button on the unpaid lesson.
    j5At('2026-10-05 09:05');
    $session = DB::table('sessions')->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->value('id');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(LiveBoard::class)->callAction(TestAction::make('payLink')->arguments(['session' => $session]))->assertHasNoActionErrors();
    expect(Notification::query()->where('template_key', 'billing.pay_link')->count())->toBe(3);

    j5Pay();
    j5At('2026-10-05 11:00');
    expect(fn () => app(SendPayLink::class)->handle($this->invoice->id, $this->admin->id))->toThrow(ValidationException::class, __('billing::collections.errors.paid'))
        ->and(fn () => app(BillingApi::class)->sendPayLink($this->booked->enrollmentId, $this->admin->id))->toThrow(ValidationException::class, __('billing::collections.errors.nothing_waiting'));
});

it('opens an invoice message on the family\'s invoice, and a balance message on their account', function () {
    $links = app(NotificationLinks::class);
    expect($links->resolve(['screen' => 'invoice', 'id' => $this->invoice->id], 'family'))->toContain('invoice='.$this->invoice->id)->toEndWith('#invoice-'.$this->invoice->id)
        ->and($links->resolve(['screen' => 'invoice', 'id' => $this->invoice->id], 'academy'))->toContain($this->invoice->id)
        ->and($links->resolve(['screen' => 'course_balance', 'id' => $this->booked->enrollmentId], 'family'))->not->toBeNull()
        ->and($links->personal(['screen' => 'invoice', 'id' => $this->invoice->id], $this->payer->id))->toContain('signature=');
});
