<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Actions\Mushaf\MarkMistake;
use Modules\Quran\Actions\Plans\AdjustQuranPlan;
use Modules\Quran\Actions\Plans\StartQuranPlan;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Mushaf\MushafLessons;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Tenancy\TenantContext;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranLessons;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranLessons::class);

/*
 * The Quran program and a course's plan on it (docs/architecture/02,
 * section 26): the academy's programs, a plan per course with where the
 * learner is, today's portion made from it, and a lesson's record: grades
 * from mistakes, the position moving only past a new part that passed, and
 * never while the course is paused or over. The fixture's text is made up in
 * Tanzil's shape, never the Quran: surah 1 (3 verses: 1:1–2 on page 1, 1:3
 * on page 2, part 1), surah 2 (2 verses on page 3, part 2).
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->makeQuranSubject($this->quran);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->sessions = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->pluck('id')->all();
    $this->program = app(SaveQuranProgram::class)->handle(null, [
        'name' => 'Fixture hifz', 'track' => 'hifz', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 2, 'scope_to_verse' => 2,
        'direction' => 'forward', 'unit' => 'verses', 'new_amount' => 2, 'near_portions' => 1, 'far_pages' => 1, 'pass_grade' => 'good', 'exam_stops' => 'surah',
    ], $this->admin->id);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function programIn(TestCase $test, callable $do): mixed
{
    $tenants = app(TenantContext::class);
    $tenants->set($test->academy);
    try {
        return $do();
    } finally {
        $tenants->clear();
    }
}

function programApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

/** Half an hour into a lesson. */
function duringLesson(TestCase $test, string $sessionId): void
{
    $startsAt = app(TenantContext::class)->current() !== null ? Session::query()->findOrFail($sessionId)->starts_at : programIn($test, fn () => Session::query()->findOrFail($sessionId)->starts_at);
    CarbonImmutable::setTestNow(CarbonImmutable::parse($startsAt)->addMinutes(30));
}

function quranRefusal(Closure $do): array
{
    try {
        $do();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Not refused');
}

it('saves the academy\'s programs as the rules allow, and holds a program\'s shape once a plan is on it', function () {
    programIn($this, function () {
        $save = app(SaveQuranProgram::class);
        $audit = DB::table('audit_logs')->where('action', 'quran.program_saved')->sole();
        expect($audit->subject_id)->toBe($this->program->id)->and(json_decode($audit->data, true))->toBe(['name' => 'Fixture hifz', 'quran_fields' => []]);

        // A scope outside the platform's copy, an amount the unit does not allow, quarters the copy does not have.
        expect(quranRefusal(fn () => $save->handle(null, ['scope_to_surah' => 3] + $this->program->only(['name', 'track', 'scope_from_surah', 'scope_from_verse', 'scope_to_verse', 'direction', 'unit', 'new_amount', 'near_portions', 'far_pages', 'pass_grade']))))
            ->toHaveKey('scope_to_surah');
        expect(quranRefusal(fn () => $save->handle($this->program, ['new_amount' => 0.5])))->toBe(['new_amount' => [__('quran::programs.errors.amount')]])
            ->and(quranRefusal(fn () => $save->handle($this->program, ['unit' => 'quarter_hizb', 'new_amount' => 1])))->toBe(['unit' => [__('quran::programs.errors.quarters')]]);
        // A ready track whose scope is not in this copy is refused; the Noorani Qaida needs no text.
        expect(quranRefusal(fn () => $save->fromPreset('juz_amma_talqin')))->toHaveKey('scope_from_surah');
        $noorani = $save->fromPreset('noorani');
        expect($noorani)->toMatchArray(['name' => __('quran::programs.presets.noorani.name'), 'track' => 'noorani', 'preset' => 'noorani', 'unit' => 'lesson', 'scope_to_verse' => 17]);

        // Before any plan the shape may change; after, only the pace, reviews and grades.
        $save->handle($this->program, ['direction' => 'backward']);
        $save->handle($this->program, ['direction' => 'forward']);
        app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, [], $this->admin->id);
        expect(quranRefusal(fn () => $save->handle($this->program->fresh(), ['direction' => 'backward'])))->toBe(['direction' => [__('quran::programs.errors.shape_locked')]]);
        $save->handle($this->program->fresh(), ['near_portions' => 2, 'name' => 'Fixture']);
        expect(json_decode(DB::table('audit_logs')->where('action', 'quran.program_saved')->latest('created_at')->orderByDesc('id')->first()->data, true))
            ->toBe(['name' => 'Fixture', 'quran_fields' => ['near_portions']]);

        $save->archive($this->program->fresh());
        expect(quranRefusal(fn () => app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id)))->toHaveKey('program_id');
    });
});

