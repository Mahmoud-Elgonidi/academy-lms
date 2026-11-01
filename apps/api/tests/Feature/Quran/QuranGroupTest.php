<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Actions\Plans\StartQuranPlan;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\GroupLessons;
use Tests\Support\QuranLessons;

uses(GroupLessons::class, QuranLessons::class);

/*
 * A group of a Quran subject (docs/architecture/02, section 26): one plan
 * for the group, so one portion a lesson and one position, and a grade for
 * each student. The position moves when most of the students graded passed
 * the new part; each student's hifz map hears their own grades. A member's
 * course follows the group's plan and takes none of its own. The fixture's
 * text is made up, never the Quran (surah 1: 3 verses, surah 2: 2).
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->setUpGroups();
    $this->makeQuranSubject($this->quran);
    $this->group = $this->group(capacity: 4);
    $this->members = [$this->student(), $this->student(), $this->student()];
    $this->joined = array_map(fn (User $s) => $this->join($this->group, $s), $this->members);
    $this->program = app(SaveQuranProgram::class)->handle(null, [
        'name' => 'Fixture group', 'track' => 'hifz', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 2, 'scope_to_verse' => 2,
        'direction' => 'forward', 'unit' => 'verses', 'new_amount' => 2, 'near_portions' => 0, 'far_pages' => 0, 'pass_grade' => 'good', 'exam_stops' => 'none',
    ], $this->admin->id);
    $this->lessons = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->pluck('id')->all();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** Each member's new part: their mistakes (wrong words), in seat order. */
function groupNew(array $members, array $wrong): array
{
    return ['students' => array_map(fn (User $s, int $n) => ['student_id' => $s->id, 'parts' => ['new' => ['mistakes' => ['wrong' => $n]]]], $members, $wrong)];
}

it('shares one plan and position in the group, a grade for each student, and moves on when most passed', function () {
    $plan = app(StartQuranPlan::class)->forGroup($this->group->id, $this->program->id, [], $this->admin->id);
    expect($plan->position()?->toArray())->toBe(['surah' => 1, 'verse' => 1])
        // A member's course follows the group's plan and takes none of its own.
        ->and(app(Plans::class)->forEnrollment($this->joined[0]->enrollmentId)?->id)->toBe($plan->id);
    expect(fn () => app(StartQuranPlan::class)->handle($this->joined[0]->enrollmentId, $this->program->id))->toThrow(ValidationException::class);

    // Lesson one (1:1–2, page 1): two of three pass; the group moves on.
    CarbonImmutable::setTestNow(CarbonImmutable::parse(Session::query()->findOrFail($this->lessons[0])->starts_at)->addMinutes(20));
    $results = app(RecordQuranLesson::class)->handle($this->lessons[0], $this->teacher->id, groupNew($this->members, [0, 1, 9]));
    expect(array_map(fn (QuranLessonResult $r) => $r->new_grade, $results))->toBe(['excellent', 'very_good', 'repeat'])
        ->and(array_map(fn (QuranLessonResult $r) => $r->enrollment_id, $results))->toBe(array_map(fn ($j) => $j->enrollmentId, $this->joined))
        ->and($results[0]->advanced)->toBeTrue()
        ->and($plan->fresh()->position()?->toArray())->toBe(['surah' => 1, 'verse' => 3]);
    // Each student's map hears their own grade: memorized for those who passed, again for the one who did not.
    $states = fn () => array_map(fn (User $s) => HifzPage::query()->where('student_id', $s->id)->where('page', 1)->value('state'), $this->members);
    expect($states())->toBe(['memorized', 'memorized', 'learning']);

    // Lesson two (1:3): one of three passes; the position stays for everyone.
    CarbonImmutable::setTestNow(CarbonImmutable::parse(Session::query()->findOrFail($this->lessons[1])->starts_at)->addMinutes(20));
    $results = app(RecordQuranLesson::class)->handle($this->lessons[1], $this->teacher->id, groupNew($this->members, [0, 8, 9]));
    expect($results[0]->advanced)->toBeFalse()->and($plan->fresh()->position()?->toArray())->toBe(['surah' => 1, 'verse' => 3]);

    // Only the group's students, each once, and a group lesson names them.
    CarbonImmutable::setTestNow(CarbonImmutable::parse(Session::query()->findOrFail($this->lessons[2])->starts_at)->addMinutes(20));
    $outsider = $this->student();
    expect(fn () => app(RecordQuranLesson::class)->handle($this->lessons[2], $this->teacher->id, groupNew([$outsider], [0])))->toThrow(ValidationException::class)
        ->and(fn () => app(RecordQuranLesson::class)->handle($this->lessons[2], $this->teacher->id, ['parts' => ['new' => []]]))->toThrow(ValidationException::class)
        ->and(fn () => app(RecordQuranLesson::class)->handle($this->lessons[2], $this->teacher->id, groupNew([$this->members[0], $this->members[0]], [0, 0])))->toThrow(ValidationException::class);
});
