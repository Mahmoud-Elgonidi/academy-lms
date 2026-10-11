<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Credits\Actions\ExpireCredits;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\Events\CreditBalanceLow;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * The credit ledger on its own (docs/architecture/02, section 3): minutes
 * granted in lots, set aside per lesson, used, given back or turned into
 * makeup credit, lapsing and extended, and lessons decided again. Whatever
 * happens, no total goes below nothing and the entries add up to the totals.
 * The fixture: a block of 240 minutes (eight 30-minute lessons, booked
 * ahead) whose renewal mark is two lessons (60 minutes).
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy());
    $this->student = User::factory()->withRole(Role::Student)->create();
    $this->credits = app(CreditsApi::class);
    $this->account = $this->credits->openAccount($this->student->id, 'enrollment', (string) Str::uuid(), 60);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A paid block of minutes, valid six weeks. */
function ledgerGrant(int $minutes = 240, string $key = 'block-1'): string
{
    return test()->credits->grant(test()->account, LotSource::Purchase, null, $minutes, CarbonImmutable::now()->addWeeks(6), $key);
}

/** @return list<string> lessons of 30 minutes, one a day from tomorrow, each with its minutes set aside */
function ledgerBook(int $count, bool $makeup = false): array
{
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $id = (string) Str::uuid();
        expect(test()->credits->reserve($id, test()->account, 30, CarbonImmutable::now()->addDays($i + 1), "session:{$id}:reserve", $makeup))->toBeTrue();
        $ids[] = $id;
    }

    return $ids;
}

function ledgerUse(string $session): void
{
    test()->credits->consume($session, "session:{$session}:consume");
}

/**
 * The totals agree with the lots, the held reservations and every entry
 * written, and none of them is below nothing.
 */
function ledgerHolds(string $accountId): CreditAccount
{
    $account = CreditAccount::query()->findOrFail($accountId);
    $effect = [
        'grant' => [1, 0, 0], 'reserve' => [-1, 1, 0], 'consume' => [0, -1, 1], 'release' => [1, -1, 0],
        'expire' => [-1, 0, 0], 'extend' => [0, 0, 0], 'restore' => [1, 0, -1], 'reclaim' => [-1, 0, 1],
    ];
    $sum = [0, 0, 0];
    foreach (CreditEntry::query()->where('account_id', $accountId)->get() as $entry) {
        expect($effect)->toHaveKey($entry->type)
            ->and($entry->available_after)->toBeGreaterThanOrEqual(0)
            ->and($entry->reserved_after)->toBeGreaterThanOrEqual(0);
        foreach ($effect[$entry->type] as $i => $sign) {
            $sum[$i] += $sign * $entry->minutes;
        }
    }

    expect([$account->available_min, $account->reserved_min, $account->consumed_min])->toBe($sum)
        ->and($account->available_min)->toBeGreaterThanOrEqual(0)
        ->and($account->consumed_min)->toBeGreaterThanOrEqual(0)
        ->and($account->available_min)->toBe((int) CreditLot::query()->where('account_id', $accountId)->sum('remaining_min'))
        ->and($account->reserved_min)->toBe((int) CreditReservation::query()->where('account_id', $accountId)->where('status', 'held')->sum('minutes'));

    return $account;
}

