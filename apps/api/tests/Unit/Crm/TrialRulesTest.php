<?php

use Carbon\CarbonImmutable;
use Modules\Crm\Domain\NextTrial;
use Modules\Crm\Domain\NextTrialRules;
use Modules\Crm\Domain\TrialOfferRules;

/*
 * After the trial (docs/architecture/02, section 27, J2), the pure rules:
 * the offer's bounds, end and one reminder; and one more free trial from an
 * earlier one, in the order a family would want to hear why not.
 */

function nextTrial(array $change = []): NextTrial
{
    return new NextTrial(...$change + [
        'kind' => 'second', 'familyLook' => true, 'familyBooks' => true, 'secondTrialOn' => true, 'assessed' => true,
        'startedAt' => CarbonImmutable::parse('2026-10-06 16:00'), 'now' => CarbonImmutable::parse('2026-10-08 10:00'),
        'studying' => false, 'openTrial' => false, 'openForNumber' => 0, 'secondUsed' => false, 'studentMissed' => 0, 'hadTrial' => true,
    ]);
}

it('keeps the offer between 5% and 50%, open from a day to two weeks, ending its hours after the report', function () {
    expect(TrialOfferRules::invalid(10, 72))->toBe([])
        ->and(TrialOfferRules::invalid(5, 24))->toBe([])
        ->and(TrialOfferRules::invalid(50, 336))->toBe([])
        ->and(TrialOfferRules::invalid(4, 72))->toBe(['percent'])
        ->and(TrialOfferRules::invalid(51, 23))->toBe(['percent', 'hours'])
        ->and(TrialOfferRules::invalid(10, 337))->toBe(['hours'])
        ->and(TrialOfferRules::endsAt(CarbonImmutable::parse('2026-10-06 19:00', 'Asia/Riyadh'), 72)->toIso8601ZuluString())->toBe('2026-10-09T16:00:00Z');
});

it('reminds of the offer once in its last day: not within 12 hours of the report, not in its last 2 hours', function () {
    $sent = CarbonImmutable::parse('2026-10-06 16:00');
    $ends = $sent->addHours(72);
    expect(TrialOfferRules::remindDue($sent, $ends, $ends->subHours(25)))->toBeFalse()
        ->and(TrialOfferRules::remindDue($sent, $ends, $ends->subHours(24)))->toBeTrue()
        ->and(TrialOfferRules::remindDue($sent, $ends, $ends->subHours(3)))->toBeTrue()
        ->and(TrialOfferRules::remindDue($sent, $ends, $ends->subHours(2)))->toBeFalse();

    // An offer of a day is not chased the moment it is made: from 12 hours after the report.
    $day = $sent->addHours(24);
    expect(TrialOfferRules::remindDue($sent, $day, $sent->addHours(11)))->toBeFalse()
        ->and(TrialOfferRules::remindDue($sent, $day, $sent->addHours(12)))->toBeTrue();
});

it('allows a second trial while the academy gives it, once, within 14 days, before the learner studies the subject', function () {
    expect(NextTrialRules::whyNot(nextTrial()))->toBeNull()
        ->and(NextTrialRules::whyNot(nextTrial(['familyLook' => false])))->toBe('family_only')
        ->and(NextTrialRules::whyNot(nextTrial(['secondTrialOn' => false])))->toBe('second_off')
        ->and(NextTrialRules::shown(nextTrial(['secondTrialOn' => false])))->toBeFalse()
        ->and(NextTrialRules::whyNot(nextTrial(['assessed' => false])))->toBe('not_assessed')
        ->and(NextTrialRules::whyNot(nextTrial(['studying' => true])))->toBe('already_student')
        ->and(NextTrialRules::whyNot(nextTrial(['secondUsed' => true])))->toBe('second_used')
        ->and(NextTrialRules::whyNot(nextTrial(['now' => CarbonImmutable::parse('2026-10-20 16:00')])))->toBeNull()
        ->and(NextTrialRules::whyNot(nextTrial(['now' => CarbonImmutable::parse('2026-10-20 16:01')])))->toBe('second_late')
        // J1's rules as well: one trial to come per learner and subject, three per number, while families book.
        ->and(NextTrialRules::whyNot(nextTrial(['openTrial' => true])))->toBe('trial_open')
        ->and(NextTrialRules::whyNot(nextTrial(['openForNumber' => 3])))->toBe('too_many_open')
        ->and(NextTrialRules::whyNot(nextTrial(['familyBooks' => false])))->toBe('staff_books')
        ->and(NextTrialRules::whyNot(nextTrial(['hasNumber' => false])))->toBe('no_number')
        // The reason a family can act on comes first: studying beats an expired guarantee.
        ->and(NextTrialRules::whyNot(nextTrial(['studying' => true, 'now' => CarbonImmutable::parse('2026-11-30')])))->toBe('already_student');
});

it('books again after a missed trial until one took place or the learner missed two; the teacher\'s absence never counts', function () {
    $rebook = ['kind' => 'rebook', 'hadTrial' => false, 'assessed' => false, 'secondTrialOn' => false];
    expect(NextTrialRules::whyNot(nextTrial($rebook)))->toBeNull()
        ->and(NextTrialRules::shown(nextTrial($rebook)))->toBeTrue()
        ->and(NextTrialRules::whyNot(nextTrial($rebook + ['studentMissed' => 1])))->toBeNull()
        ->and(NextTrialRules::whyNot(nextTrial($rebook + ['studentMissed' => 2])))->toBe('missed_twice')
        ->and(NextTrialRules::whyNot(nextTrial(['hadTrial' => true] + $rebook)))->toBe('had_trial')
        // No 14-day limit and no "second used" for booking again.
        ->and(NextTrialRules::whyNot(nextTrial($rebook + ['secondUsed' => true, 'now' => CarbonImmutable::parse('2026-12-01')])))->toBeNull();
});
