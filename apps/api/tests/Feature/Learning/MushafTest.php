<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Texts\QuranText;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Models\RecitationMistake;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Tenancy\TenantContext;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * A Quran lesson's shared mushaf and the mistakes notebook (docs/
 * architecture/02, section 22): the teacher turns the pages and marks words,
 * the student and their guardians follow and read; the marks feed the hifz
 * map once the report is handed in, and suggest what to recite next. The
 * fixture's text is made up in Tanzil's shape: two surahs on three pages
 * (1:1–2 on page 1, 1:3 on page 2, surah 2 on page 3), never the Quran.
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, Program::query()->value('id'));
    // The first lesson's text is surah 1, verses 2 to 3: pages 1 and 2.
    DB::table('curriculum_nodes')->where('id', $this->lessons[0])->update(['text_source' => json_encode(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3])]);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $this->later = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip(1)->firstOrFail();
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function mushafApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

function mushafIn(TestCase $test, callable $read): mixed
{
    $tenants = app(TenantContext::class);
    $tenants->set($test->academy);
    $value = $read();
    $tenants->clear();

    return $value;
}

it('shows the lesson\'s mushaf to its teacher, its student and their guardians, word by word, and to no one else', function () {
    $mushaf = "/api/v1/learning/lessons/{$this->lesson->id}/mushaf";
    $page = mushafApiAs($this->teacher)->getJson($mushaf)->assertOk()->json('data');
    expect($page)->toMatchArray(['role' => 'teacher', 'page' => 1, 'start_page' => 1, 'teacher_page' => null, 'pages_total' => 3, 'basmala' => null, 'can_mark' => false])
        ->and(array_map(fn ($v) => [$v['surah'], $v['verse'], count($v['words'])], $page['verses']))->toBe([[1, 1, 4], [1, 2, 4]])
        ->and($page['verses'][1]['words'][0])->toBe('كَلِمَةٌ')
        ->and($page['attribution'])->toContain('Tanzil');

    mushafApiAs($this->student)->getJson($mushaf)->assertOk()->assertJsonPath('data.role', 'student');
    mushafApiAs($this->guardian)->getJson($mushaf)->assertOk()->assertJsonPath('data.role', 'guardian');
    // Surah 2 begins on page 3: its basmala comes from the platform's copy, not typed.
    expect(mushafApiAs($this->student)->getJson("{$mushaf}?page=3")->assertOk()->json('data'))
        ->toMatchArray(['page' => 3, 'basmala' => ['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ']])
        ->and(mushafApiAs($this->student)->getJson("{$mushaf}?page=3")->json('data.verses.0.words'))->toBe(['حَرْفٌ']);

    mushafApiAs($this->other)->getJson($mushaf)->assertNotFound();
    $stranger = mushafIn($this, fn () => User::factory()->withRole(Role::Guardian)->create());
    mushafApiAs($stranger)->getJson($mushaf)->assertNotFound();
    mushafApiAs($this->teacher)->getJson('/api/v1/learning/lessons/not-a-lesson/mushaf')->assertNotFound();
    mushafApiAs($this->teacher)->getJson("{$mushaf}?page=605")->assertUnprocessable();

    // A course whose curriculum reads no Quran, with no pages on the map: its lessons have no mushaf.
    mushafIn($this, fn () => DB::table('curriculum_nodes')->update(['text_source' => null]));
    mushafApiAs($this->teacher)->getJson($mushaf)->assertNotFound();
});

it('lets the teacher turn the page for the student to follow, and only the teacher', function () {
    $mushaf = "/api/v1/learning/lessons/{$this->lesson->id}/mushaf";
    mushafApiAs($this->teacher)->putJson($mushaf, ['page' => 2])->assertOk()->assertJsonPath('data.page', 2)->assertJsonPath('data.teacher_page', 2);
    mushafApiAs($this->student)->getJson($mushaf)->assertOk()->assertJsonPath('data.page', 2)->assertJsonPath('data.teacher_page', 2);
    // The student may look at another page; the teacher's stays.
    mushafApiAs($this->student)->getJson("{$mushaf}?page=3")->assertJsonPath('data.page', 3)->assertJsonPath('data.teacher_page', 2);

    mushafApiAs($this->student)->putJson($mushaf, ['page' => 3])->assertForbidden();
    mushafApiAs($this->teacher)->putJson($mushaf, ['page' => 4])->assertUnprocessable()->assertJsonValidationErrors('page');
    expect(mushafIn($this, fn () => DB::table('lesson_mushaf')->where('session_id', $this->lesson->id)->value('page')))->toBe(2);
});

