<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Models\CreditAccount;
use Modules\Enrollment\Models\Enrollment;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Paying for waiting lessons (docs/architecture/02, section 3, "حالة
 * التمويل"): oldest first, and the first lesson the minutes cannot cover
 * stops the run, so no later lesson is paid while an earlier one waits. The
 * fixture's sixteen lessons wait unpaid (30 minutes each); minutes are
 * granted here by hand, and funding is run by hand.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    Event::fake([CreditsGranted::class]);
    $this->account = Enrollment::query()->findOrFail($this->booked->enrollmentId)->credit_account_id;
    $this->grant = fn (int $minutes, LotSource $source = LotSource::Purchase) => app(CreditsApi::class)
        ->grant($this->account, $source, null, $minutes, CarbonImmutable::now()->addDays(42), 'test:'.$source->value.':'.$minutes.':'.microtime(true));
    $this->funding = fn () => Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->get()
        ->map(fn (Session $s) => $s->funding_status->value)->take(6)->all();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('pays for waiting lessons oldest first, once each, and stops at the first it cannot cover', function () {
    ($this->grant)(90);

    expect(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(3)
        ->and(($this->funding)())->toBe(['funded', 'funded', 'funded', 'unfunded', 'unfunded', 'unfunded'])
        ->and(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(0)
        ->and(CreditAccount::query()->findOrFail($this->account)->reserved_min)->toBe(90);

    // 45 more minutes: the fourth lesson, and 15 left that pay for nothing.
    ($this->grant)(45);
    expect(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(1)
        ->and(($this->funding)())->toBe(['funded', 'funded', 'funded', 'funded', 'unfunded', 'unfunded'])
        ->and(CreditAccount::query()->findOrFail($this->account)->available_min)->toBe(15);
});

it('leaves lessons that started or were cancelled, and never pays a regular lesson with makeup minutes', function () {
    ($this->grant)(60, LotSource::Makeup);
    expect(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(0);

    [$first, $second] = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->take(2)->get()->all();
    app(CancelSession::class)->handle($second, 'student', 'travel', null, $this->admin->id);
    CarbonImmutable::setTestNow($first->starts_at->addMinutes(5));
    ($this->grant)(60);

    expect(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(2)
        ->and(($this->funding)())->toBe(['unfunded', 'unfunded', 'funded', 'funded', 'unfunded', 'unfunded'])
        ->and($second->refresh()->funding_status)->toBe(FundingStatus::Unfunded);
});

it('pays nothing for a course with no credit account', function () {
    Enrollment::query()->whereKey($this->booked->enrollmentId)->update(['credit_account_id' => null]);

    expect(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(0);
});
