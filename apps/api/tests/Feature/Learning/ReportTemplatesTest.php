<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Curriculum\Models\Program;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\SaveReportTemplate;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Filament\Pages\ReportTemplates;
use Modules\Learning\Models\ReportTemplate;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Templates\TemplateAnswers;
use Modules\Learning\Templates\TemplateField;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    $this->enrollmentId = $this->booked->enrollmentId;
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
});

/** Pages memorised (a number), tajweed (a choice), a note for the academy only (text). */
function rtQuranQuestions(): array
{
    return [
        ['label_ar' => 'الصفحات اللي اتحفظت', 'label_en' => 'Pages memorised', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 20, 'unit_ar' => 'صفحة', 'unit_en' => 'pages'],
        ['label_ar' => 'التجويد', 'label_en' => 'Tajweed', 'type' => 'choice', 'options' => [
            ['ar' => 'ممتاز', 'en' => 'Excellent'], ['ar' => 'كويس', 'en' => 'Good'], ['ar' => 'محتاج شغل', 'en' => 'Needs work'],
        ]],
        ['label_ar' => 'ملاحظة للمشرف', 'label_en' => 'Note for the supervisor', 'type' => 'text', 'family' => false],
    ];
}

function rtLessonReport(int $n): SessionReport
{
    $session = Session::query()->where('enrollment_id', test()->enrollmentId)->orderBy('starts_at')->skip($n)->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));

    return SessionReport::query()->where('session_id', $session->id)->firstOrFail();
}

function rtWriteReport(SessionReport $report, array $answers, array $extra = []): SessionReport
{
    return app(SubmitSessionReport::class)->handle($report, test()->teacher->id, $extra + [
        'engagement' => 4, 'overall' => 5,
        'items' => [['node_id' => test()->lessons[0], 'outcome' => 'practiced']],
        'answers' => $answers,
    ]);
}