it('lets the teacher mark words while the lesson is on, change what they were and take them off', function () {
    $mistakes = "/api/v1/learning/lessons/{$this->lesson->id}/mistakes";
    // Before the room opens: not yet.
    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('word');

    $this->travelTo($this->lesson->starts_at->subMinutes(5));
    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'wrong'])->assertOk()
        ->assertJsonPath('data.can_mark', true)->assertJsonPath('data.page', 1)
        ->assertJsonPath('data.marks', [['surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'wrong']]);
    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'hesitation'])->assertOk()
        ->assertJsonPath('data.marks.0.kind', 'hesitation');
    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 3, 'word' => 4, 'kind' => 'tajweed', 'page' => 2])->assertOk()
        ->assertJsonPath('data.page', 2)->assertJsonPath('data.marks', [['surah' => 1, 'verse' => 3, 'word' => 4, 'kind' => 'tajweed']]);
    expect(mushafIn($this, fn () => RecitationMistake::query()->count()))->toBe(2);

    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 5, 'kind' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('word');
    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'forgot'])->assertUnprocessable()->assertJsonValidationErrors('kind');
    mushafApiAs($this->student)->postJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 2, 'kind' => 'wrong'])->assertForbidden();
    mushafApiAs($this->student)->getJson("/api/v1/learning/lessons/{$this->lesson->id}/mushaf")->assertJsonPath('data.can_mark', false)
        ->assertJsonPath('data.marks.0.kind', 'hesitation');

    mushafApiAs($this->teacher)->deleteJson($mistakes, ['surah' => 1, 'verse' => 3, 'word' => 4])->assertOk()->assertJsonPath('data.marks', []);

    // The next lesson shows the page's earlier marks faintly.
    $this->travelTo($this->later->starts_at);
    mushafApiAs($this->student)->getJson("/api/v1/learning/lessons/{$this->later->id}/mushaf?page=1")->assertOk()
        ->assertJsonPath('data.marks', [])->assertJsonPath('data.earlier', [['surah' => 1, 'verse' => 2, 'word' => 1, 'kind' => 'hesitation', 'times' => 1]]);

    // A day after the first lesson ended, its marks are closed.
    mushafApiAs($this->teacher)->deleteJson($mistakes, ['surah' => 1, 'verse' => 2, 'word' => 1])->assertUnprocessable();
});

it('keeps each learner\'s notebook by surah, for them, their guardians and their teacher', function () {
    $this->travelTo($this->lesson->starts_at);
    $mistakes = "/api/v1/learning/lessons/{$this->lesson->id}/mistakes";
    foreach ([[1, 2, 1, 'wrong'], [1, 2, 3, 'hesitation'], [2, 2, 2, 'tajweed']] as [$surah, $verse, $word, $kind]) {
        mushafApiAs($this->teacher)->postJson($mistakes, compact('surah', 'verse', 'word', 'kind'))->assertOk();
    }

    $book = mushafApiAs($this->student)->getJson('/api/v1/learning/mistakes')->assertOk()->json('data');
    expect($book['words'])->toBe(3)
        ->and(array_column($book['surahs'], 'surah'))->toBe([1, 2])
        ->and($book['surahs'][0]['name_ar'])->toBe('الأولى')
        ->and($book['surahs'][0]['verses'][0]['words'])->toHaveCount(4)
        ->and(array_column($book['surahs'][0]['verses'][0]['marks'], 'word'))->toBe([1, 3])
        ->and($book['surahs'][0]['verses'][0]['marks'][0])->toMatchArray(['times' => 1, 'kinds' => ['wrong']]);
    expect(mushafApiAs($this->guardian)->getJson("/api/v1/learning/mistakes?learner_id={$this->student->id}")->assertOk()->json('data.words'))->toBe(3);
    expect(mushafApiAs($this->teacher)->getJson("/api/v1/teacher/students/{$this->booked->enrollmentId}/mistakes")->assertOk()->json('data.words'))->toBe(3);

    mushafApiAs($this->other)->getJson("/api/v1/teacher/students/{$this->booked->enrollmentId}/mistakes")->assertForbidden();
    $stranger = mushafIn($this, fn () => User::factory()->withRole(Role::Guardian)->create());
    mushafApiAs($stranger)->getJson("/api/v1/learning/mistakes?learner_id={$this->student->id}")->assertNotFound();
});