it('starts a plan after what the student already knows, one live a course, only on a Quran subject', function () {
    programIn($this, function () {
        $start = app(StartQuranPlan::class);
        // Placement: page 1 known well; the plan starts at the first verse not on a memorized page.
        $plan = $start->handle($this->booked->enrollmentId, $this->program->id, ['placement' => ['pages' => [1], 'strength' => 'good']], $this->admin->id);
        expect($plan->position()?->toArray())->toBe(['surah' => 1, 'verse' => 3])
            ->and(HifzPage::query()->where('student_id', $this->student->id)->where('page', 1)->value('state'))->toBe('memorized')
            ->and(json_decode(DB::table('audit_logs')->where('action', 'quran.plan_started')->sole()->data, true))->toBe(['program' => 'Fixture hifz']);

        // A new plan closes the last; where to start may be said, and must be in the program.
        $again = $start->handle($this->booked->enrollmentId, $this->program->id, ['start' => ['surah' => 2, 'verse' => 1], 'pace' => ['new_amount' => 1]], $this->admin->id);
        expect($plan->fresh()->status)->toBe('closed')
            ->and($again->position()?->toArray())->toBe(['surah' => 2, 'verse' => 1])
            ->and($again->shape()->newAmount)->toBe(1.0)
            ->and(QuranPlan::query()->where('status', 'active')->count())->toBe(1);
        expect(quranRefusal(fn () => $start->handle($this->booked->enrollmentId, $this->program->id, ['start' => ['surah' => 2, 'verse' => 3]])))->toHaveKey('start')
            ->and(quranRefusal(fn () => $start->handle($this->booked->enrollmentId, $this->program->id, ['pace' => ['new_amount' => 0.5]])))->toHaveKey('new_amount');

        // A course of a general subject takes no Quran program.
        $this->noQuranCourses();
        expect(quranRefusal(fn () => $start->handle($this->booked->enrollmentId, $this->program->id)))->toBe(['enrollment_id' => [__('quran::programs.errors.not_quran')]]);
    });
});

