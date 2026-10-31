<?php

use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Groups\LateJoin;

/*
 * The pure rules of group lessons (docs/architecture/02, "الحصص الجماعية"):
 * seats and capacity, the plan a member joins on, who learns together, when
 * a group starts, and what a late join's missed lessons come to.
 */

it('keeps capacity within 2–20, the teacher\'s most and the seats taken', function () {
    expect(GroupRules::capacity(4, null))->toBe('no_groups')
        ->and(GroupRules::capacity(1, 5))->toBe('capacity_range')
        ->and(GroupRules::capacity(21, 25))->toBe('capacity_range')
        ->and(GroupRules::capacity(6, 5))->toBe('over_teacher')
        ->and(GroupRules::capacity(3, 5, 4))->toBe('below_members')
        ->and(GroupRules::capacity(4, 5, 4))->toBeNull()
        ->and(GroupRules::seat(4, 4))->toBe('full')
        ->and(GroupRules::seat(3, 4))->toBeNull()
        ->and(GroupRules::minToStart(5, 4))->toBe('min_to_start')
        ->and(GroupRules::minToStart(0, 4))->toBe('min_to_start')
        ->and(GroupRules::minToStart(null, 4))->toBeNull();
});

it('starts a group with its minimum, or its first student', function () {
    expect(GroupRules::startsWith(1, null))->toBeTrue()
        ->and(GroupRules::startsWith(1, 2))->toBeFalse()
        ->and(GroupRules::startsWith(2, 2))->toBeTrue();
});

it('takes a group plan of the group\'s shape', function () {
    expect(GroupRules::plan(false, 30, 2, 30, 2))->toBe('plan_not_group')
        ->and(GroupRules::plan(true, 45, 2, 30, 2))->toBe('plan_shape')
        ->and(GroupRules::plan(true, 30, 1, 30, 2))->toBe('plan_shape')
        ->and(GroupRules::plan(true, 30, 2, 30, 2))->toBeNull();
});

it('keeps age groups, and minors and adults, apart unless mixed groups are allowed', function () {
    expect(GroupRules::ages('kids', 'teen', true, [true], false))->toBe('age_band')
        ->and(GroupRules::ages(null, null, true, [false], false))->toBe('minors_adults')
        ->and(GroupRules::ages(null, null, false, [true, true], false))->toBe('minors_adults')
        ->and(GroupRules::ages('kids', null, true, [true], false))->toBeNull()
        ->and(GroupRules::ages('kids', 'adults', false, [true], true))->toBeNull()
        ->and(GroupRules::ages(null, 'adults', false, [], false))->toBeNull()
        ->and(GroupRules::level('a', 'b'))->toBe('level')
        ->and(GroupRules::level('a', null))->toBeNull()
        ->and(GroupRules::level(null, 'b'))->toBeNull();
});

it('counts the current block\'s missed lessons, lets the office lower them, and says what makes up for them', function () {
    expect(LateJoin::missed(3, 8))->toBe(3)
        ->and(LateJoin::missed(8, 8))->toBe(0)
        ->and(LateJoin::missed(11, 8))->toBe(3)
        ->and(LateJoin::missed(0, 8))->toBe(0)
        ->and(LateJoin::lessons(3, null))->toBe(3)
        ->and(LateJoin::lessons(3, 1))->toBe(1)
        ->and(LateJoin::lessons(3, 7))->toBe(3)
        ->and(LateJoin::lessons(3, -2))->toBe(0)
        ->and(LateJoin::choice(null, 'private', 2))->toBe('private')
        ->and(LateJoin::choice('group', 'discount', 2))->toBe('group')
        ->and(LateJoin::choice('discount', 'discount', 0))->toBe('none')
        ->and(LateJoin::choice('cash', 'discount', 2))->toBeNull()
        ->and(LateJoin::makeupMinutes('private', 3, 30))->toBe(90)
        ->and(LateJoin::makeupMinutes('discount', 3, 30))->toBe(0)
        ->and(LateJoin::discountLessons('discount', 3))->toBe(3)
        ->and(LateJoin::discountLessons('group', 3))->toBe(0);
});