it('counts the marks on the hifz map once the report is handed in, and suggests their verses to recite', function () {
    mushafIn($this, function () {
        $this->lesson->update(['status' => 'completed']);
        event(new SessionFinalized($this->lesson->academy_id, $this->lesson->id, 'completed', []));
    });
    $this->travelTo($this->lesson->ends_at->addMinutes(10));
    $mistakes = "/api/v1/learning/lessons/{$this->lesson->id}/mistakes";
    // Page 1: three wrong words (forgotten); page 2: two hesitations (hard).
    foreach ([[1, 1, 'wrong'], [1, 2, 'wrong'], [2, 3, 'wrong'], [3, 1, 'hesitation'], [3, 2, 'hesitation']] as [$verse, $word, $kind]) {
        mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => $verse, 'word' => $word, 'kind' => $kind])->assertOk();
    }
    $report = mushafIn($this, fn () => SessionReport::query()->where('session_id', $this->lesson->id)->firstOrFail());
    mushafApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$report->id}")->assertOk()
        ->assertJsonPath('data.mistakes_suggestion', ['surah' => 1, 'from' => 1, 'to' => 3, 'title' => 'الأولى ١–٣']);

    mushafIn($this, fn () => app(SubmitSessionReport::class)->handle($report->fresh(), $this->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered']],
    ]));
    expect(mushafIn($this, fn () => DB::table('hifz_reviews')->orderBy('page')->get(['page', 'grade', 'source'])->map(fn ($r) => (array) $r)->all()))
        ->toBe([['page' => 1, 'grade' => 1, 'source' => 'mistakes'], ['page' => 2, 'grade' => 2, 'source' => 'mistakes']]);

    // The report handed in closes the marks.
    mushafApiAs($this->teacher)->postJson($mistakes, ['surah' => 1, 'verse' => 3, 'word' => 3, 'kind' => 'wrong'])->assertUnprocessable();
});

it('answers the class page through LearningApi with the same rules', function () {
    $this->travelTo($this->lesson->starts_at);
    $api = app(LearningApi::class);
    mushafIn($this, function () use ($api) {
        expect($api->lessonMushaf($this->lesson->id, $this->student->id))->toMatchArray(['role' => 'student', 'page' => 1])
            ->and($api->lessonMushaf($this->lesson->id, $this->other->id))->toBeNull()
            ->and($api->changeMushaf($this->lesson->id, $this->teacher->id, 'turn', ['page' => 3]))->toMatchArray(['page' => 3, 'teacher_page' => 3])
            ->and($api->changeMushaf($this->lesson->id, $this->teacher->id, 'mark', ['surah' => 2, 'verse' => 2, 'word' => 1, 'kind' => 'wrong'])['marks'])
            ->toBe([['surah' => 2, 'verse' => 2, 'word' => 1, 'kind' => 'wrong']]);
        expect(fn () => $api->changeMushaf($this->lesson->id, $this->student->id, 'turn', ['page' => 1]))->toThrow(AuthorizationException::class);
    });
});

it('tells the lesson\'s page, its teacher, its student and their guardians when the mushaf changes', function () {
    config(['database.connections.mushaf_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('mushaf_listener')->getPdo();
    $pdo->exec('listen lms_live');

    mushafApiAs($this->teacher)->putJson("/api/v1/learning/lessons/{$this->lesson->id}/mushaf", ['page' => 2])->assertOk();
    $raised = [];
    while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
        $raised[] = $note['payload'];
    }
    expect($raised)->toContain("lesson-mushaf:{$this->lesson->id}", "mushaf:{$this->teacher->id}", "mushaf:{$this->student->id}", "mushaf:{$this->guardian->id}");
});