it('gives the teacher today\'s portion, records what was recited, and moves on only past a new part that passed', function () {
    programIn($this, fn () => app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, ['placement' => ['pages' => [1]]], $this->admin->id));
    [$first, $second, $third] = $this->sessions;
    duringLesson($this, $first);

    $portion = programApiAs($this->teacher)->getJson("/api/v1/quran/lessons/{$first}/portion")->assertOk()->json('data');
    expect($portion['plan']['position'])->toMatchArray(['surah' => 1, 'verse' => 3, 'title' => 'Al-Ula 3'])
        // Memorizing closes surah 1 in its own lesson (one verse, half the amount of two).
        ->and($portion['plan']['portion']['new']['ranges'])->toBe([['surah' => 1, 'from' => 3, 'to' => 3, 'title' => 'Al-Ula 3']])
        ->and($portion['plan']['portion']['new']['pages'])->toBe([2])
        ->and($portion['plan']['portion']['near']['ranges'][0])->toMatchArray(['surah' => 1, 'from' => 1, 'to' => 2])
        // Page 1, the only one memorized, is the near review's: nothing is far yet.
        ->and($portion['plan']['portion']['far'])->toBeNull()
        ->and($portion['plan']['portion']['stops'])->toBe([['kind' => 'surah', 'number' => 1, 'label' => 'End of Surah Al-Ula']])
        ->and($portion['plan']['frozen'])->toBeNull()
        ->and(array_column($portion['mistake_types'], 'key'))->toBe(['wrong', 'hesitation', 'tajweed'])
        ->and($portion['results'])->toBe([]);
    // Nobody else's lesson: another teacher, the student, a guardian.
    $other = programIn($this, fn () => User::factory()->withRole(Role::Teacher)->create());
    programApiAs($other)->getJson("/api/v1/quran/lessons/{$first}/portion")->assertForbidden();
    programApiAs($this->student)->getJson("/api/v1/quran/lessons/{$first}/portion")->assertForbidden();

    // The new part without mistakes (excellent), the near review a page with a wrong word and a tajweed slip (1.25: very good).
    $done = programApiAs($this->teacher)->withHeader('Idempotency-Key', 'first-lesson')->postJson("/api/v1/quran/lessons/{$first}/result", ['parts' => [
        'new' => ['mistakes' => []], 'near' => ['mistakes' => [['kind' => 'wrong', 'count' => 1], ['kind' => 'tajweed', 'count' => 1]]],
    ]])->assertCreated()->json('data');
    expect($done['results'][0]['parts']['new'])->toMatchArray(['grade' => 'excellent', 'computed' => 'excellent'])
        ->and($done['results'][0]['parts']['near'])->toMatchArray(['grade' => 'very_good', 'mistakes' => [['kind' => 'wrong', 'label' => 'Wrong', 'count' => 1], ['kind' => 'tajweed', 'label' => 'Tajweed', 'count' => 1]]])
        ->and($done['results'][0]['advanced'])->toBeTrue()
        ->and($done['plan']['position'])->toMatchArray(['surah' => 2, 'verse' => 1]);
    // The same request again replays; a second record of the lesson is refused.
    programApiAs($this->teacher)->withHeader('Idempotency-Key', 'first-lesson')->postJson("/api/v1/quran/lessons/{$first}/result", ['parts' => [
        'new' => ['mistakes' => []], 'near' => ['mistakes' => [['kind' => 'wrong', 'count' => 1], ['kind' => 'tajweed', 'count' => 1]]],
    ]])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$first}/result", ['parts' => ['new' => []]])->assertUnprocessable()->assertJsonValidationErrors('students.0');

    programIn($this, function () {
        // The hifz map heard each page once, from the lesson: page 2 memorized, page 1 reviewed.
        $reviews = DB::table('hifz_reviews')->where('source', 'lesson')->orderBy('page')->get(['page', 'grade']);
        expect($reviews->map(fn ($r) => [(int) $r->page, (int) $r->grade])->all())->toBe([[1, 3], [2, 4]])
            ->and(HifzPage::query()->where('student_id', $this->student->id)->where('page', 2)->value('state'))->toBe('memorized');
    });

    // Lesson two: no near review at this student's pace, so the far review takes page 1 (pages 1 and 2 are memorized);
    // surah 2 (one page) with six wrong words: to repeat, and the position stays.
    programApiAs($this->teacher)->patchJson("/api/v1/quran/students/{$this->booked->enrollmentId}/plan", ['near_portions' => 0])->assertOk();
    duringLesson($this, $second);
    $portion = programApiAs($this->teacher)->getJson("/api/v1/quran/lessons/{$second}/portion")->assertOk()->json('data.plan.portion');
    expect($portion['new']['ranges'])->toBe([['surah' => 2, 'from' => 1, 'to' => 2, 'title' => 'Ath-Thaniya 1–2']])
        ->and($portion['far']['pages'])->toBe([1]);
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$second}/result", ['parts' => ['new' => ['mistakes' => [['kind' => 'wrong', 'count' => 6]]], 'far' => []]])
        ->assertCreated()->assertJsonPath('data.results.0.parts.new.grade', 'repeat')->assertJsonPath('data.results.0.advanced', false)
        ->assertJsonPath('data.plan.position.surah', 2)->assertJsonPath('data.plan.position.verse', 1);
    programIn($this, function () {
        expect(HifzPage::query()->where('student_id', $this->student->id)->where('page', 3)->value('state'))->toBe('learning')
            ->and(QuranPlan::query()->where('status', 'active')->value('far_cursor'))->toBe(1);
    });

    // Lesson three: the teacher gives another grade than the mistakes, with a note; the program is complete.
    duringLesson($this, $third);
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$third}/result", ['parts' => ['new' => ['mistakes' => [['kind' => 'wrong', 'count' => 6]], 'grade' => 'good']]])
        ->assertUnprocessable()->assertJsonValidationErrors('students.0.parts.new.note');
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$third}/result", ['parts' => ['new' => ['mistakes' => [['kind' => 'wrong', 'count' => 6]], 'grade' => 'good', 'note' => 'Nervous; knew it at home']]])
        ->assertCreated()->assertJsonPath('data.results.0.parts.new.computed', 'repeat')->assertJsonPath('data.results.0.parts.new.note', 'Nervous; knew it at home')
        ->assertJsonPath('data.plan.position', null)->assertJsonPath('data.plan.finished', true)->assertJsonPath('data.plan.progress', 1);

    // The family reads the plan and the grades, never the teacher's note or the worked-out grade.
    $family = programApiAs($this->guardian)->getJson('/api/v1/quran/plan')->assertOk()->json('data');
    expect($family['learner_id'])->toBe($this->student->id)
        ->and($family['courses'][0]['plan']['finished'])->toBeTrue()
        ->and($family['courses'][0]['recent'])->toHaveCount(3)
        ->and($family['courses'][0]['recent'][0]['parts']['new'])->not->toHaveKey('note')->not->toHaveKey('computed')
        ->and(json_encode($family))->not->toContain('Nervous');
    // The teacher's view of the course has them.
    programApiAs($this->teacher)->getJson("/api/v1/quran/students/{$this->booked->enrollmentId}/plan")->assertOk()
        ->assertJsonPath('data.recent.0.parts.new.note', 'Nervous; knew it at home');
    programApiAs($other)->getJson("/api/v1/quran/students/{$this->booked->enrollmentId}/plan")->assertForbidden();
});