it('sets minutes aside per lesson, uses them once, and gives back what a cancelled lesson held', function () {
    ledgerGrant();
    [$first, $second, $third] = ledgerBook(3);

    // A lesson reserved twice, or used twice by the sweeper and a late decision, moves once.
    expect($this->credits->reserve($first, $this->account, 30, CarbonImmutable::now()->addDay(), 'another-key'))->toBeTrue();
    ledgerUse($first);
    $this->credits->consume($first, 'late-decision');
    // Once used, it cannot be given back as well.
    $this->credits->release($first, ReleaseMode::Refund, "session:{$first}:release");
    $this->credits->release($second, ReleaseMode::Refund, "session:{$second}:release");
    $this->credits->release($second, ReleaseMode::Makeup, 'again');
    // Nothing set aside: nothing to use or give back.
    $this->credits->consume((string) Str::uuid(), 'nothing');

    $account = ledgerHolds($this->account);
    expect([$account->available_min, $account->reserved_min, $account->consumed_min])->toBe([180, 30, 30])
        ->and(CreditEntry::query()->where('type', 'consume')->count())->toBe(1)
        ->and(CreditEntry::query()->where('type', 'release')->count())->toBe(1)
        ->and($this->credits->settledAs($first))->toBe('consumed')
        ->and($this->credits->settledAs($second))->toBe('refund')
        ->and($this->credits->settledAs($third))->toBeNull();
});

it('never takes more than there is: a lesson the lots cannot cover takes nothing', function () {
    ledgerGrant(60);
    ledgerBook(2);

    $short = (string) Str::uuid();
    expect($this->credits->reserve($short, $this->account, 30, CarbonImmutable::now()->addDays(3), "session:{$short}:reserve"))->toBeFalse()
        // A lesson after the lot lapses cannot use it either.
        ->and($this->credits->reserve($short, $this->account, 30, CarbonImmutable::now()->addWeeks(7), "session:{$short}:reserve-late"))->toBeFalse()
        ->and($this->credits->isReserved($short))->toBeFalse();
    expect(ledgerHolds($this->account)->available_min)->toBe(0);
});

it('lets minutes lapse once, and pushes back the dates of lots not yet lapsed', function () {
    ledgerGrant(60, 'old');
    $this->credits->grant($this->account, LotSource::Purchase, null, 120, CarbonImmutable::now()->addWeeks(10), 'new');

    // A holiday gives back three days to what has not lapsed, once per key.
    expect($this->credits->extend($this->account, 3, 'holiday-1'))->toBe(2)
        ->and($this->credits->extend($this->account, 3, 'holiday-1'))->toBe(0);
    expect(CreditLot::query()->orderBy('granted_min')->pluck('expires_at')->map->toDateString()->all())
        ->toBe([CarbonImmutable::now()->addWeeks(6)->addDays(3)->toDateString(), CarbonImmutable::now()->addWeeks(10)->addDays(3)->toDateString()]);

    expect(app(ExpireCredits::class)->handle(CarbonImmutable::now()->addWeeks(6)->addDays(2)))->toBe(0)
        ->and(app(ExpireCredits::class)->handle(CarbonImmutable::now()->addWeeks(6)->addDays(4)))->toBe(1)
        ->and(app(ExpireCredits::class)->handle(CarbonImmutable::now()->addWeeks(6)->addDays(5)))->toBe(0);

    // A lapsed lot stays lapsed: a later extension leaves it alone.
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addWeeks(7));
    expect($this->credits->extend($this->account, 5, 'holiday-2'))->toBe(1);
    $account = ledgerHolds($this->account);
    expect($account->available_min)->toBe(120)
        ->and(CreditEntry::query()->where('type', 'expire')->sole()->minutes)->toBe(60);
});

it('raises the renewal mark when a lesson turned into makeup credit brings the paid minutes down to it', function () {
    Event::fake([CreditBalanceLow::class]);
    ledgerGrant();
    $lessons = ledgerBook(8);
    foreach (array_slice($lessons, 0, 5) as $taught) {
        ledgerUse($taught);
    }
    Event::assertNotDispatched(CreditBalanceLow::class);       // three lessons left

    // The teacher misses the sixth: its minutes become makeup credit, which renewal leaves aside. Two paid lessons are left.
    $this->credits->release($lessons[5], ReleaseMode::Makeup, "session:{$lessons[5]}:release");

    Event::assertDispatched(CreditBalanceLow::class, fn (CreditBalanceLow $e) => $e->accountId === $this->account && $e->remainingMin === 60);
    ledgerUse($lessons[6]);
    ledgerUse($lessons[7]);
    Event::assertDispatchedTimes(CreditBalanceLow::class, 1);
    ledgerHolds($this->account);
});

