<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Actions\Resources\AddNodeResource;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\MarkMissedHomework;
use Modules\Learning\Actions\SaveReportTemplate;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\Learning\Queries\Journey\StarsQuery;
use Modules\People\Contracts\FamilyView;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * Homework, reports and materials at their edges (docs/architecture/02,
 * section 18; CLAUDE.md, Report questions and Lesson materials): homework
 * handed in late still counts (one star), practice-only homework is handed
 * in by playing and never by a written answer, a report keeps answering the
 * questions it was handed in with after the academy changes them (for the
 * family too), and a guardian beside the video gets everyone's materials,
 * never the teachers' own.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function heToken(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    app()->forgetInstance(FamilyView::class);

    return $token;
}

function heHomework(string $accepts, CarbonImmutable $due, ?array $practice = null): Homework
{
    return app(AssignHomework::class)->handle([
        'teacher_id' => test()->teacher->id, 'student_id' => test()->student->id, 'enrollment_id' => test()->booked->enrollmentId,
        'title' => 'Homework ('.$accepts.')', 'accepts' => $accepts, 'due_at' => $due, 'practice_nodes' => $practice,
    ]);
}

it('takes homework late for one star, and a practice-only homework only by playing it', function () {
    $late = heHomework('any', CarbonImmutable::parse('2026-10-06 18:00', 'UTC'));
    $byPlaying = heHomework('practice', CarbonImmutable::parse('2026-10-09 18:00', 'UTC'), [$this->lessons[0]]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    app(MarkMissedHomework::class)->handle();
    expect($late->refresh()->status)->toBe('missed');

    // A recording handed in a day late: one star, done on the journey.
    app(SubmitHomework::class)->handle($late, $this->student->id, null, UploadedFile::fake()->create('recitation.m4a', 300, 'audio/mp4'));
    expect($late->refresh()->status)->toBe('submitted')
        ->and(app(StarsQuery::class)->for($this->student->id)['homework'])->toBe(1)
        ->and(collect(app(JourneyMap::class)->for($this->student->id, 'kids')['missions'])->firstWhere('homework_id', $late->id))
        ->toMatchArray(['state' => 'done', 'stars' => 1]);

    // The practice is the homework: no written answer, no file instead (the screens show no form for it either).
    expect(fn () => app(SubmitHomework::class)->handle($byPlaying, $this->student->id, 'played it, honest', null))
        ->toThrow(ValidationException::class, __('learning::homework.errors.by_playing'));
    $this->withToken(heToken($this->student))->postJson("/api/v1/learning/homework/{$byPlaying->id}/submissions", ['body' => 'done'])
        ->assertUnprocessable()->assertJsonPath('errors.homework.0', __('learning::homework.errors.by_playing'));
    $this->actAsTenant($this->academy);
    expect($byPlaying->refresh()->status)->toBe('assigned')->and($byPlaying->submissions()->count())->toBe(0);
});

it('answers a report by the questions it was handed in with, for the family too, after the academy changes them', function () {
    $v1 = app(SaveReportTemplate::class)->handle($this->quran, [
        ['label_ar' => 'الصفحات', 'label_en' => 'Pages', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 20],
        ['label_ar' => 'ملاحظة للمشرف', 'label_en' => 'For the supervisor', 'type' => 'text', 'family' => false],
    ], $this->admin->id);
    $session = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    $report = app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $session->id)->firstOrFail(), $this->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced']],
        'answers' => ['f1' => 3, 'f2' => 'Shy today'],
    ]);

    // Mid-course the academy renames the first question and lets the family read the second.
    $fields = $v1->fields;
    $fields[0]['label_en'] = 'Pages memorised';
    $fields[1]['family'] = true;
    $v2 = app(SaveReportTemplate::class)->handle($this->quran, $fields, $this->admin->id);
    expect($v2->version)->toBe(2)->and($report->refresh()->template_id)->toBe($v1->id);

    $family = $this->withToken(heToken($this->guardian))->withHeaders(['Accept-Language' => 'en'])
        ->getJson("/api/v1/learning/enrollments/{$this->booked->enrollmentId}/reports")->assertOk()->json('data.0.answers');
    expect($family)->toHaveCount(1)
        ->and($family[0])->toMatchArray(['key' => 'f1', 'label' => 'Pages', 'value' => 3]);
});

it('gives a guardian beside the video everyone\'s materials, never the teachers\' own', function () {
    $this->actAsTenant($this->academy);
    $add = app(AddNodeResource::class);
    $first = Node::query()->findOrFail($this->lessons[0]);
    $add->handle($first, ['kind' => 'file', 'title_en' => 'Fatiha page'], UploadedFile::fake()->create('page.pdf', 80, 'application/pdf'), $this->admin->id);
    $add->handle($first, ['kind' => 'file', 'title_en' => 'Teacher guide', 'audience' => 'teachers'], UploadedFile::fake()->create('guide.pdf', 80, 'application/pdf'), $this->admin->id);
    $lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $this->clearTenant();

    $this->actingAs($this->guardian)->get("http://noor.lms.test/class/{$lesson->id}")->assertOk()
        ->assertSee('Fatiha page')->assertDontSee('Teacher guide');
});