it('counts the words marked in the lesson\'s mushaf when no mistakes are given, by the academy\'s own kinds', function () {
    programIn($this, function () {
        app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, ['placement' => ['pages' => [1]]], $this->admin->id);
        app(SaveQuranSettings::class)->handle(['mistake_types' => [
            ['key' => 'wrong', 'weight' => 2], ['key' => 'hesitation', 'weight' => 0.5], ['key' => 'tajweed', 'weight' => 0.25],
            ['key' => 'waqf', 'weight' => 1, 'name_ar' => 'الوقف والابتداء', 'name_en' => 'Stopping and starting'],
        ]]);
        expect(json_decode(DB::table('audit_logs')->where('action', 'quran.settings_saved')->sole()->data, true))->toBe(['quran_fields' => ['mistake_types']]);
        duringLesson($this, $this->sessions[0]);
        $lesson = app(MushafLessons::class)->for($this->sessions[0], $this->teacher->id);
        app(MarkMistake::class)->handle($lesson, 1, 3, 1, 'wrong');
        app(MarkMistake::class)->handle($lesson, 1, 3, 2, 'waqf');
        expect(fn () => app(MarkMistake::class)->handle($lesson, 1, 3, 3, 'unknown'))->toThrow(ValidationException::class);

        // Page 2 (1:3) is one page: wrong 2 + waqf 1 = 3, good.
        [$result] = app(RecordQuranLesson::class)->handle($this->sessions[0], $this->teacher->id, ['parts' => ['new' => []]]);
        expect($result->parts['new'])->toMatchArray(['mistakes' => ['waqf' => 1, 'wrong' => 1], 'penalty' => 3.0, 'grade' => 'good'])
            ->and($result->advanced)->toBeTrue();
        expect(fn () => app(RecordQuranLesson::class)->handle($this->sessions[1], $this->other->id, ['parts' => ['new' => []]]))->toThrow(AuthorizationException::class);

        // The scale is the academy's; a wrong one is refused.
        expect(quranRefusal(fn () => app(SaveQuranSettings::class)->handle(['grade_scale' => ['excellent' => 2, 'very_good' => 1, 'good' => 3, 'acceptable' => 5]])))->toHaveKey('grade_scale')
            ->and(quranRefusal(fn () => app(SaveQuranSettings::class)->handle(['mistake_types' => [['key' => 'wrong', 'weight' => 1]]])))->toHaveKey('mistake_types');
        app(SaveQuranSettings::class)->handle(['exams_enabled' => true, 'examiner' => 'supervisor', 'certificate_title' => '<b>شهادة إتمام</b>', 'memorization_threshold' => 'very_good']);
        expect(QuranSettings::current())->toMatchArray(['exams_enabled' => true, 'examiner' => 'supervisor', 'certificate_title' => 'شهادة إتمام', 'riwaya' => 'hafs', 'mushaf' => 'madinah_604'])
            ->and(json_decode(DB::table('audit_logs')->where('action', 'quran.settings_saved')->orderByDesc('created_at')->orderByDesc('id')->first()->data, true)['quran_fields'])
            ->toEqualCanonicalizing(['exams_enabled', 'examiner', 'certificate_title', 'memorization_threshold'])
            ->and(DB::table('audit_logs')->where('action', 'like', 'quran.%')->pluck('data')->implode(' '))->not->toContain('شهادة');
        expect(quranRefusal(fn () => app(SaveQuranSettings::class)->handle(['riwaya' => 'warsh'])))->toHaveKey('riwaya');
    });
});

