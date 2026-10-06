<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Curriculum\Models\Program;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    $this->enrollmentId = $this->booked->enrollmentId;
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->stranger = User::factory()->withRole(Role::Guardian)->create();

    $session = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    $this->report = SessionReport::query()->firstOrFail();
    $this->clearTenant();
});

/** Signs in through the API as this person; the guard is reset so one test can switch people. */
function signedIn(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => $user->email, 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

it('gives the teacher the report with today\'s plan filled in, and takes it back with homework', function () {
    signedIn($this->teacher)->getJson('/api/v1/teacher/reports')
        ->assertOk()->assertJsonPath('data.0.id', $this->report->id)->assertJsonPath('data.0.status', 'due')
        ->assertJsonPath('data.0.lesson_at.timezone', 'Africa/Cairo');

    signedIn($this->teacher)->getJson("/api/v1/teacher/reports/{$this->report->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.node_id', $this->lessons[0])
        ->assertJsonPath('data.items.0.reason', 'next')
        ->assertJsonPath('data.items.0.outcome', 'introduced')
        ->assertJsonCount(7, 'data.lesson_options')
        ->assertJsonPath('data.roadmap.percent', 0);

    signedIn($this->teacher)->putJson("/api/v1/teacher/reports/{$this->report->id}", [
        'engagement' => 5, 'overall' => 4, 'notes_for_student' => 'Great start', 'notes_internal' => 'Shy at first',
        'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered', 'score' => 5]],
        'homework' => ['title' => 'Recite the letters', 'accepts' => 'audio'],
    ])->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.items.0.outcome', 'mastered');

    signedIn($this->other)->getJson("/api/v1/teacher/reports/{$this->report->id}")->assertForbidden();
    signedIn($this->student)->getJson('/api/v1/teacher/reports')->assertForbidden();
});

it('shows the family the roadmap and the report, without the internal notes', function () {
    signedIn($this->teacher)->putJson("/api/v1/teacher/reports/{$this->report->id}", [
        'engagement' => 5, 'overall' => 4, 'notes_for_student' => 'Great start', 'notes_internal' => 'Shy at first',
        'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered', 'score' => 5]],
    ])->assertOk();

    signedIn($this->guardian)->getJson('/api/v1/learning/students')
        ->assertOk()->assertJsonPath('data.0.id', $this->student->id)
        ->assertJsonPath('data.0.enrollments.0.percent', 12)
        ->assertJsonPath('data.0.enrollments.0.current.id', $this->lessons[1]);

    signedIn($this->student)->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/roadmap")
        ->assertOk()
        ->assertJsonPath('data.levels.0.units.0.lessons.0.state', 'done')
        ->assertJsonPath('data.levels.0.units.0.lessons.1.state', 'here')
        ->assertJsonPath('data.levels.1.percent', 0);

    $reports = signedIn($this->guardian)->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/reports")->assertOk();
    $reports->assertJsonPath('data.0.notes_for_student', 'Great start')->assertJsonPath('data.0.items.0.title', 'Lesson 1');
    expect($reports->json('data.0'))->not->toHaveKey('notes_internal');

    signedIn($this->stranger)->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/roadmap")->assertNotFound();
});

it('lets the student hand in a recording, the teacher mark it, and only they download it', function () {
    signedIn($this->teacher)->putJson("/api/v1/teacher/reports/{$this->report->id}", [
        'engagement' => 5, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced', 'score' => 3]],
        'homework' => ['title' => 'Recite the letters', 'accepts' => 'audio'],
    ])->assertOk();
    $homework = signedIn($this->student)->getJson('/api/v1/learning/homework')->assertOk()->assertJsonPath('data.0.status', 'assigned')->json('data.0');

    signedIn($this->stranger)->post("/api/v1/learning/homework/{$homework['id']}/submissions", ['body' => 'hi'])->assertForbidden();
    $handed = signedIn($this->student)->post("/api/v1/learning/homework/{$homework['id']}/submissions", [
        'file' => UploadedFile::fake()->create('letters.m4a', 200, 'audio/mp4'),
    ])->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.submissions.0.file.is_audio', true);
    $url = $handed->json('data.submissions.0.file.url');

    signedIn($this->teacher)->getJson('/api/v1/teacher/homework')->assertOk()->assertJsonPath('data.0.id', $homework['id']);
    signedIn($this->teacher)->get($url)->assertOk()->assertDownload('letters.m4a');
    signedIn($this->stranger)->get($url)->assertNotFound();

    signedIn($this->teacher)->postJson("/api/v1/teacher/homework/{$homework['id']}/review", ['grade' => 4, 'feedback' => 'Clear makharij'])
        ->assertOk()->assertJsonPath('data.status', 'reviewed')->assertJsonPath('data.grade', 4);
    signedIn($this->guardian)->getJson('/api/v1/learning/homework')->assertJsonPath('data.0.feedback', 'Clear makharij');
});

it('answers a bad report as problem details', function () {
    signedIn($this->teacher)->putJson("/api/v1/teacher/reports/{$this->report->id}", ['engagement' => 9])
        ->assertStatus(422)->assertJsonPath('type', 'validation')
        ->assertJsonStructure(['errors' => ['engagement', 'overall', 'items']]);
});
