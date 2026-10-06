<?php

use App\Filament\Family\FamilyHome;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Filament\Family\BalanceWidget;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Filament\Family\FamilyHomework;
use Modules\Learning\Filament\Family\FamilyProgress;
use Modules\Learning\Filament\Family\FamilyReports;
use Modules\Learning\Filament\Family\JourneyWidget;
use Modules\Learning\Filament\Family\LearningHomeWidget;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\JourneyQuery;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Resources\Enrollments\ViewEnrollment;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * A student's own area takes the look of their stage (children, middle
 * school, secondary school) from their age or the academy's choice: the
 * roadmap and where they are on it on the home page, in that stage's
 * spirit, built from what really happened. Guardians and adults keep the
 * classic look.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();                      // Mon 5 Oct 2026; Lesson 1–5 in level 1, 6–7 in level 2
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The student's age today (5 October 2026), for their look. */
function aged(User $student, ?int $years, ?LearnerStage $chosen = null): void
{
    app(PeopleApi::class)->saveStudentProfile($student->id, $years === null ? null : CarbonImmutable::parse('2026-06-01')->subYears($years), $chosen);
    app()->forgetInstance(FamilyView::class);   // its answers are kept for the request
}

function inFamilyArea(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('family');
    app()->forgetInstance(FamilyView::class);   // its answers are kept for the request
}

/** Ends the n-th lesson (time order) as the sweeper would; with items, the teacher reports it. */
function taught(int $n, array $items = [], string $outcome = 'completed'): SessionReport
{
    $session = Session::query()->where('enrollment_id', test()->booked->enrollmentId)->orderBy('starts_at')->skip($n)->firstOrFail();
    $session->update(['status' => $outcome]);
    event(new SessionFinalized($session->academy_id, $session->id, $outcome, []));
    $report = SessionReport::query()->where('session_id', $session->id)->firstOrFail();

    return $items === [] ? $report : app(SubmitSessionReport::class)->handle($report, test()->teacher->id, [
        'engagement' => 4, 'overall' => 4,
        'items' => array_map(fn ($i) => ['node_id' => test()->lessons[$i[0]], 'outcome' => $i[1], 'score' => $i[2] ?? null], $items),
    ]);
}

it('picks the look from the age unless the academy chose one, for students only', function () {
    expect(array_map(fn ($age) => LearnerStage::forAge($age)->value, [null, 6, 11, 12, 14, 15, 18, 19, 40]))
        ->toBe(['classic', 'kids', 'kids', 'middle', 'middle', 'teen', 'teen', 'classic', 'classic']);

    aged($this->student, 9);
    $profile = app(PeopleApi::class)->studentProfile($this->student->id);
    expect($profile->age)->toBe(9)->and($profile->stage)->toBe(LearnerStage::Kids)->and($profile->chosenStage)->toBeNull()
        ->and(app(FamilyView::class)->stage($this->student->id))->toBe(LearnerStage::Kids)
        ->and(app(FamilyView::class)->stage($this->guardian->id))->toBe(LearnerStage::Classic);   // a guardian keeps the classic look

    aged($this->student, 9, LearnerStage::Teen);
    expect(app(FamilyView::class)->stage($this->student->id))->toBe(LearnerStage::Teen);

    expect(fn () => app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::now()->addDay(), null))->toThrow(ValidationException::class);
});

it('sets the birth date and the look from the student account in the back office', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    Livewire::test(ViewEnrollment::class, ['record' => $this->booked->enrollmentId])
        ->callAction('studentLook', data: ['birth_date' => '2013-06-01', 'stage' => 'auto'])
        ->assertHasNoActionErrors();
    expect(app(PeopleApi::class)->studentProfile($this->student->id))
        ->birthDate->toDateString()->toBe('2013-06-01')
        ->stage->toBe(LearnerStage::Middle);

    Livewire::test(ViewEnrollment::class, ['record' => $this->booked->enrollmentId])
        ->callAction('studentLook', data: ['birth_date' => '2013-06-01', 'stage' => 'classic']);
    expect(app(PeopleApi::class)->studentProfile($this->student->id)->stage)->toBe(LearnerStage::Classic);
});

it('counts stars, points, the streak and badges from what really happened', function () {
    taught(0, [[0, 'mastered']]);
    taught(1, [[1, 'mastered'], [2, 'introduced', 4]]);
    taught(2, [[2, 'practiced', 5]]);                 // Lesson 3 needs two scores of 4+: mastered now
    $journey = app(JourneyQuery::class)->for($this->student->id);

    expect($journey)->toMatchArray(['stars' => 3, 'attended' => 3, 'streak' => 3, 'on_time' => 0, 'points' => 3 * 100 + 3 * 20])
        ->and($journey['rank'])->toMatchArray(['key' => 'explorer', 'next' => 800, 'next_key' => 'star', 'progress' => 12])
        ->and(collect($journey['badges'])->where('earned', true)->pluck('key')->all())->toBe(['first_star', 'streak_3', 'halfway']);   // 4 of 8 weight points

    $course = $journey['courses'][0];
    expect(array_column($course['worlds'], 'state'))->toBe(['here', 'ahead'])
        ->and($course['level'])->toMatchArray(['index' => 1, 'count' => 2, 'done' => 3])
        ->and(array_column($course['next'], 'title'))->toBe(['Lesson 4', 'Lesson 5', 'Lesson 6']);

    taught(3, outcome: 'student_absent');             // the streak starts again after an absence
    expect(app(JourneyQuery::class)->for($this->student->id)['streak'])->toBe(0);
});

