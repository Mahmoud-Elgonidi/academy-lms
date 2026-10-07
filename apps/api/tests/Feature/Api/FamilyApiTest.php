<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

/*
 * The family app's API (docs/architecture/03, "الـ API"): a guardian and a
 * student see the learner's lessons, cancel one under the policy (once, even
 * when the app retries), rate a lesson that took place, see lessons left and
 * the invoices they pay, and pay online. The fixture: Monday 5 October 2026,
 * a London student with a Cairo teacher, Tuesdays and Saturdays 18:00 Cairo,
 * an unpaid 400 SAR invoice paid by the guardian.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The app signed in as this person: a bearer token, and nothing left over from the last person. */
function familyApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();

    return $token;
}

function cairoLesson(string $day): Session
{
    test()->actAsTenant(test()->academy);
    $lesson = Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('starts_at', CarbonImmutable::parse($day.' 18:00', 'Africa/Cairo')->utc())->firstOrFail();
    test()->clearTenant();

    return $lesson;
}

it('lists the learners a person follows', function () {
    $this->withToken(familyApp($this->guardian))->getJson('/api/v1/family/learners')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->student->id)
        ->assertJsonPath('data.0.is_self', false)
        ->assertJsonPath('viewer_stage', 'classic');

    $this->withToken(familyApp($this->student))->getJson('/api/v1/family/learners')
        ->assertOk()->assertJsonPath('data.0.is_self', true);
});

it('shows a learner\'s lessons on the viewer\'s clock, and nobody else\'s', function () {
    $token = familyApp($this->guardian);

    $lessons = $this->withToken($token)->withHeaders(['X-Timezone' => 'Asia/Dubai', 'Accept-Language' => 'en'])
        ->getJson('/api/v1/family/lessons?learner_id='.$this->student->id)
        ->assertOk()
        ->assertJsonPath('learner.id', $this->student->id)
        ->assertJsonCount(8, 'data')
        ->json('data.0');

    expect($lessons['starts_at'])->toMatchArray(['utc' => '2026-10-06T15:00:00Z', 'local' => '2026-10-06T19:00:00+04:00', 'timezone' => 'Asia/Dubai'])
        ->and($lessons['with'])->toBe($this->teacher->name)
        ->and($lessons['can_cancel'])->toBeTrue()
        ->and($lessons['join'])->toMatchArray(['open' => false]);

    $this->withToken($token)->getJson('/api/v1/family/lessons?when=past')->assertOk()->assertJsonCount(0, 'data');

    $this->actAsTenant($this->academy);
    $stranger = User::factory()->withRole(Role::Student)->create();
    $this->clearTenant();
    $this->withToken($token)->getJson('/api/v1/family/lessons?learner_id='.$stranger->id)->assertNotFound();
});

