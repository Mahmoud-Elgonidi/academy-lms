<?php

use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\GradeScale;
use Modules\Quran\Domain\Grading\Grading;
use Modules\Quran\Domain\Grading\MistakeTypes;
use Modules\Quran\Domain\Hifz\Scheduler;

/*
 * A recitation's grade from its mistakes (docs/architecture/02, section 26):
 * each kind times its weight, over the pages recited (at least half a page),
 * on the academy's scale; the teacher may give another with a note.
 */

$weights = MistakeTypes::weights(MistakeTypes::DEFAULTS);

it('grades by the weight of mistakes a page', function () use ($weights) {
    $scale = GradeScale::DEFAULT;
    expect(Grading::grade([], $weights, $scale, 1.0))->toBe(['grade' => 'excellent', 'penalty' => 0.0])
        ->and(Grading::grade(['tajweed' => 2], $weights, $scale, 1.0)['grade'])->toBe('excellent')          // 0.5
        ->and(Grading::grade(['hesitation' => 3], $weights, $scale, 1.0)['grade'])->toBe('very_good')      // 1.5
        ->and(Grading::grade(['wrong' => 3], $weights, $scale, 1.0)['grade'])->toBe('good')                // 3
        ->and(Grading::grade(['wrong' => 4, 'hesitation' => 2], $weights, $scale, 1.0)['grade'])->toBe('acceptable')   // 5
        ->and(Grading::grade(['wrong' => 6], $weights, $scale, 1.0))->toBe(['grade' => 'repeat', 'penalty' => 6.0]);
    // Two pages carry twice the mistakes for the same grade.
    expect(Grading::grade(['wrong' => 6], $weights, $scale, 2.0)['grade'])->toBe('good');
    // A few verses count as half a page: one wrong word is 2 a page, not 10.
    expect(Grading::grade(['wrong' => 1], $weights, $scale, 0.1))->toBe(['grade' => 'good', 'penalty' => 2.0]);
});

it('weighs the academy\'s own kinds, nothing for a kind it no longer has, and follows its scale', function () use ($weights) {
    $own = $weights + ['waqf' => 0.75];
    expect(Grading::grade(['waqf' => 2], $own, GradeScale::DEFAULT, 1.0)['grade'])->toBe('very_good')
        ->and(Grading::grade(['gone' => 9], $weights, GradeScale::DEFAULT, 1.0)['grade'])->toBe('excellent');
    $strict = ['excellent' => 0.0, 'very_good' => 0.5, 'good' => 1.0, 'acceptable' => 2.0];
    expect(Grading::grade(['hesitation' => 1], $weights, $strict, 1.0)['grade'])->toBe('very_good')
        ->and(Grading::grade(['wrong' => 3], $weights, $strict, 1.0)['grade'])->toBe('repeat');
});

it('takes the teacher\'s own grade only with a note', function () {
    expect(Grading::decide('good', null, null))->toBe(['grade' => 'good', 'overridden' => false, 'error' => null])
        ->and(Grading::decide('good', 'good', null)['overridden'])->toBeFalse()
        ->and(Grading::decide('good', 'excellent', ' ')['error'])->toBe('note')
        ->and(Grading::decide('good', 'perfect', 'why')['error'])->toBe('grade')
        ->and(Grading::decide('repeat', 'acceptable', 'Nervous, knew it'))->toBe(['grade' => 'acceptable', 'overridden' => true, 'error' => null]);
});

it('orders the grades, says which reach a threshold, and how the hifz map counts them', function () {
    expect(Grades::meets('excellent', 'good'))->toBeTrue()
        ->and(Grades::meets('good', 'good'))->toBeTrue()
        ->and(Grades::meets('acceptable', 'good'))->toBeFalse()
        ->and(Grades::meets('repeat', 'acceptable'))->toBeFalse()
        ->and(array_map(fn ($g) => Grades::hifzGrade($g), Grades::ORDER))->toBe([Scheduler::EASY, Scheduler::GOOD, Scheduler::GOOD, Scheduler::HARD, Scheduler::AGAIN]);
    expect(fn () => Grades::rank('perfect'))->toThrow(InvalidArgumentException::class);
});

it('keeps wrong, hesitation and tajweed, and up to twelve kinds with names and weights', function () {
    $own = [...MistakeTypes::DEFAULTS, ['key' => 'waqf', 'weight' => 0.5, 'name_ar' => 'الوقف', 'name_en' => 'Stopping']];
    expect(MistakeTypes::errors($own))->toBe([])
        ->and(MistakeTypes::normalize($own)[3])->toBe(['key' => 'waqf', 'weight' => 0.5, 'name_ar' => 'الوقف', 'name_en' => 'Stopping'])
        ->and(MistakeTypes::errors(array_slice($own, 1)))->toBe(['type_built_in'])
        ->and(MistakeTypes::errors([...MistakeTypes::DEFAULTS, ['key' => 'waqf', 'weight' => 1]]))->toBe(['type_name'])
        ->and(MistakeTypes::errors([...MistakeTypes::DEFAULTS, ['key' => 'Waqf!', 'weight' => 1]]))->toContain('type_key')
        ->and(MistakeTypes::errors([...MistakeTypes::DEFAULTS, ['key' => 'wrong', 'weight' => 1]]))->toBe(['type_key'])
        ->and(MistakeTypes::errors([['key' => 'wrong', 'weight' => 6], ...array_slice(MistakeTypes::DEFAULTS, 1)]))->toBe(['type_weight'])
        ->and(MistakeTypes::errors(array_fill(0, 13, MistakeTypes::DEFAULTS[0])))->toBe(['types'])
        ->and(MistakeTypes::errors('wrong'))->toBe(['types']);
});

it('keeps a scale whose bounds rise', function () {
    expect(GradeScale::errors(GradeScale::DEFAULT))->toBe([])
        ->and(GradeScale::errors(['excellent' => 1, 'very_good' => 1, 'good' => 3, 'acceptable' => 5]))->toBe(['scale'])
        ->and(GradeScale::errors(['excellent' => 0, 'very_good' => 1, 'good' => 3]))->toBe(['scale'])
        ->and(GradeScale::errors(['excellent' => 0, 'very_good' => 1, 'good' => 3, 'acceptable' => 51]))->toBe(['scale'])
        ->and(GradeScale::errors(GradeScale::DEFAULT + ['repeat' => 9]))->toBe(['scale'])
        ->and(GradeScale::normalize(['excellent' => '0', 'very_good' => 1, 'good' => 2, 'acceptable' => 3]))->toBe(['excellent' => 0.0, 'very_good' => 1.0, 'good' => 2.0, 'acceptable' => 3.0]);
});