function rtQuestionError(Closure $run): array
{
    try {
        $run();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('keeps each change as a new version, with keys that stay with their question', function () {
    $save = app(SaveReportTemplate::class);
    $v1 = $save->handle($this->quran, rtQuranQuestions(), $this->admin->id);
    expect($v1->version)->toBe(1)
        ->and(array_column($v1->fields, 'key'))->toBe(['f1', 'f2', 'f3'])
        ->and(array_column($v1->fields[1]['options'], 'key'))->toBe(['o1', 'o2', 'o3']);

    // The same questions again: nothing new.
    $same = array_map(fn (array $f) => $f, $v1->fields);
    expect($save->handle($this->quran, $same, $this->admin->id)->id)->toBe($v1->id);

    // Words changed, the tajweed choices reordered with one dropped and one added, the note now a yes/no.
    $fields = $v1->fields;
    $fields[0]['label_en'] = 'Pages memorised today';
    $fields[1]['options'] = [$fields[1]['options'][2], $fields[1]['options'][0], ['ar' => 'ضعيف', 'en' => '']];
    $fields[2]['type'] = 'yes_no';
    $v2 = $save->handle($this->quran, $fields, $this->admin->id);

    expect($v2->version)->toBe(2)
        ->and(array_column($v2->fields, 'key'))->toBe(['f1', 'f2', 'f4'])
        ->and(array_column($v2->fields[1]['options'], 'key'))->toBe(['o3', 'o1', 'o4'])
        ->and($v2->fields[1]['options'][2]['en'])->toBe('ضعيف')                 // one language stands in for the other
        ->and(ReportTemplate::query()->count())->toBe(2)
        ->and(ReportTemplate::currentFor($this->quran)->id)->toBe($v2->id);

    // A question dropped and a new one added never takes the old key.
    $v3 = $save->handle($this->quran, [$v2->fields[0], ['label_ar' => 'التركيز', 'type' => 'rating']], $this->admin->id);
    expect(array_column($v3->fields, 'key'))->toBe(['f1', 'f5'])->and($v3->fields[1]['label_en'])->toBe('التركيز');

    // A version is never edited.
    expect(fn () => DB::table('report_templates')->where('id', $v1->id)->update(['version' => 9]))->toThrow(QueryException::class);
});

it('refuses questions it cannot ask', function () {
    $save = app(SaveReportTemplate::class);
    $bad = fn (array $fields, ?string $subject = null) => array_key_first(rtQuestionError(fn () => $save->handle($subject ?? $this->quran, $fields, $this->admin->id)));

    expect($bad(array_fill(0, 13, ['label_en' => 'Q', 'type' => 'rating'])))->toBe('fields')
        ->and($bad([['label_en' => '', 'label_ar' => ' ', 'type' => 'rating']]))->toBe('fields.0.label')
        ->and($bad([['label_en' => 'Q', 'type' => 'stars']]))->toBe('fields.0.type')
        ->and($bad([['label_en' => 'Q', 'type' => 'choice', 'options' => [['en' => 'Only one']]]]))->toBe('fields.0.options')
        ->and($bad([['label_en' => 'Q', 'type' => 'number', 'min' => 5, 'max' => 2]]))->toBe('fields.0.max')
        ->and($bad([['label_en' => 'Q', 'type' => 'number', 'min' => 1.5]]))->toBe('fields.0.min')
        ->and($bad([['label_en' => 'Q', 'type' => 'rating']], (string) Str::uuid()))->toBe('subject_id')
        ->and(ReportTemplate::query()->count())->toBe(0);
});

it('asks the subject\'s questions in a lesson report, and keeps the version it was handed in with', function () {
    $v1 = app(SaveReportTemplate::class)->handle($this->quran, rtQuranQuestions(), $this->admin->id);
    $first = rtLessonReport(0);

    expect(rtQuestionError(fn () => rtWriteReport($first, ['f2' => 'o1'])))->toHaveKey('answers.f1')
        ->and(rtQuestionError(fn () => rtWriteReport($first, ['f1' => 25])))->toHaveKey('answers.f1')
        ->and(rtQuestionError(fn () => rtWriteReport($first, ['f1' => 2, 'f2' => 'o9'])))->toHaveKey('answers.f2')
        ->and(rtQuestionError(fn () => rtWriteReport($first, ['f1' => 2, 'f3' => str_repeat('x', 501)])))->toHaveKey('answers.f3');

    $first = rtWriteReport($first, ['f1' => '1.50', 'f2' => 'o2', 'f3' => '  Shy, but tried hard ', 'f9' => 'dropped']);
    expect($first->template_id)->toBe($v1->id)
        ->and($first->answers)->toBe(['f1' => 1.5, 'f2' => 'o2', 'f3' => 'Shy, but tried hard']);

    // The academy changes the questions: the report handed in keeps v1, even when corrected; the next one asks v2.
    $fields = $v1->fields;
    $fields[] = ['label_en' => 'Revision', 'label_ar' => 'المراجعة', 'type' => 'yes_no', 'required' => true];
    $v2 = app(SaveReportTemplate::class)->handle($this->quran, $fields, $this->admin->id);

    $first = rtWriteReport($first, ['f1' => 3, 'f2' => 'o1']);
    expect($first->template_id)->toBe($v1->id)->and($first->answers)->toBe(['f1' => 3, 'f2' => 'o1']);

    $second = rtLessonReport(1);
    expect(rtQuestionError(fn () => rtWriteReport($second, ['f1' => 2])))->toHaveKey('answers.f4');
    $second = rtWriteReport($second, ['f1' => 2, 'f4' => false]);
    expect($second->template_id)->toBe($v2->id)->and($second->answers)->toBe(['f1' => 2, 'f4' => false]);
});

it('leaves absence notes and subjects without questions as they were', function () {
    $report = rtLessonReport(0);
    $report = rtWriteReport($report, ['f1' => 3]);
    expect($report->template_id)->toBeNull()->and($report->answers)->toBe([]);

    app(SaveReportTemplate::class)->handle($this->quran, rtQuranQuestions(), $this->admin->id);
    $session = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->skip(1)->firstOrFail();
    $session->update(['status' => 'student_absent']);
    event(new SessionFinalized($session->academy_id, $session->id, 'student_absent', []));
    $absence = SessionReport::query()->where('session_id', $session->id)->firstOrFail();

    $absence = app(SubmitSessionReport::class)->handle($absence, $this->teacher->id, ['absence_note' => 'Did not come', 'answers' => ['f1' => 3]]);
    expect($absence->kind)->toBe('absence')->and($absence->template_id)->toBeNull()->and($absence->answers)->toBe([]);
});

/** Signs in through the API as this person. */
function rtApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => $user->email, 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

it('gives the apps the questions, takes the answers, and shows the family only theirs', function () {
    app(SaveReportTemplate::class)->handle($this->quran, rtQuranQuestions(), $this->admin->id);
    $report = rtLessonReport(0);
    $this->clearTenant();

    rtApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$report->id}")->assertOk()
        ->assertJsonPath('data.template.version', 1)
        ->assertJsonPath('data.template.fields.0.key', 'f1')
        ->assertJsonPath('data.template.fields.0.type', 'number')
        ->assertJsonPath('data.template.fields.0.unit', 'pages')
        ->assertJsonPath('data.template.fields.0.required', true)
        ->assertJsonPath('data.template.fields.1.options.1', ['key' => 'o2', 'label' => 'Good'])
        ->assertJsonPath('data.template.fields.2.family', false)
        ->assertJsonPath('data.answers', []);

    rtApiAs($this->teacher)->putJson("/api/v1/teacher/reports/{$report->id}", [
        'engagement' => 5, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered']],
        'answers' => ['f1' => 25],
    ])->assertUnprocessable()->assertJson(['errors' => ['answers.f1' => ['"Pages memorised" is a number from 0 to 20.']]]);

    rtApiAs($this->teacher)->putJson("/api/v1/teacher/reports/{$report->id}", [
        'engagement' => 5, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered']],
        'answers' => ['f1' => 2, 'f2' => 'o1', 'f3' => 'Reads fast'],
    ])->assertOk()
        ->assertJsonPath('data.answers.0', ['key' => 'f1', 'label' => 'Pages memorised', 'type' => 'number', 'value' => 2, 'text' => '2 pages', 'family' => true, 'unit' => 'pages', 'max' => null])
        ->assertJsonPath('data.answers.1.text', 'Excellent')
        ->assertJsonPath('data.answers.2.text', 'Reads fast');

    $family = rtApiAs($this->guardian)->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/reports")->assertOk();
    expect(array_column($family->json('data.0.answers'), 'key'))->toBe(['f1', 'f2']);

    // In Arabic, the questions and the answers come in Arabic.
    rtApiAs($this->guardian)->withHeader('Accept-Language', 'ar')->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/reports")
        ->assertOk()->assertJsonPath('data.0.answers.0.text', '2 صفحة')->assertJsonPath('data.0.answers.1.text', 'ممتاز');
});

it('lets the curriculum managers edit the questions, and nobody else', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    $page = Livewire::test(ReportTemplates::class)->assertOk()->assertSet('subject', $this->quran)
        ->set('data.fields', [
            'a' => ['key' => null, 'label_ar' => 'الصفحات', 'label_en' => '', 'type' => 'number', 'required' => true, 'family' => true, 'min' => 0, 'max' => 10, 'unit_ar' => 'صفحة', 'unit_en' => null, 'options' => []],
            'b' => ['key' => null, 'label_ar' => '', 'label_en' => 'Focus', 'type' => 'rating', 'required' => false, 'family' => false, 'min' => null, 'max' => null, 'unit_ar' => null, 'unit_en' => null, 'options' => []],
        ])
        ->call('save')->assertNotified(__('learning::templates.saved', ['n' => 1]));
    $page->call('save')->assertNotified(__('learning::templates.unchanged'));

    $template = ReportTemplate::currentFor($this->quran);
    expect($template->version)->toBe(1)
        ->and(array_column($template->fields, 'label_en'))->toBe(['الصفحات', 'Focus'])
        ->and($template->fields[1]['family'])->toBeFalse();

    $this->actingAs($this->teacher);
    Livewire::test(ReportTemplates::class)->assertForbidden();
});

