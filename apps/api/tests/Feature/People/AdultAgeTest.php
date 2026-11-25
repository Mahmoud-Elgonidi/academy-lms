<?php

use Carbon\CarbonImmutable;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\People\PeopleService;
use Modules\Scheduling\Queries\Start\StartRules;
use Modules\Scheduling\Support\StartRefused;

/*
 * One adult age for everyone: from 18 a student is no longer a minor, and
 * the same 18 gives the plain (classic) look and the adults' age group, so
 * an 18-year-old reads money and starts a course on their own like any
 * adult (secondary is 15 to 17).
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));
    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function studentAged(int $years): User
{
    $student = User::factory()->withRole(Role::Student)->create();
    app(PeopleApi::class)->saveStudentProfile($student->id, CarbonImmutable::now()->subYears($years)->subDays(3), null);
    app()->forgetInstance(FamilyView::class);

    return $student;
}

it('makes 18 the adult age in every bound', function () {
    expect(AgeGroup::ADULT_AGE)->toBe(18)->and(PeopleService::ADULT_AGE)->toBe(18)
        ->and(AgeGroup::forAge(17))->toBe(AgeGroup::Teen)->and(AgeGroup::forAge(18))->toBe(AgeGroup::Adults)
        ->and(LearnerStage::forAge(17))->toBe(LearnerStage::Teen)->and(LearnerStage::forAge(18))->toBe(LearnerStage::Classic);
});

it('treats an 18-year-old as an adult and a 17-year-old as a minor', function () {
    $adult = studentAged(18);
    $minor = studentAged(17);
    $people = app(PeopleApi::class);

    expect($people->studentProfile($adult->id)->age)->toBe(18)
        ->and($people->studentProfile($adult->id)->stage)->toBe(LearnerStage::Classic)
        ->and($people->isMinor($adult->id))->toBeFalse()
        ->and($people->studentProfile($minor->id)->stage)->toBe(LearnerStage::Teen)
        ->and($people->isMinor($minor->id))->toBeTrue();

    // An 18-year-old starts a course on their own; at 17 it is the family's to start.
    app(StartRules::class)->assertCanStart($adult->id);
    expect(fn () => app(StartRules::class)->assertCanStart($minor->id))->toThrow(StartRefused::class);
});
