<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * One lesson, a reservation for each student in it (a group lesson): each
 * from their own account, settled on its own, keyed by it; the one-to-one
 * calls keep working on a lesson's single reservation and refuse one with
 * several. Makeup credit may be kept for private or group makeup lessons.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    Event::fake([CreditsGranted::class]);
    $this->actAsTenant($this->createAcademy());
    $this->credits = app(CreditsApi::class);
    [$this->a, $this->b] = array_map(function () {
        $student = User::factory()->withRole(Role::Student)->create();
        $account = $this->credits->openAccount($student->id, 'enrollment', (string) Str::uuid());
        $this->credits->grant($account, LotSource::Purchase, null, 120, CarbonImmutable::now()->addWeeks(6), "block:{$account}");

        return $account;
    }, [1, 2]);
    $this->lesson = (string) Str::uuid();
    $this->at = CarbonImmutable::now()->addDay();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('holds one reservation per student in a lesson and settles each on its own', function () {
    $key = fn (string $acc, string $what) => "session:{$this->lesson}:account:{$acc}:{$what}";
    expect($this->credits->reserveFor($this->lesson, $this->a, 30, $this->at, $key($this->a, 'reserve')))->toBeTrue()
        ->and($this->credits->reserveFor($this->lesson, $this->b, 30, $this->at, $key($this->b, 'reserve')))->toBeTrue()
        ->and($this->credits->reserveFor($this->lesson, $this->b, 30, $this->at, $key($this->b, 'reserve')))->toBeTrue()   // the same key twice: once
        ->and(CreditReservation::query()->where('session_id', $this->lesson)->count())->toBe(2);

    $this->credits->consumeFor($this->lesson, $this->a, $key($this->a, 'consume'), 'session_completed');
    $this->credits->releaseFor($this->lesson, $this->b, ReleaseMode::Makeup, $key($this->b, 'release'), null, 'teacher_absent');

    expect($this->credits->settledAsFor($this->lesson, $this->a))->toBe('consumed')
        ->and($this->credits->settledAsFor($this->lesson, $this->b))->toBe('makeup')
        ->and(CreditAccount::query()->find($this->a)->consumed_min)->toBe(30)
        ->and(CreditAccount::query()->find($this->b)->consumed_min)->toBe(0)
        ->and($this->credits->balance($this->b)->makeupMin)->toBe(30);

    // Decided again for one student: their credit moves, the other's stays.
    expect($this->credits->correctFor($this->lesson, $this->a, 'makeup'))->toBe(0)
        ->and($this->credits->settledAsFor($this->lesson, $this->a))->toBe('makeup')
        ->and($this->credits->settledAsFor($this->lesson, $this->b))->toBe('makeup')
        ->and(CreditAccount::query()->find($this->a)->consumed_min)->toBe(0);
});

it('keeps the one-to-one calls for a lesson with one reservation, and refuses them on a lesson with several', function () {
    $one = (string) Str::uuid();
    expect($this->credits->reserve($one, $this->a, 30, $this->at, "session:{$one}:reserve"))->toBeTrue();
    $this->credits->consume($one, "session:{$one}:consume");
    expect($this->credits->settledAs($one))->toBe('consumed');

    $this->credits->reserveFor($this->lesson, $this->a, 30, $this->at, 'r:a');
    $this->credits->reserveFor($this->lesson, $this->b, 30, $this->at, 'r:b');
    expect(fn () => $this->credits->consume($this->lesson, 'c:any'))->toThrow(LogicException::class);
});

it('reserves a seat afresh after it was given back, under a new key', function () {
    $this->credits->reserveFor($this->lesson, $this->a, 30, $this->at, 'seat:1');
    $this->credits->releaseFor($this->lesson, $this->a, ReleaseMode::Refund, 'seat:1:left');
    expect($this->credits->isReservedFor($this->lesson, $this->a))->toBeFalse()
        ->and($this->credits->reserveFor($this->lesson, $this->a, 30, $this->at, 'seat:1'))->toBeFalse()     // the old key: nothing again
        ->and($this->credits->reserveFor($this->lesson, $this->a, 30, $this->at, 'seat:2'))->toBeTrue()
        ->and(CreditAccount::query()->find($this->a)->reserved_min)->toBe(30)
        ->and(CreditAccount::query()->find($this->a)->available_min)->toBe(90);
});

it('pays a makeup lesson only from makeup credit kept for its kind, or for either', function () {
    $this->credits->grantMakeup($this->a, 30, 30, null, 'late:private', useFor: 'private');
    $this->credits->grantMakeup($this->b, 30, 30, null, 'late:group', useFor: 'group');
    $lot = CreditLot::query()->where('account_id', $this->a)->where('source', 'makeup')->first();
    expect($lot->use_for)->toBe('private')->and($lot->expires_at->toDateString())->toBe('2026-11-04');

    $s = fn () => (string) Str::uuid();
    expect($this->credits->reserveFor($s(), $this->a, 30, $this->at, 'a:group', forMakeup: true, makeupFor: 'group'))->toBeFalse()
        ->and($this->credits->reserveFor($s(), $this->b, 30, $this->at, 'b:private', forMakeup: true, makeupFor: 'private'))->toBeFalse()
        ->and($this->credits->reserveFor($s(), $this->a, 30, $this->at, 'a:private', forMakeup: true))->toBeTrue()
        ->and($this->credits->reserveFor($s(), $this->b, 30, $this->at, 'b:group', forMakeup: true, makeupFor: 'group'))->toBeTrue();

    // A lesson's own makeup credit (kept for either) pays both kinds.
    $this->credits->reserveFor($this->lesson, $this->a, 30, $this->at, 'x');
    $this->credits->releaseFor($this->lesson, $this->a, ReleaseMode::Makeup, 'x:release');
    expect($this->credits->reserveFor($s(), $this->a, 30, $this->at, 'a:group2', forMakeup: true, makeupFor: 'group'))->toBeTrue()
        ->and(fn () => $this->credits->grantMakeup($this->a, 30, 30, null, 'bad', useFor: 'class'))->toThrow(InvalidArgumentException::class);
});
