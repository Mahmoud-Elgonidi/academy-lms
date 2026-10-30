<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Tests\Support\EnrolledStudent;

/*
 * Find a teacher in steps (docs/architecture/02, section 2): the student and
 * the subject, then the times ("Available now" with suggestions and a map of
 * free teachers, or the student's own hours), then the teachers; and every
 * time nobody is found or a booking is refused, the reason in place.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function stepsPage(array $data = []): Testable
{
    return Livewire::test(FindTeacher::class)->fillForm($data + [
        'subject_id' => test()->quran, 'timezone' => 'Asia/Riyadh', 'start_date' => '2026-10-06', 'per_week' => 2,
    ]);
}

it('opens on the free times once the subject is chosen, and a suggestion fills the times, finds the teachers and picks its times', function () {
    Livewire::test(FindTeacher::class)
        ->assertSet('timesTab', 'free')
        ->assertSee(__('scheduling::find.free.wait.title'))
        ->assertDontSee(__('scheduling::find.free.suggestions'));

    $page = stepsPage()
        ->assertSee(__('scheduling::find.free.suggestions'))
        ->assertSee(__('scheduling::find.free.map'));
    $suggestion = $page->instance()->freeTimes['suggestions'][0];

    $page->call('useSuggestion', 0)
        ->assertSet('searched', true)
        ->assertSet('pickedTeacher', $suggestion['teacher_id'])
        ->assertDispatched('ft-results')
        ->assertSee(__('scheduling::find.hold', ['min' => 10]));

    $cells = $page->get('cells');
    expect(array_keys($cells))->toEqualCanonicalizing(collect($suggestion['times'])->pluck('weekday')->all())
        ->and($page->instance()->results)->not->toBeEmpty()
        ->and($page->get('picked'))->toHaveCount(2);

    $page->call('hold');
    expect($page->get('holdBatch'))->not->toBeNull();
});

it('switches between the free times and the student\'s own hours; hours sent along open on the student\'s', function () {
    stepsPage()
        ->call('showTimes', 'mine')->assertSet('timesTab', 'mine')->assertSee(__('scheduling::find.grid.title'))
        ->call('showTimes', 'free')->assertSet('timesTab', 'free')->assertSee(__('scheduling::find.free.map'));

    Livewire::withQueryParams(['subject' => $this->quran, 'days' => '2', 'from' => '19', 'to' => '21'])->test(FindTeacher::class)
        ->assertSet('timesTab', 'mine')->assertSet('searched', true);
});

it('says why nobody is found: no teacher of the subject, the filters, no free time, or only the hours chosen', function () {
    $fiqh = app(CatalogApi::class)->createSubject('فقه', 'Fiqh');
    stepsPage(['subject_id' => $fiqh])->assertSee(__('scheduling::find.reasons.no_teachers.title'));
    stepsPage(['subject_id' => $fiqh, 'mode' => 'trial'])->assertSee(__('scheduling::find.reasons.no_trial_teachers.title'));

    // Both teachers teach in Arabic: English leaves them out, and clearing the filters brings them back.
    stepsPage(['language' => 'en'])
        ->assertSee(__('scheduling::find.reasons.filters.title'))
        ->call('clearFilters')
        ->assertSet('data.language', null)
        ->assertDontSee(__('scheduling::find.reasons.filters.title'))
        ->assertSee(__('scheduling::find.free.suggestions'));

    // Monday 7 in the morning: nobody, though other times are free.
    stepsPage()->call('showTimes', 'mine')->call('toggleCell', 1, 7)->call('search')
        ->assertSee(__('scheduling::find.reasons.times.title'))
        ->assertSee(__('scheduling::find.reasons.see_free'));

    // The Cairo teacher's days are full; the Riyadh teacher is off one Tuesday and one Saturday: no time in all eight weeks.
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', maxDailyLessons: 1, subjects: [new TeacherSubjectData($this->quran)]));
    foreach (['2026-10-13', '2026-10-17'] as $day) {
        app(DecideTimeOff::class)->handle(app(RequestTimeOff::class)->handle($this->other->id, $day, $day, null, null, 'sick', null, $this->other->id), true, null, $this->admin->id);
    }
    stepsPage()
        ->assertSee(__('scheduling::find.reasons.no_time.title'))
        ->assertSee(__('scheduling::find.reasons.allow_one'))
        ->call('relax', 'coverage')
        ->assertSet('data.min_coverage', 7)
        ->assertDontSee(__('scheduling::find.reasons.no_time.title'))
        ->assertSee(__('scheduling::find.free.suggestions'));
});

it('says when a hold is refused or has run out, and holds again', function () {
    $page = stepsPage()->call('useSuggestion', 0);
    $teacher = $page->get('pickedTeacher');
    $times = collect($page->instance()->pickedCandidates())->flatMap(fn ($c) => $c->occurrences)->all();

    // Another coordinator holds the same times a moment earlier: they are no longer offered, and the bar says so.
    $other = User::factory()->withRole(Role::Scheduler)->create();
    app(PlaceBookingHold::class)->handle($teacher, null, $times, 30, $other->id);
    $page->call('hold')->assertSet('holdBatch', null)
        ->assertSet('refusal', __('scheduling::find.errors.hold_gone'))
        ->assertSee(__('scheduling::find.errors.hold_gone'));

    // Their hold runs out; ours runs out too, and is held again.
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(11));
    $page->call('hold')->assertSet('refusal', null);
    $first = $page->get('holdBatch');
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(11));
    expect($page->instance()->holdExpired())->toBeTrue();
    $page->assertSee(__('scheduling::find.expired.again'))
        ->call('holdAgain');
    expect($page->get('holdBatch'))->not->toBeNull()->not->toBe($first)
        ->and($page->instance()->holdExpired())->toBeFalse();
});

it('says a trial was refused because the hold ran out', function () {
    $page = stepsPage(['mode' => 'trial', 'student_id' => $this->student->id])->call('useSuggestion', 0)->call('hold');
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(11));

    $page->call('confirm', 'trial')
        ->assertSet('refusal', __('scheduling::find.errors.hold_expired'))
        ->assertNotified(__('scheduling::find.errors.hold_expired'));
});