it('counts a booked makeup lesson as owed, not as paid minutes left', function () {
    Event::fake([CreditBalanceLow::class]);
    ledgerGrant();
    $lessons = ledgerBook(8);
    $this->credits->release($lessons[0], ReleaseMode::Makeup, "session:{$lessons[0]}:release");
    foreach (array_slice($lessons, 1, 4) as $taught) {
        ledgerUse($taught);
    }
    expect($this->credits->balance($this->account)->renewableMin())->toBe(90);

    // The makeup lesson is booked from its credit: still three paid lessons left, not four.
    [$makeup] = ledgerBook(1, makeup: true);
    expect($this->credits->balance($this->account)->renewableMin())->toBe(90);
    Event::assertNotDispatched(CreditBalanceLow::class);

    ledgerUse($lessons[5]);
    Event::assertDispatchedTimes(CreditBalanceLow::class, 1);

    // Taking the makeup lesson uses no paid minutes, so it raises nothing more.
    ledgerUse($makeup);
    ledgerUse($lessons[6]);
    Event::assertDispatchedTimes(CreditBalanceLow::class, 1);
    expect($this->credits->balance($this->account)->renewableMin())->toBe(30);
    ledgerHolds($this->account);
});

it('moves a lesson decided again between used and makeup, giving back only what it took', function () {
    ledgerGrant();
    [$lesson, $next] = ledgerBook(2);
    ledgerUse($lesson);

    // Reviewed: the teacher was absent after all. Then reviewed again: it took place. And again: absent.
    expect($this->credits->correct($lesson, 'makeup'))->toBe(0);
    expect($this->credits->settledAs($lesson))->toBe('makeup');
    expect($this->credits->correct($lesson, 'consumed'))->toBe(0);
    expect($this->credits->settledAs($lesson))->toBe('consumed')
        ->and((int) CreditLot::query()->where('source', 'makeup')->sum('remaining_min'))->toBe(0);
    expect($this->credits->correct($lesson, 'makeup'))->toBe(0)
        ->and((int) CreditLot::query()->where('source', 'makeup')->sum('remaining_min'))->toBe(30);

    // Took place after all: the makeup credit it was given (its newest) is taken back in full.
    expect($this->credits->correct($lesson, 'consumed'))->toBe(0)
        ->and((int) CreditLot::query()->where('source', 'makeup')->sum('remaining_min'))->toBe(0);
    // Asking for what already stands changes nothing.
    expect($this->credits->correct($lesson, 'consumed'))->toBe(0)
        ->and($this->credits->correct($next, 'makeup'))->toBe(0);

    $account = ledgerHolds($this->account);
    expect([$account->available_min, $account->reserved_min, $account->consumed_min])->toBe([180, 30, 30]);
});

it('keeps the makeup a family already booked, without a second one, however often the lesson is decided again', function () {
    ledgerGrant();
    [$missed, $other] = ledgerBook(2);

    // The teacher missed it; the family books the makeup lesson with its credit.
    $this->credits->release($missed, ReleaseMode::Makeup, "session:{$missed}:release");
    [$makeup] = ledgerBook(1, makeup: true);

    // Reviewed: it took place. The makeup is already booked, so nothing can be taken back.
    expect($this->credits->correct($missed, 'consumed'))->toBe(30);
    // Reviewed again: the teacher was absent. The makeup they booked is the one this lesson owed: no second one.
    expect($this->credits->correct($missed, 'makeup'))->toBe(0)
        ->and($this->credits->settledAs($missed))->toBe('makeup')
        ->and((int) CreditLot::query()->where('source', 'makeup')->sum('remaining_min'))->toBe(0);

    ledgerUse($makeup);
    ledgerUse($other);
    $account = ledgerHolds($this->account);
    expect([$account->available_min, $account->reserved_min, $account->consumed_min])->toBe([180, 0, 60]);
});