it('freezes the plan while the course is paused or over: the result is kept, nothing moves', function () {
    programIn($this, function () {
        app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, ['placement' => ['pages' => [1]]], $this->admin->id);
        $today = CarbonImmutable::parse('2026-10-05');
        app(EnrollmentApi::class)->activate($this->booked->enrollmentId, $this->admin->id);
        app(EnrollmentApi::class)->pause($this->booked->enrollmentId, $today, $today->addDays(6), 'Travel', $today, $this->admin->id);
    });
    duringLesson($this, $this->sessions[0]);
    programApiAs($this->teacher)->getJson("/api/v1/quran/lessons/{$this->sessions[0]}/portion")->assertOk()->assertJsonPath('data.plan.frozen', 'paused');
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$this->sessions[0]}/result", ['parts' => ['new' => []]])->assertCreated()
        ->assertJsonPath('data.results.0.parts.new.grade', 'excellent')->assertJsonPath('data.results.0.advanced', false)->assertJsonPath('data.results.0.frozen', true)
        ->assertJsonPath('data.plan.position.verse', 3);

    programIn($this, function () {
        $today = CarbonImmutable::parse('2026-10-05');
        app(EnrollmentApi::class)->resume($this->booked->enrollmentId, true, $this->admin->id);
        app(EnrollmentApi::class)->end($this->booked->enrollmentId, $today->addDays(2), 'Moved away', $today, $this->admin->id);
    });
    // A lesson before the end, recorded once the course is over: nothing moves either.
    duringLesson($this, $this->sessions[1]);
    programApiAs($this->teacher)->getJson("/api/v1/quran/lessons/{$this->sessions[1]}/portion")->assertOk()->assertJsonPath('data.plan.frozen', 'ended');
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$this->sessions[1]}/result", ['parts' => ['new' => []]])->assertCreated()
        ->assertJsonPath('data.results.0.advanced', false)->assertJsonPath('data.plan.position.verse', 3);
    programIn($this, fn () => expect(quranRefusal(fn () => app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id)))->toHaveKey('enrollment_id'));
});

