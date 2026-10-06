<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Filament\Programs\ManagePrograms;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Filament\EnrollmentLearning;
use Modules\Learning\Filament\Pages\ReportsOversight;
use Modules\Learning\Filament\Teacher\HomeworkToMark;
use Modules\Learning\Filament\Teacher\MyReports;
use Modules\Learning\Filament\Teacher\WriteReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ProgressQuery;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Resources\Enrollments\ViewEnrollment;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    $this->program = $this->publishQuranProgram($this->quran);
    $session = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    $this->report = SessionReport::query()->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('builds a program: adds a lesson to a unit and lists it with its students', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    $unit = Node::query()->where('kind', 'unit')->orderBy('ordinal')->firstOrFail();

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee('Lesson 1')
        ->callAction('addChild', data: ['title_ar' => 'درس جديد', 'title_en' => 'New lesson', 'weight' => 1, 'est_sessions' => 1, 'mastery_rule' => 'mastered_once'], arguments: ['node' => $unit->id, 'kind' => 'lesson'])
        ->assertHasNoActionErrors()
        ->assertSee('New lesson')
        ->assertActionHidden('deleteNode', arguments: ['node' => $unit->id])   // published: no deleting
        ->assertDontSeeHtml("mountAction('deleteNode'");

    Livewire::test(ManagePrograms::class)->assertCanSeeTableRecords([$this->program]);
});

it('puts the student on a program from the account page and shows the roadmap there', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    Livewire::test(ViewEnrollment::class, ['record' => $this->booked->enrollmentId])->assertSeeLivewire('learning.enrollment');
    Livewire::test(EnrollmentLearning::class, ['enrollmentId' => $this->booked->enrollmentId])
        ->callAction('attach', data: ['program_id' => $this->program->id])
        ->assertSee('Lesson 1')
        ->assertSee('0%');

    expect(app(ProgressQuery::class)->position($this->booked->enrollmentId))->not->toBeNull();
});

it('lets the teacher write the report from today\'s plan in the teacher area', function () {
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->program->id);
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');

    Livewire::test(MyReports::class)->assertCanSeeTableRecords([$this->report]);

    $page = Livewire::test(WriteReport::class, ['report' => $this->report->id]);
    $items = $page->get('data.items');
    expect(array_values($items)[0]['node_id'])->toBe($this->lessons[0]);

    $key = array_key_first($items);
    $page->set("data.items.{$key}.outcome", 'mastered')->set("data.items.{$key}.score", 5)
        ->set('data.engagement', 5)->set('data.overall', 4)
        ->set('data.notes_for_student', 'Lovely start')
        ->set('data.homework.title', 'Recite lesson 1')->set('data.homework.accepts', 'audio')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(MyReports::getUrl());

    expect($this->report->refresh()->status)->toBe('submitted')
        ->and(app(ProgressQuery::class)->position($this->booked->enrollmentId)->percent)->toBe(12)
        ->and(Homework::query()->value('title'))->toBe('Recite lesson 1');
});

it('shows the teacher form errors next to the fields', function () {
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');

    Livewire::test(WriteReport::class, ['report' => $this->report->id])
        ->set('data.engagement', null)
        ->call('submit')
        ->assertHasErrors(['data.engagement']);
});

it('lets the teacher mark homework, and a supervisor review the report', function () {
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->program->id);
    app(SubmitSessionReport::class)->handle($this->report, $this->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced', 'score' => 3]],
        'homework' => ['title' => 'Recite', 'accepts' => 'audio'],
    ]);
    $homework = Homework::query()->firstOrFail();
    app(SubmitHomework::class)->handle($homework, $this->student->id, null, UploadedFile::fake()->create('r.m4a', 100, 'audio/mp4'));

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(HomeworkToMark::class)->assertCanSeeTableRecords([$homework])
        ->callTableAction('mark', $homework, data: ['grade' => 4, 'feedback' => 'Good'])
        ->assertHasNoTableActionErrors();
    expect($homework->refresh()->status)->toBe('reviewed');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(ReportsOversight::class)->set('tab', 'submitted')
        ->assertCanSeeTableRecords([$this->report])
        ->callTableAction('review', $this->report, data: ['note' => 'Add a score per lesson next time'])
        ->assertHasNoTableActionErrors();
    expect($this->report->refresh()->status)->toBe('reviewed');
});

it('keeps teachers out of the back office and staff out of the teacher area', function () {
    $this->clearTenant();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/admin')->assertForbidden();
    $this->actingAs($this->admin)->get('http://noor.lms.test/teach/reports')->assertForbidden();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/teach/reports')->assertOk()->assertSee(__('learning::teacher.reports.write'));
});
