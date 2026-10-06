<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Program;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\MarkMissedHomework;
use Modules\Learning\Actions\MarkOverdueReports;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\ReviewSessionReport;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Contracts\Events\HomeworkAssigned;
use Modules\Learning\Contracts\Events\NodeMastered;
use Modules\Learning\Contracts\Events\SessionReportOverdue;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ProgressQuery;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    $this->enrollmentId = $this->booked->enrollmentId;
});

/** Closes the n-th lesson of the enrollment (0-based, in time order) with an outcome, as the sweeper would. */
function closeLesson(int $n, string $outcome = 'completed'): SessionReport
{
    $session = Session::query()->where('enrollment_id', test()->enrollmentId)->orderBy('starts_at')->skip($n)->firstOrFail();
    $session->update(['status' => $outcome]);
    event(new SessionFinalized($session->academy_id, $session->id, $outcome, []));

    return SessionReport::query()->where('session_id', $session->id)->firstOrFail();
}

function handIn(SessionReport $report, array $items, array $extra = []): SessionReport
{
    return app(SubmitSessionReport::class)->handle($report, test()->teacher->id, $extra + [
        'engagement' => 4, 'overall' => 4,
        'items' => array_map(fn ($i) => ['node_id' => test()->lessons[$i[0]], 'outcome' => $i[1], 'score' => $i[2] ?? null], $items),
    ]);
}

it('follows the only program of the subject from enrolment, and opens a report when a lesson ends', function () {
    // The enrolment came before the program here, so attach it as a coordinator would.
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    expect(app(EnrollmentApi::class)->find($this->enrollmentId)->curriculumVersionId)->not->toBeNull()
        ->and(app(ProgressQuery::class)->position($this->enrollmentId)->percent)->toBe(0);

    $report = closeLesson(0);
    expect($report->kind)->toBe('lesson')->and($report->status)->toBe('due')->and($report->teacher_id)->toBe($this->teacher->id)
        ->and($report->student_id)->toBe($this->student->id)
        ->and($report->due_at->greaterThan($report->session_starts_at->addHours(12)))->toBeTrue();

    expect(closeLesson(1, 'student_absent')->kind)->toBe('absence');
    $session = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->skip(2)->first();
    event(new SessionFinalized($session->academy_id, $session->id, 'teacher_absent', []));
    expect(SessionReport::query()->count())->toBe(2);

    // Idempotent: the same event twice opens one report.
    event(new SessionFinalized($report->academy_id, $report->session_id, 'completed', []));
    expect(SessionReport::query()->count())->toBe(2);
});

it('moves the student along the roadmap as reports come in, and says what was mastered', function () {
    Event::fake([SessionReportSubmitted::class, NodeMastered::class, HomeworkAssigned::class]);
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));

    handIn(closeLesson(0), [[0, 'mastered', 5], [1, 'introduced', 3]]);
    $pos = app(ProgressQuery::class)->position($this->enrollmentId);
    expect($pos->current_node_id)->toBe($this->lessons[1])
        ->and($pos->percent)->toBe(12)              // 1 of 8 weight
        ->and((int) $pos->mastered_weight)->toBe(1);
    Event::assertDispatched(NodeMastered::class, fn ($e) => $e->nodeId === $this->lessons[0]);
    Event::assertDispatched(SessionReportSubmitted::class, fn ($e) => ! $e->late && $e->kind === 'lesson');

    handIn(closeLesson(1), [[1, 'mastered', 4], [2, 'practiced', 4]]);
    handIn(closeLesson(2), [[2, 'practiced', 5]]);   // lesson 3 needs two scores of 4+
    $pos = app(ProgressQuery::class)->position($this->enrollmentId);
    expect($pos->current_node_id)->toBe($this->lessons[3])
        ->and($pos->percent)->toBe(50)              // 1 + 1 + 2 of 8
        ->and((float) $pos->pace)->toBe(round(4 / 3, 3))
        ->and((float) $pos->eta_weeks)->toBe(1.5);  // 4 left ÷ 1.333 ÷ 2 a week
});

it('lets the teacher correct a report until it is reviewed, and rebuilds the progress from it', function () {
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    $report = handIn(closeLesson(0), [[0, 'mastered', 5]]);
    expect(app(ProgressQuery::class)->position($this->enrollmentId)->percent)->toBe(12);

    $report = handIn($report, [[0, 'practiced', 3]]);
    expect(app(ProgressQuery::class)->position($this->enrollmentId)->percent)->toBe(0)
        ->and($report->items)->toHaveCount(1);

    app(ReviewSessionReport::class)->handle($report, $this->admin->id, 'Good detail');
    expect(fn () => handIn($report->refresh(), [[0, 'mastered', 5]]))->toThrow(ValidationException::class);
});