it('shows the teacher the last homework beside the video', function () {
    $report = rtWriteReport(rtLessonReport(0), [], ['homework' => ['title' => 'Recite Al-Fatiha twice', 'instructions' => 'Slowly, with the rules we practised.']]);
    $next = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->skip(1)->firstOrFail();

    $line = app(LearningApi::class)->lastHomework($this->student->id, $this->enrollmentId, $next->starts_at);
    expect($line->title)->toBe('Recite Al-Fatiha twice')->and($line->status)->toBe('assigned')->and($line->submissions)->toBe(0);

    app(SubmitHomework::class)->handle($report->homework, $this->student->id, 'Done, twice.', null);
    expect(app(LearningApi::class)->lastHomework($this->student->id, $this->enrollmentId, $next->starts_at)->status)->toBe('submitted')
        ->and(app(LearningApi::class)->lastHomework($this->student->id, $this->enrollmentId, CarbonImmutable::now()->subDay()))->toBeNull();

    $this->clearTenant();
    $this->actingAs($this->teacher)->get("http://noor.lms.test/class/{$next->id}")->assertOk()
        ->assertSee(__('classroom::classroom.homework.title'))->assertSee('Recite Al-Fatiha twice')
        ->assertSee(__('classroom::classroom.homework.statuses.submitted'));
});

it('writes a number with its unit, in Arabic with the plural from 3 to 10', function () {
    $field = TemplateField::fromArray(['key' => 'f1', 'label_ar' => 'الآيات', 'label_en' => 'Verses', 'type' => 'number', 'unit_ar' => 'آية', 'unit_ar_few' => 'آيات', 'unit_en' => 'verses']);
    $text = fn ($n, $locale) => TemplateAnswers::text($field, $n, $locale);

    expect($text(6, 'ar'))->toBe('6 آيات')->and($text(12, 'ar'))->toBe('12 آية')->and($text(2, 'ar'))->toBe('2 آية')
        ->and($text(2.5, 'ar'))->toBe('2.5 آية')->and($text(6, 'en'))->toBe('6 verses');
});