it('cancels a lesson under the policy once, however often the app retries', function () {
    $token = familyApp($this->guardian);
    $lesson = cairoLesson('2026-10-13');

    $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson("/api/v1/lessons/{$lesson->id}/cancellation")
        ->assertOk()
        ->assertJsonPath('outcome', fn (string $text) => str_contains($text, 'goes back to your balance'))
        ->assertJsonPath('reasons.0.value', 'illness');

    $cancel = fn (array $body, string $key) => $this->withToken($token)->withHeaders(['Idempotency-Key' => $key])
        ->postJson("/api/v1/lessons/{$lesson->id}/cancel", $body);

    $first = $cancel(['reason' => 'travel'], 'k-1')->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_cancel', false);
    $again = $cancel(['reason' => 'travel'], 'k-1')->assertOk()->assertHeader('Idempotent-Replayed', 'true');
    expect($again->json())->toBe($first->json());

    $cancel(['reason' => 'illness'], 'k-1')->assertStatus(409)->assertJsonPath('type', 'conflict');
    // A new key is a new request: the lesson is no longer there to cancel.
    $cancel(['reason' => 'travel'], 'k-2')->assertForbidden();

    $this->actAsTenant($this->academy);
    expect($lesson->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and(DB::table('audit_logs')->where('action', 'like', '%cancel%')->count())->toBeLessThanOrEqual(1);
});

it('lets no child in the kids\' look cancel, and nobody cancel someone else\'s lesson', function () {
    $this->actAsTenant($this->academy);
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2018-03-01'), null);
    $this->clearTenant();
    $lesson = cairoLesson('2026-10-13');

    $kid = familyApp($this->student);
    $this->withToken($kid)->getJson('/api/v1/family/lessons')->assertOk()->assertJsonPath('data.0.can_cancel', false);
    $this->withToken($kid)->postJson("/api/v1/lessons/{$lesson->id}/cancel", ['reason' => 'travel'])->assertForbidden();

    $this->actAsTenant($this->academy);
    $otherParent = User::factory()->withRole(Role::Guardian)->create();
    $this->clearTenant();
    $this->withToken(familyApp($otherParent))->postJson("/api/v1/lessons/{$lesson->id}/cancel", ['reason' => 'travel'])->assertNotFound();
    $this->withToken(familyApp($otherParent))->postJson('/api/v1/lessons/not-a-lesson/cancel', ['reason' => 'travel'])->assertNotFound();
});

it('rates a lesson that took place, and says why another cannot be rated', function () {
    $this->actAsTenant($this->academy);
    $lesson = cairoLesson('2026-10-06');
    $this->actAsTenant($this->academy);
    CarbonImmutable::setTestNow($lesson->starts_at);
    foreach ([$this->student, $this->teacher] as $person) {
        app(RecordParticipantJoin::class)->handle($lesson->refresh(), $person->id, $lesson->starts_at);
    }
    foreach ([$this->student, $this->teacher] as $person) {
        app(RecordParticipantLeave::class)->handle($lesson->refresh(), $person->id, $lesson->ends_at);
    }
    CarbonImmutable::setTestNow($lesson->ends_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();
    $this->clearTenant();

    $token = familyApp($this->guardian);
    $this->withToken($token)->getJson("/api/v1/lessons/{$lesson->id}/rating")
        ->assertOk()->assertJsonPath('can_rate', true)->assertJsonPath('stars', null)->assertJsonPath('choices.good.0.value', 'clear');

    $this->withToken($token)->putJson("/api/v1/lessons/{$lesson->id}/rating", ['stars' => 5, 'tags' => ['clear', 'patient']])
        ->assertOk()->assertJsonPath('stars', 5)->assertJsonPath('tags', ['clear', 'patient']);
    $this->withToken($token)->putJson("/api/v1/lessons/{$lesson->id}/rating", ['stars' => 4])->assertOk()->assertJsonPath('stars', 4);

    $later = cairoLesson('2026-10-10');
    $this->withToken($token)->getJson("/api/v1/lessons/{$later->id}/rating")->assertOk()->assertJsonPath('can_rate', false)->assertJsonPath('why', fn ($why) => is_string($why));
    $this->withToken($token)->putJson("/api/v1/lessons/{$later->id}/rating", ['stars' => 5])->assertUnprocessable();
});

it('shows lessons left and the invoices this person pays, and opens a payment', function () {
    $token = familyApp($this->guardian);

    $this->withToken($token)->getJson('/api/v1/family/balance?learner_id='.$this->student->id)
        ->assertOk()->assertJsonPath('data.0.session_minutes', 30);

    $invoices = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/invoices')->assertOk();
    $invoice = $invoices->json('data.0');
    expect($invoice)->toMatchArray(['currency' => 'SAR', 'total_minor' => 40000, 'outstanding_minor' => 40000, 'status' => 'issued', 'pay_with' => []])
        ->and($invoice['document_url'])->toContain('/pay/invoice/'.$invoice['id'].'/document');

    $this->withToken($token)->postJson("/api/v1/family/invoices/{$invoice['id']}/checkout")
        ->assertUnprocessable()->assertJsonPath('errors.account_id.0', 'This invoice cannot be paid online now. Pay the academy the usual way.');

    $this->actAsTenant($this->academy);
    $account = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Card', 'mode' => 'test'], $this->admin->id);
    $this->clearTenant();

    $this->withToken($token)->getJson('/api/v1/family/invoices')->assertJsonPath('data.0.pay_with.0.account_id', $account->id);
    $checkout = $this->withToken($token)->withHeaders(['Idempotency-Key' => 'pay-1'])->postJson("/api/v1/family/invoices/{$invoice['id']}/checkout")
        ->assertCreated()->json();
    expect($checkout['url'])->toContain('/pay/demo/');
    $this->withToken($token)->withHeaders(['Idempotency-Key' => 'pay-1'])->postJson("/api/v1/family/invoices/{$invoice['id']}/checkout")
        ->assertCreated()->assertJsonPath('checkout_id', $checkout['checkout_id']);

    // The student does not pay: no money for them, and no paying someone else's invoice.
    $student = familyApp($this->student);
    $this->withToken($student)->postJson("/api/v1/family/invoices/{$invoice['id']}/checkout")->assertNotFound();
});