it('refuses lessons from outside the student\'s program, and asks absence notes only for absences', function () {
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    $report = closeLesson(0);

    expect(fn () => app(SubmitSessionReport::class)->handle($report, $this->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => (string) Str::uuid(), 'outcome' => 'mastered']],
    ]))->toThrow(ValidationException::class);
    expect(fn () => app(SubmitSessionReport::class)->handle($report, $this->teacher->id, ['engagement' => 4, 'overall' => 4, 'items' => []]))
        ->toThrow(ValidationException::class);

    $absence = closeLesson(1, 'student_absent');
    expect(fn () => app(SubmitSessionReport::class)->handle($absence, $this->teacher->id, []))->toThrow(ValidationException::class);
    $absence = app(SubmitSessionReport::class)->handle($absence, $this->teacher->id, ['absence_note' => 'Called twice, no answer']);
    expect($absence->status)->toBe('submitted')->and($absence->items)->toHaveCount(0);
});

it('marks reports overdue after the deadline and a late hand-in as late', function () {
    Event::fake([SessionReportOverdue::class, SessionReportSubmitted::class]);
    $report = closeLesson(0);

    expect(app(MarkOverdueReports::class)->handle($report->due_at->subMinute()))->toBe(0)
        ->and(app(MarkOverdueReports::class)->handle($report->due_at->addMinute()))->toBe(1)
        ->and($report->refresh()->status)->toBe('overdue');
    Event::assertDispatched(SessionReportOverdue::class);

    CarbonImmutable::setTestNow($report->due_at->addHour());
    // No program attached: the report is notes and scores only.
    $report = app(SubmitSessionReport::class)->handle($report, $this->teacher->id, ['engagement' => 3, 'overall' => 3, 'notes_for_student' => 'Revise the letters']);
    expect($report->late)->toBeTrue();
    Event::assertDispatched(SessionReportSubmitted::class, fn ($e) => $e->late);
});

it('sets homework from the report, takes a voice recording, and lets the teacher mark it', function () {
    Storage::fake('local');
    Event::fake([HomeworkAssigned::class]);
    $report = handIn(closeLesson(0), [], ['homework' => ['title' => 'Recite lesson 1', 'accepts' => 'audio', 'due_days' => 2]]);

    $homework = $report->homework;
    expect($homework->title)->toBe('Recite lesson 1')->and($homework->accepts)->toBe('audio')
        ->and($homework->due_at->diffInDays($report->submitted_at))->toEqual(-2.0);
    Event::assertDispatched(HomeworkAssigned::class);

    expect(fn () => app(SubmitHomework::class)->handle($homework, $this->student->id, 'I read it', null))->toThrow(ValidationException::class);
    $voice = UploadedFile::fake()->create('recitation.m4a', 300, 'audio/mp4');
    $submission = app(SubmitHomework::class)->handle($homework, $this->student->id, null, $voice);

    expect($homework->refresh()->status)->toBe('submitted')
        ->and($submission->isAudio())->toBeTrue()
        ->and($submission->file_path)->toStartWith("academies/{$this->academy->id}/homework/{$homework->id}/");
    Storage::disk('local')->assertExists($submission->file_path);

    app(ReviewHomework::class)->handle($homework, $this->teacher->id, 5, 'Beautiful tajweed');
    expect($homework->refresh()->status)->toBe('reviewed')->and($homework->grade)->toBe(5)
        ->and(fn () => app(SubmitHomework::class)->handle($homework, $this->student->id, 'again', null))->toThrow(ValidationException::class);
});

it('marks homework missed after its due time, and still takes it late', function () {
    $report = handIn(closeLesson(0), [], ['homework' => ['title' => 'Write the letters', 'accepts' => 'text', 'due_days' => 1]]);
    $homework = $report->homework;

    expect(app(MarkMissedHomework::class)->handle($homework->due_at->addMinute()))->toBe(1)
        ->and($homework->refresh()->status)->toBe('missed');
    app(SubmitHomework::class)->handle($homework, $this->student->id, 'ا ب ت ث', null);
    expect($homework->refresh()->status)->toBe('submitted');
});

it('drops the homework when the teacher clears it before anything was handed in', function () {
    $report = handIn(closeLesson(0), [], ['homework' => ['title' => 'Write the letters']]);
    expect(Homework::query()->count())->toBe(1);

    handIn($report, [], ['homework' => ['title' => '']]);
    expect(Homework::query()->count())->toBe(0);
});

it('never shows one academy the reports of another', function () {
    closeLesson(0);
    $this->actAsTenant($this->createAcademy(['slug' => 'other']));

    expect(SessionReport::query()->count())->toBe(0)
        ->and(DB::table('enrollment_progress')->count())->toBe(0)
        ->and(DB::table('homework')->count())->toBe(0);
});
