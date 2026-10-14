<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\RecordPayment;
use Modules\Identity\Models\User;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\Courses\EndEnrollment;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * What the apps and the portal read of a paused or ended course
 * (docs/architecture/02, section 3, "إيقاف الكورس وإنهاؤه"): its status and
 * days on the family's course endpoints (the balance, the journey), the
 * lessons cancelled with why, and a line above the lessons. Days are on the
 * academy's calendar (Cairo), whatever the viewer's clock. Monday 5 October 2026.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    app(PauseEnrollment::class)->handle($this->booked->enrollmentId, '2026-10-05', '2026-10-24', 'Travel: private words', $this->admin->id);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function courseApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();

    return $token;
}

it('says a course is paused, until a day on the academy\'s calendar, on the balance', function () {
    $course = $this->withToken(courseApp($this->guardian))->withHeaders(['Accept-Language' => 'en', 'X-Timezone' => 'America/New_York'])
        ->getJson('/api/v1/family/balance?learner_id='.$this->student->id)
        ->assertOk()->json('data.0');

    expect($course['status'])->toBe('paused')
        ->and($course['extra'])->toBeNull()
        ->and($course['ends_on'])->toBeNull()
        ->and($course['paused_from']['local'])->toStartWith('2026-10-05')
        ->and($course['paused_until'])->toMatchArray(['utc' => '2026-10-23T21:00:00Z', 'local' => '2026-10-24T00:00:00+03:00', 'timezone' => 'Africa/Cairo', 'label' => 'Sat 24 Oct']);
});

it('says it on the journey too, and above the lessons, whose cancelled ones say why, never the office\'s reason', function () {
    $token = courseApp($this->student);
    $journey = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/learning/journey/map')->assertOk()->json('data.courses.0');
    expect($journey['status'])->toBe('paused')
        ->and($journey['paused_until']['local'])->toStartWith('2026-10-24');

    $lessons = $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/lessons')->assertOk();
    expect($lessons->json('notices.0'))->toBe('Quran is paused until Saturday 24 October.')
        ->and($lessons->json('data.0.status'))->toBe('cancelled')
        ->and($lessons->json('data.0.cancelled_because'))->toBe('The course is paused; the minutes are back in the balance')
        ->and($lessons->json('data.0.can_cancel'))->toBeFalse()
        ->and($lessons->getContent())->not->toContain('private words');
});

it('says an ended course has ended, and a child in the kids\' look reads the same words, no money', function () {
    $this->actAsTenant($this->academy);
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2018-05-01'), LearnerStage::Kids);
    app(EndEnrollment::class)->handle($this->booked->enrollmentId, '2026-10-05', 'Moved: private words', $this->admin->id);
    $this->clearTenant();

    $balance = $this->withToken(courseApp($this->guardian))->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/balance?learner_id='.$this->student->id)->assertOk();
    expect($balance->json('data.0.status'))->toBe('ended')
        ->and($balance->json('data.0.ends_on.local'))->toStartWith('2026-10-05')
        ->and($balance->json('data.0.minutes'))->toBe(240);

    $kid = courseApp($this->student);
    $lessons = $this->withToken($kid)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/lessons')->assertOk();
    expect($lessons->json('notices'))->toContain('Quran has ended.')
        ->and(collect($lessons->json('data'))->every(fn (array $l) => $l['can_cancel'] === false))->toBeTrue()
        ->and($lessons->getContent())->not->toContain('private words')
        // An ended course is off the journey; the invoices are not a child's to read.
        ->and($this->withToken($kid)->getJson('/api/v1/learning/journey/map')->assertOk()->json('data.courses'))->toBe([]);
    $this->withToken($kid)->getJson('/api/v1/family/invoices')->assertForbidden();
});
