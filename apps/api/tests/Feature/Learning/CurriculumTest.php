<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\MoveNode;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Tests\Support\QuranProgram;

uses(QuranProgram::class);

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->subject = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
});

it('keeps levels on top, units in levels and lessons in units or levels', function () {
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'برنامج', 'name_en' => 'Program']);
    $version = $program->workingVersion();
    $save = app(SaveNode::class);

    $level = $save->handle($version, null, ['kind' => 'level', 'title_ar' => 'م', 'title_en' => 'L']);
    expect(fn () => $save->handle($version, null, ['kind' => 'unit', 'title_ar' => 'و', 'title_en' => 'U']))->toThrow(ValidationException::class);
    $unit = $save->handle($version, null, ['kind' => 'unit', 'parent_id' => $level->id, 'title_ar' => 'و', 'title_en' => 'U']);
    expect(fn () => $save->handle($version, null, ['kind' => 'level', 'parent_id' => $unit->id, 'title_ar' => 'م', 'title_en' => 'L']))->toThrow(ValidationException::class);

    $a = $save->handle($version, null, ['kind' => 'lesson', 'parent_id' => $unit->id, 'title_ar' => 'أ', 'title_en' => 'A']);
    $b = $save->handle($version, null, ['kind' => 'lesson', 'parent_id' => $unit->id, 'title_ar' => 'ب', 'title_en' => 'B']);
    app(MoveNode::class)->handle($b, 'up');

    expect(array_map(fn ($n) => $n->titleEn, app(CurriculumApi::class)->tree($version->id)->lessons()))->toBe(['B', 'A']);
});

it('publishes a version with lessons, then refuses deleting from it', function () {
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'برنامج', 'name_en' => 'Program']);
    $version = $program->workingVersion();
    expect(fn () => app(PublishVersion::class)->handle($version, null))->toThrow(ValidationException::class)
        ->and(app(CurriculumApi::class)->programsFor($this->subject))->toBe([]);

    $level = app(SaveNode::class)->handle($version, null, ['kind' => 'level', 'title_ar' => 'م', 'title_en' => 'L']);
    $lesson = app(SaveNode::class)->handle($version, null, ['kind' => 'lesson', 'parent_id' => $level->id, 'title_ar' => 'د', 'title_en' => 'Lesson']);
    app(PublishVersion::class)->handle($version, null);

    expect(fn () => app(DeleteNode::class)->handle($lesson))->toThrow(ValidationException::class)
        ->and(Node::query()->count())->toBe(2)
        ->and(app(CurriculumApi::class)->programsFor($this->subject))->toHaveCount(1)
        ->and(app(CurriculumApi::class)->programsFor($this->subject)[0]->lessons)->toBe(1);
});

it('puts a new enrolment on the subject\'s program when it has exactly one', function () {
    $this->publishQuranProgram($this->subject);
    $student = User::factory()->withRole(Role::Student)->create();
    $teacher = User::factory()->withRole(Role::Teacher)->create();

    $id = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $this->subject, $teacher->id, CarbonImmutable::parse('2026-10-05')));
    $enrollment = app(EnrollmentApi::class)->find($id);

    expect($enrollment->programId)->not->toBeNull()
        ->and($enrollment->curriculumVersionId)->toBe(app(CurriculumApi::class)->programsFor($this->subject)[0]->publishedVersionId);

    $other = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => $other, 'name_ar' => 'ب', 'name_en' => 'B']);
    expect(fn () => app(EnrollmentApi::class)->attachCurriculum($id, $program->id))->toThrow(ValidationException::class);
});