it('shows a child the adventure map with their stars and where they are, and three tabs', function () {
    aged($this->student, 9);
    taught(0, [[0, 'mastered']]);
    taught(1, [[1, 'mastered']]);
    inFamilyArea($this->student);

    Livewire::test(FamilyHome::class)->assertOk()
        ->assertSeeLivewire(JourneyWidget::class)
        ->assertDontSeeLivewire(LearningHomeWidget::class)
        ->assertDontSeeLivewire(BalanceWidget::class);
    Livewire::test(JourneyWidget::class)
        ->assertSee(trans_choice('learning::journey.kids.stars', 2, ['n' => 2]))
        ->assertSee(__('learning::journey.kids.here'))
        ->assertSee('Lesson 3')
        ->assertSee(trans_choice('learning::journey.kids.left', 3, ['n' => 3]))    // 2 of the 5 stops on the first island
        ->assertSee(__('learning::journey.kids.next_island', ['title' => 'Level 2']));

    Livewire::test(FamilyLessons::class)->assertActionHidden('cancel');   // their guardian cancels for them

    expect(FamilyProgress::shouldRegisterNavigation())->toBeFalse()
        ->and(FamilyReports::shouldRegisterNavigation())->toBeFalse()
        ->and(FamilyBilling::shouldRegisterNavigation())->toBeFalse()
        ->and([FamilyHome::getNavigationLabel(), FamilyLessons::getNavigationLabel(), FamilyHomework::getNavigationLabel()])
        ->toBe([__('family.home.kids'), __('scheduling::family.nav.kids'), __('learning::family.homework.nav.kids')]);

    // The shell: the stage's own font and shapes, and the tab bar on phones.
    $this->clearTenant();
    $this->get('http://noor.lms.test/my')->assertOk()
        ->assertSee('data-stage="kids"', false)
        ->assertSee('Baloo Bhaijaan 2 Variable', false)
        ->assertSee('class="lms-tabs"', false)
        // Motion: the shared layer, the star count that counts up, and a cheer for new stars (the widget is drawn with the page).
        ->assertSee('window.LmsMotion', false)
        ->assertSee('data-count="2"', false)
        ->assertSee('data-celebrate="stars:'.$this->student->id.'"', false);
});

it('gives middle school worlds, a rank with the points to the next, and badges', function () {
    aged($this->student, 13);
    taught(0, [[0, 'mastered']]);
    inFamilyArea($this->student);

    Livewire::test(JourneyWidget::class)
        ->assertSee(__('learning::journey.ranks.beginner'))
        ->assertSee(__('learning::journey.middle.to_next', ['n' => 180, 'rank' => __('learning::journey.ranks.explorer')]))   // 120 of 300
        ->assertSee(__('learning::journey.middle.world', ['n' => 1, 'of' => 2]))
        ->assertSee(__('learning::journey.badges.first_star')[0])
        ->assertDontSee(__('learning::journey.middle.world', ['n' => 2, 'of' => 2]));
    Livewire::test(FamilyProgress::class)->assertOk()
        ->assertSee(__('learning::journey.middle.world', ['n' => 2, 'of' => 2]));   // every world on the worlds page

    expect(FamilyProgress::getNavigationLabel())->toBe(__('learning::family.progress.nav.middle'))
        ->and(FamilyReports::shouldRegisterNavigation())->toBeTrue()
        ->and(FamilyBilling::shouldRegisterNavigation())->toBeFalse();
});

it('gives a teenager a plan: milestones, the next lessons and what to review', function () {
    aged($this->student, 16);
    taught(0, [[0, 'mastered']]);
    taught(1, [[1, 'mastered']]);
    taught(2, [[0, 'needs_review', 2], [2, 'introduced', 4]]);
    inFamilyArea($this->student);

    Livewire::test(JourneyWidget::class)
        ->assertSee(__('learning::journey.teen.milestones'))
        ->assertSee(__('learning::journey.teen.lessons', ['done' => 2, 'total' => 7]))
        ->assertSeeInOrder([__('learning::journey.teen.up_next'), 'Lesson 3', 'Lesson 4', 'Lesson 5'])
        ->assertSeeInOrder([__('learning::journey.teen.review'), 'Lesson 1'])
        ->assertSee(__('learning::journey.teen.goal'));
    expect(FamilyProgress::getNavigationLabel())->toBe(__('learning::family.progress.nav.teen'));
});

it('keeps guardians on the classic look, with money shown to whoever pays', function () {
    aged($this->student, 9);
    inFamilyArea($this->guardian);

    Livewire::test(FamilyHome::class)->assertOk()
        ->assertDontSeeLivewire(JourneyWidget::class)
        ->assertSeeLivewire(LearningHomeWidget::class)
        ->assertSeeLivewire(BalanceWidget::class);
    expect(FamilyProgress::shouldRegisterNavigation())->toBeTrue()
        ->and(FamilyBilling::shouldRegisterNavigation())->toBeTrue()
        ->and(FamilyLessons::getNavigationLabel())->toBe(__('scheduling::family.nav.classic'));

    $this->clearTenant();
    $this->get('http://noor.lms.test/my')->assertOk()
        ->assertDontSee('data-stage=', false)
        ->assertDontSee('class="lms-tabs"', false);
});