it('lets the teacher change the student\'s pace, audited without values', function () {
    programIn($this, fn () => app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, [], $this->admin->id));
    programApiAs($this->teacher)->patchJson("/api/v1/quran/students/{$this->booked->enrollmentId}/plan", ['new_amount' => 1, 'far_pages' => 3])->assertOk()
        ->assertJsonPath('data.plan.pace.new_amount', 1)->assertJsonPath('data.plan.pace.far_pages', 3)->assertJsonPath('data.plan.pace.near_portions', 1);
    programApiAs($this->teacher)->patchJson("/api/v1/quran/students/{$this->booked->enrollmentId}/plan", ['new_amount' => 0.5])->assertUnprocessable()->assertJsonValidationErrors('new_amount');
    programIn($this, function () {
        expect(json_decode(DB::table('audit_logs')->where('action', 'quran.plan_adjusted')->sole()->data, true))->toBe(['quran_fields' => ['new_amount', 'far_pages']]);
        $plan = QuranPlan::query()->where('status', 'active')->sole();
        app(AdjustQuranPlan::class)->handle($plan, ['position' => ['surah' => 2, 'verse' => 2], 'new_amount' => null]);
        expect($plan->fresh()->position()?->toArray())->toBe(['surah' => 2, 'verse' => 2])->and($plan->fresh()->new_amount)->toBeNull();
        expect(quranRefusal(fn () => app(AdjustQuranPlan::class)->handle($plan, ['position' => ['surah' => 3, 'verse' => 1]])))->toHaveKey('position');
    });
});

it('has no portion for a Quran course without a plan', function () {
    duringLesson($this, $this->sessions[0]);
    programApiAs($this->teacher)->getJson("/api/v1/quran/lessons/{$this->sessions[0]}/portion")->assertOk()->assertJsonPath('data.plan', null);
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$this->sessions[0]}/result", ['parts' => ['new' => []]])
        ->assertUnprocessable()->assertJsonValidationErrors('session');
    expect(programApiAs($this->guardian)->getJson('/api/v1/quran/plan')->assertOk()->json('data.courses'))->toBe([]);
});

it('closes the program\'s routes and writes nothing without the Quran in the plan, and keeps what it holds', function () {
    programIn($this, fn () => app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, [], $this->admin->id));
    app(TenantContext::class)->clear();
    $plan = SaasPlan::query()->create(['code' => 'no-quran', 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    duringLesson($this, $this->sessions[0]);

    programApiAs($this->teacher)->getJson("/api/v1/quran/lessons/{$this->sessions[0]}/portion")->assertForbidden()->assertJsonPath('type', fn ($t) => str_contains((string) $t, 'feature-not-in-plan'));
    programApiAs($this->teacher)->postJson("/api/v1/quran/lessons/{$this->sessions[0]}/result", ['parts' => ['new' => []]])->assertForbidden();
    programApiAs($this->guardian)->getJson('/api/v1/quran/plan')->assertForbidden();
    programApiAs($this->teacher)->patchJson("/api/v1/quran/students/{$this->booked->enrollmentId}/plan", ['new_amount' => 1])->assertForbidden();
    programIn($this, function () {
        expect(fn () => app(RecordQuranLesson::class)->handle($this->sessions[0], $this->teacher->id, ['parts' => ['new' => []]]))->toThrow(FeatureNotInPlan::class)
            ->and(fn () => app(SaveQuranProgram::class)->fromPreset('noorani'))->toThrow(FeatureNotInPlan::class)
            ->and(fn () => app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id))->toThrow(FeatureNotInPlan::class)
            ->and(fn () => app(AdjustQuranPlan::class)->handle(QuranPlan::query()->sole(), ['new_amount' => 1]))->toThrow(FeatureNotInPlan::class);
        expect(QuranLessonResult::query()->count())->toBe(0)
            ->and(QuranProgram::query()->count())->toBe(1)
            ->and(QuranPlan::query()->where('status', 'active')->count())->toBe(1);
    });
});
