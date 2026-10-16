<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Texts\QuranText;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\Hifz\MarkMemorized;
use Modules\Learning\Filament\Pages\HifzOverview;
use Modules\Learning\Filament\Pages\HifzStudent;
use Modules\Learning\Models\RecitationMistake;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * The academy's hifz in the back office (docs/architecture/02, section 22):
 * each course a hifz map follows, its student's map in numbers, the most in
 * need first, and a student's map and mistakes notebook, for the academy's
 * supervisors. The fixture's text is made up in Tanzil's shape, never the
 * Quran.
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, Program::query()->value('id'));
    DB::table('curriculum_nodes')->where('id', $this->lessons[0])->update(['text_source' => json_encode(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3])]);
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    Filament::setCurrentPanel('academy');
});

it('lists the courses a hifz map follows with their students\' maps in numbers, for supervisors', function () {
    app(MarkMemorized::class)->handle($this->student->id, [1, 2], 'weak');
    $lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    RecitationMistake::query()->create(['student_id' => $this->student->id, 'enrollment_id' => $this->booked->enrollmentId, 'session_id' => $lesson->id,
        'teacher_id' => $this->teacher->id, 'surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'wrong']);
    $this->actingAs($this->supervisor);
    expect(HifzOverview::canAccess())->toBeTrue()->and(HifzOverview::shouldRegisterNavigation())->toBeTrue();

    $page = Livewire::test(HifzOverview::class)->assertOk()->assertSee($this->student->name)->assertSee($this->teacher->name);
    expect($page->instance()->board['rows'])->toHaveCount(1)
        ->and($page->instance()->board['rows'][0])->toMatchArray(['enrollment' => $this->booked->enrollmentId, 'memorized' => 2, 'learning' => 0, 'words' => 1])
        ->and($page->instance()->board['teachers'])->toBe([$this->teacher->id => $this->teacher->name]);
    $page->set('search', 'لا أحد بهذا الاسم')->assertSee(__('learning::hifz.board.empty'))
        ->set('search', '')->set('teacher', $this->other->id)->assertSee(__('learning::hifz.board.empty'));

    // The student's own page: the map, the reviews and the notebook, read only.
    $this->clearTenant();
    $this->actingAs($this->supervisor)->get("http://noor.lms.test/admin/hifz/{$this->booked->enrollmentId}")->assertOk()
        ->assertSee(__('learning::hifz.title'))->assertSee(__('learning::mushaf.book'))->assertSee(__('learning::hifz.sources.teacher'))
        ->assertSee($this->student->name);
});

it('keeps it to the academy\'s supervisors, and to courses a map follows', function () {
    $this->actingAs($this->accountant);
    expect(HifzOverview::canAccess())->toBeFalse()->and(HifzStudent::canAccess())->toBeFalse()->and(HifzOverview::shouldRegisterNavigation())->toBeFalse();

    // A course that reads no Quran, its student with no pages: not on the board, and no page of its own.
    DB::table('curriculum_nodes')->update(['text_source' => null]);
    $this->actingAs($this->supervisor);
    expect(Livewire::test(HifzOverview::class)->instance()->board['rows'])->toBe([]);
    $this->clearTenant();
    $this->actingAs($this->supervisor)->get("http://noor.lms.test/admin/hifz/{$this->booked->enrollmentId}")->assertNotFound();
    $this->actingAs($this->supervisor)->get('http://noor.lms.test/admin/hifz/not-a-course')->assertNotFound();
});

it('says the Quran\'s text is not on the platform yet, and stays out of the menu', function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/none')));
    app()->forgetInstance(CurriculumApi::class);
    $this->actingAs($this->supervisor);
    expect(HifzOverview::shouldRegisterNavigation())->toBeFalse();
    Livewire::test(HifzOverview::class)->assertSee(__('learning::hifz.board.no_text'));
});
