<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Models\Notification;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Actions\Certificates\IssueQuranCertificate;
use Modules\Quran\Actions\Certificates\RevokeQuranCertificate;
use Modules\Quran\Actions\Exams\BookQuranExam;
use Modules\Quran\Actions\Exams\CancelQuranExam;
use Modules\Quran\Actions\Exams\RecordQuranExam;
use Modules\Quran\Actions\Exams\SuggestQuranExam;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Actions\Plans\StartQuranPlan;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Tenancy\TenantContext;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranLessons;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranLessons::class);

/*
 * Quran exams and certificates (docs/architecture/02, section 26): a lesson
 * that moves a plan past a stop suggests an exam once; it is booked as an
 * assessment lesson with an examiner who is not the course's teacher, under
 * Scheduling's rules; the examiner alone records it; a fail sets a retake,
 * a pass earns a numbered certificate anyone may verify by its code. The
 * fixture's text is made up in Tanzil's shape, never the Quran: surah 1
 * (3 verses on pages 1–2, part 1), surah 2 (2 verses on page 3, part 2).
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->makeQuranSubject($this->quran);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create(['timezone' => 'Africa/Cairo']);
    $this->sessions = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->pluck('id')->all();
    $this->program = app(SaveQuranProgram::class)->handle(null, [
        'name' => 'Fixture hifz', 'track' => 'hifz', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 2, 'scope_to_verse' => 2,
        'direction' => 'forward', 'unit' => 'verses', 'new_amount' => 2, 'near_portions' => 0, 'far_pages' => 0, 'pass_grade' => 'good', 'exam_stops' => 'surah',
        'counts_for_certificate' => true,
    ], $this->admin->id);
    app(SaveQuranSettings::class)->handle(['exams_enabled' => true, 'certificates_enabled' => true]);
    $this->plan = app(StartQuranPlan::class)->handle($this->booked->enrollmentId, $this->program->id, ['placement' => ['pages' => [1]]], $this->admin->id);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function examIn(TestCase $test, callable $do): mixed
{
    $tenants = app(TenantContext::class);
    $tenants->set($test->academy);
    try {
        return $do();
    } finally {
        $tenants->clear();
    }
}

function examApiAs(?User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    if ($user === null) {
        return test()->withHeader('X-Academy', 'noor');
    }
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

function examRefusal(Closure $do): array
{
    try {
        $do();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Not refused');
}

/** The first lesson recorded (1:3, surah 1's last verse) with the new part passing: the plan moves past surah 1. */
function passSurahOne(TestCase $test): array
{
    $startsAt = examIn($test, fn () => Session::query()->findOrFail($test->sessions[0])->starts_at);
    CarbonImmutable::setTestNow(CarbonImmutable::parse($startsAt)->addMinutes(30));

    return examIn($test, fn () => app(RecordQuranLesson::class)->handle($test->sessions[0], $test->teacher->id, ['parts' => ['new' => []]]));
}

/** Thursday 8 October 2026, 16:00 in Cairo: nobody has a lesson then. */
function examTime(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-10-08 16:00', 'Africa/Cairo');
}

function bookExam(TestCase $test, QuranExam $exam, ?string $examinerId = null, ?CarbonImmutable $at = null): QuranExam
{
    return app(BookQuranExam::class)->handle($exam, $examinerId ?? $test->supervisor->id, $at ?? examTime(), 30, $test->admin->id);
}

function sentQuran(string $userId, string $template): int
{
    return Notification::query()->where('user_id', $userId)->where('template_key', $template)->count();
}

it('suggests an exam once when a lesson moves the plan past a stop, to who examines', function () {
    passSurahOne($this);

    examIn($this, function () {
        $exam = QuranExam::query()->sole();
        expect($exam)->toMatchArray(['stop_kind' => 'surah', 'stop_number' => 1, 'status' => 'suggested', 'student_id' => $this->student->id,
            'enrollment_id' => $this->booked->enrollmentId, 'plan_id' => $this->plan->id, 'program_id' => $this->program->id])
            ->and(json_decode(DB::table('audit_logs')->where('action', 'quran.exam_suggested')->sole()->data, true))->toEqual(['stop_kind' => 'surah', 'stop_number' => 1, 'retake' => false]);
        // Who holds quran.examine hears of it (the admin and the supervisor), not the teacher or the family.
        expect(sentQuran($this->supervisor->id, 'quran.exam_suggested'))->toBe(1)
            ->and(sentQuran($this->admin->id, 'quran.exam_suggested'))->toBe(1)
            ->and(sentQuran($this->teacher->id, 'quran.exam_suggested'))->toBe(0)
            ->and(sentQuran($this->guardian->id, 'quran.exam_suggested'))->toBe(0);

        // Once: the same stop again gives the open exam back.
        $again = app(SuggestQuranExam::class)->handle(QuranPlan::query()->with('program')->sole(), $this->student->id, $this->booked->enrollmentId, 'surah', 1);
        expect($again?->id)->toBe($exam->id)->and(QuranExam::query()->count())->toBe(1);
        // A stop must be one: a part past 30 or a surah past 114.
        expect(examRefusal(fn () => app(SuggestQuranExam::class)->handle(QuranPlan::query()->with('program')->sole(), $this->student->id, null, 'juz', 31)))->toHaveKey('stop');
    });
});

it('suggests nothing while the academy holds no exams, and the program\'s end once the scope is done', function () {
    examIn($this, fn () => app(SaveQuranSettings::class)->handle(['exams_enabled' => false]));
    passSurahOne($this);
    examIn($this, fn () => expect(QuranExam::query()->count())->toBe(0));

    // Exams back on: the last lesson ends the scope (surah 2, the program's end) and suggests both.
    examIn($this, fn () => app(SaveQuranSettings::class)->handle(['exams_enabled' => true]));
    $startsAt = examIn($this, fn () => Session::query()->findOrFail($this->sessions[1])->starts_at);
    CarbonImmutable::setTestNow(CarbonImmutable::parse($startsAt)->addMinutes(30));
    examIn($this, function () {
        app(RecordQuranLesson::class)->handle($this->sessions[1], $this->teacher->id, ['parts' => ['new' => []]]);
        expect(QuranExam::query()->orderBy('stop_kind')->get()->map(fn ($e) => [$e->stop_kind, $e->stop_number])->all())->toBe([['program', null], ['surah', 2]]);
    });
});

it('books the exam as an assessment lesson with an examiner who is not the course\'s teacher, under Scheduling\'s rules', function () {
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');

    examIn($this, function () {
        $exam = QuranExam::query()->sole();
        // Never the course's own teacher, nor the student; a teacher of the Quran other than theirs may (another teacher, the default).
        expect(examRefusal(fn () => bookExam($this, $exam, $this->teacher->id)))->toBe(['examiner_id' => [__('quran::exams.errors.examiner_own_teacher')]])
            ->and(examRefusal(fn () => bookExam($this, $exam, $this->student->id)))->toBe(['examiner_id' => [__('quran::exams.errors.examiner_student')]])
            ->and(examRefusal(fn () => bookExam($this, $exam, $this->guardian->id)))->toBe(['examiner_id' => [__('quran::exams.errors.examiner_not_examiner')]]);
        // Supervisors only: a teacher is not one; any teacher: the course's own too.
        app(SaveQuranSettings::class)->handle(['examiner' => 'supervisor']);
        expect(examRefusal(fn () => bookExam($this, $exam, $this->other->id)))->toBe(['examiner_id' => [__('quran::exams.errors.examiner_not_examiner')]]);
        app(SaveQuranSettings::class)->handle(['examiner' => 'any_teacher']);
        app(PeopleApi::class)->pauseForMinors($this->teacher->id, 'Checking', $this->admin->id);  // harmless: the student is an adult
        expect(fn () => DB::transaction(function () use ($exam) {
            bookExam($this, $exam, $this->teacher->id);
            throw new RuntimeException('roll back');
        }))->toThrow(RuntimeException::class, 'roll back');
        app(SaveQuranSettings::class)->handle(['examiner' => 'other_teacher']);

        // Scheduling's rules: not in the past, not twice at once (the student's own lesson then), not on a paused day.
        expect(examRefusal(fn () => bookExam($this, $exam, at: CarbonImmutable::parse('2026-10-06 08:00 UTC'))))->toHaveKey('starts_at');
        $lesson = Session::query()->findOrFail($this->sessions[1]);
        expect(examRefusal(fn () => bookExam($this, $exam, at: CarbonImmutable::parse($lesson->starts_at))))->toHaveKey('starts_at');
        app(EnrollmentApi::class)->activate($this->booked->enrollmentId, $this->admin->id);
        app(EnrollmentApi::class)->pause($this->booked->enrollmentId, CarbonImmutable::parse('2026-10-08'), CarbonImmutable::parse('2026-10-09'), 'Travel', CarbonImmutable::parse('2026-10-07'), $this->admin->id);
        expect(examRefusal(fn () => bookExam($this, $exam)))->toBe(['starts_at' => [__('scheduling::courses.errors.paused_then', ['until' => CarbonImmutable::parse('2026-10-09')->locale(app()->getLocale())->translatedFormat('j F')])]]);
        app(EnrollmentApi::class)->resume($this->booked->enrollmentId, true, $this->admin->id);
        // A child's exam goes only to an examiner People clears for children.
        app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2016-03-01'), null);
        app(PeopleApi::class)->pauseForMinors($this->other->id, 'Checking', $this->admin->id);
        expect(examRefusal(fn () => bookExam($this, $exam, $this->other->id)))->toHaveKey('teacher');
        expect(QuranExam::query()->sole()->status)->toBe('suggested')->and(Session::query()->where('type', 'assessment')->count())->toBe(0);

        $exam = bookExam($this, $exam);
        $session = Session::query()->findOrFail($exam->session_id);
        expect($exam->status)->toBe('booked')->and($exam->examiner_id)->toBe($this->supervisor->id)
            ->and($session->type->value)->toBe('assessment')->and($session->funding_status->value)->toBe('free')
            ->and($session->teacher_id)->toBe($this->supervisor->id)->and($session->enrollment_id)->toBe($this->booked->enrollmentId)
            ->and(CarbonImmutable::parse($session->starts_at)->equalTo(examTime()))->toBeTrue()
            ->and(json_decode(DB::table('audit_logs')->where('action', 'quran.exam_booked')->sole()->data, true))->toMatchArray(['stop_kind' => 'surah', 'stop_number' => 1]);
        // The family and the examiner hear of it.
        expect(sentQuran($this->student->id, 'quran.exam_booked'))->toBe(1)
            ->and(sentQuran($this->guardian->id, 'quran.exam_booked'))->toBe(1)
            ->and(sentQuran($this->supervisor->id, 'quran.exam_assigned'))->toBe(1);
        // Booked once.
        expect(examRefusal(fn () => bookExam($this, $exam, at: examTime()->addDay())))->toBe(['exam' => [__('quran::exams.errors.not_suggested')]]);
    });
});

it('lets the examiner alone record it: a fail sets a retake for its day, a pass earns a numbered certificate', function () {
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    $exam = examIn($this, fn () => bookExam($this, QuranExam::query()->sole()));

    // Not before the lesson; not anyone else.
    examApiAs($this->supervisor)->postJson("/api/v1/quran/exams/{$exam->id}/result", ['mistakes' => []])->assertUnprocessable()->assertJsonValidationErrors('exam');
    CarbonImmutable::setTestNow(examTime()->addMinutes(20));
    examApiAs($this->teacher)->postJson("/api/v1/quran/exams/{$exam->id}/result", ['mistakes' => []])->assertForbidden();
    examIn($this, fn () => expect(fn () => app(RecordQuranExam::class)->handle($exam, $this->admin->id, []))->toThrow(AuthorizationException::class));

    // The examiner's queue.
    $queue = examApiAs($this->supervisor)->getJson('/api/v1/quran/examiner/exams')->assertOk()->json('data');
    expect($queue['booked'])->toHaveCount(1)
        ->and($queue['booked'][0])->toMatchArray(['id' => $exam->id, 'status' => 'booked', 'student' => $this->student->name])
        ->and($queue['booked'][0]['stop'])->toBe(['kind' => 'surah', 'number' => 1, 'label' => 'Surah Al-Ula'])
        ->and(array_column($queue['mistake_types'], 'key'))->toBe(['wrong', 'hesitation', 'tajweed']);
    expect(examApiAs($this->other)->getJson('/api/v1/quran/examiner/exams')->assertOk()->json('data.booked'))->toBe([]);

    // Twelve wrong words over surah 1's pages: to repeat. A fail needs a note and a retake day.
    $fail = ['mistakes' => [['kind' => 'wrong', 'count' => 12]]];
    examApiAs($this->supervisor)->postJson("/api/v1/quran/exams/{$exam->id}/result", $fail)->assertUnprocessable()->assertJsonValidationErrors(['note', 'retake_on']);
    examApiAs($this->supervisor)->postJson("/api/v1/quran/exams/{$exam->id}/result", $fail + ['note' => 'Review verse 3', 'retake_on' => '2026-10-08'])
        ->assertUnprocessable()->assertJsonValidationErrors(['retake_on']);
    $failed = examApiAs($this->supervisor)->withHeader('Idempotency-Key', 'fail-1')->postJson("/api/v1/quran/exams/{$exam->id}/result", $fail + ['note' => 'Review verse 3', 'retake_on' => '2026-10-15'])
        ->assertCreated()->json('data');
    expect($failed['status'])->toBe('failed')
        ->and($failed['result'])->toMatchArray(['grade' => 'repeat', 'passed' => false, 'note' => 'Review verse 3', 'retake_on' => '2026-10-15']);

    examIn($this, function () use ($exam) {
        expect(json_decode(DB::table('audit_logs')->where('action', 'quran.exam_recorded')->sole()->data, true))
            ->toEqual(['stop_kind' => 'surah', 'stop_number' => 1, 'outcome' => 'failed', 'grade' => 'repeat'])
            ->and(sentQuran($this->guardian->id, 'quran.exam_failed'))->toBe(1)
            ->and(QuranCertificate::query()->count())->toBe(0);
        // The retake is suggested for its day and not booked before it.
        $retake = QuranExam::query()->where('status', 'suggested')->sole();
        expect($retake->retake_of)->toBe($exam->id)->and($retake->not_before?->toDateString())->toBe('2026-10-15');
        expect(examRefusal(fn () => bookExam($this, $retake, at: CarbonImmutable::parse('2026-10-13 16:00', 'Africa/Cairo'))))->toHaveKey('starts_at');
        $retake = bookExam($this, $retake, $this->other->id, CarbonImmutable::parse('2026-10-15 16:00', 'Africa/Cairo'));

        // The other teacher examines it: a good recitation with one slip passes, and earns the academy's first certificate.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 16:10', 'Africa/Cairo'));
        app(RecordQuranExam::class)->handle($retake, $this->other->id, ['mistakes' => ['hesitation' => 1]]);
        $certificate = QuranCertificate::query()->sole();
        expect($retake->fresh()->status)->toBe('passed')
            ->and($certificate)->toMatchArray(['exam_id' => $retake->id, 'student_id' => $this->student->id, 'number' => 'Q-2026-000001', 'seq' => 1,
                'riwaya' => 'hafs', 'grade' => $retake->fresh()->grade, 'examiner_id' => $this->other->id])
            ->and($certificate->portion)->toMatchArray(['kind' => 'surah', 'number' => 1, 'program' => 'Fixture hifz', 'track' => 'hifz'])
            ->and(CertificateNumber::normalize($certificate->code))->toBe($certificate->code)
            ->and(json_decode(DB::table('audit_logs')->where('action', 'quran.certificate_issued')->sole()->data, true))->toEqual(['number' => 'Q-2026-000001', 'exam_id' => $retake->id])
            ->and(sentQuran($this->guardian->id, 'quran.exam_passed'))->toBe(1)
            ->and(sentQuran($this->guardian->id, 'quran.certificate_issued'))->toBe(1);
        // The message carries each reader's own page.
        $body = (string) Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'quran.certificate_issued')->sole()->body;
        expect($body)->toContain('Q-2026-000001')->toContain('/quran/certificates/'.$certificate->id)->toContain('signature=');

        // Issued once an exam; the next one takes the next number.
        expect(app(IssueQuranCertificate::class)->handle($retake->fresh())->id)->toBe($certificate->id);
        $second = QuranExam::query()->create(['plan_id' => $retake->plan_id, 'program_id' => $retake->program_id, 'student_id' => $this->student->id,
            'stop_kind' => 'surah', 'stop_number' => 2, 'status' => 'passed', 'examiner_id' => $this->other->id, 'session_id' => $retake->session_id,
            'grade' => 'excellent', 'recorded_at' => now()]);
        $next = app(IssueQuranCertificate::class)->handle($second);
        expect($next->number)->toBe('Q-2026-000002')->and($next->seq)->toBe(2)->and($next->code)->not->toBe($certificate->code);
        // An issued certificate never changes but to revoke it.
        expect(fn () => $certificate->fill(['grade' => 'acceptable'])->save())->toThrow(LogicException::class)
            ->and(fn () => DB::table('quran_certificates')->where('id', $certificate->id)->update(['grade' => 'acceptable']))->toThrow(QueryException::class);
    });
});

it('gives no certificate while the academy gives none, or for a program that does not lead to one', function () {
    examIn($this, fn () => app(SaveQuranSettings::class)->handle(['certificates_enabled' => false]));
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    $exam = examIn($this, fn () => bookExam($this, QuranExam::query()->sole()));
    CarbonImmutable::setTestNow(examTime()->addMinutes(20));

    examIn($this, function () use ($exam) {
        app(RecordQuranExam::class)->handle($exam, $this->supervisor->id, ['mistakes' => []]);
        expect($exam->fresh()->status)->toBe('passed')->and($exam->fresh()->grade)->toBe('excellent')->and(QuranCertificate::query()->count())->toBe(0)
            ->and(examRefusal(fn () => app(IssueQuranCertificate::class)->handle($exam->fresh())))->toBe(['exam' => [__('quran::certificates.errors.off')]]);
        app(SaveQuranSettings::class)->handle(['certificates_enabled' => true]);
        app(SaveQuranProgram::class)->handle($this->program->fresh(), ['counts_for_certificate' => false]);
        expect(examRefusal(fn () => app(IssueQuranCertificate::class)->handle($exam->fresh())))->toBe(['exam' => [__('quran::certificates.errors.program')]]);
        // A passed stop is not suggested again on the same program.
        expect(app(SuggestQuranExam::class)->handle(QuranPlan::query()->with('program')->sole(), $this->student->id, null, 'surah', 1))->toBeNull();
    });
});

it('verifies a certificate publicly by its code, says when it is revoked, and serves its signed page', function () {
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    $exam = examIn($this, fn () => bookExam($this, QuranExam::query()->sole()));
    CarbonImmutable::setTestNow(examTime()->addMinutes(20));
    examIn($this, fn () => app(RecordQuranExam::class)->handle($exam, $this->supervisor->id, ['mistakes' => []]));
    $certificate = examIn($this, fn () => QuranCertificate::query()->sole());
    examIn($this, fn () => User::query()->whereKey($this->student->id)->update(['name' => 'Ziad Omar Hassan']));

    // Anyone, no sign-in, any case and dashes: the certificate, the academy, the student's first name and last initial.
    $code = strtolower(CertificateNumber::display($certificate->code));
    $public = examApiAs(null)->getJson("/api/v1/quran/certificates/{$code}")->assertOk()->json('data');
    expect(array_keys($public))->toBe(['number', 'title', 'portion', 'riwaya', 'grade', 'grade_label', 'issued_at', 'academy', 'student', 'revoked', 'revoked_at'])
        ->and($public)->toMatchArray(['number' => 'Q-2026-000001', 'title' => __('quran::certificates.title'), 'grade' => 'excellent', 'student' => 'Ziad H.', 'revoked' => false, 'revoked_at' => null])
        ->and($public['portion'])->toBe(['kind' => 'surah', 'number' => 1, 'label' => 'Surah Al-Ula'])
        ->and(json_encode($public))->not->toContain('Omar')->not->toContain($this->student->email);
    examApiAs(null)->getJson('/api/v1/quran/certificates/AAAA-BBBB-CCCC-DDDD')->assertNotFound();
    examApiAs(null)->getJson('/api/v1/quran/certificates/nope')->assertNotFound();

    // The signed page: the full name, the number, a QR code; without the signature, nothing.
    $path = URL::temporarySignedRoute('quran.certificate', now()->addDay(), ['certificate' => $certificate->id], absolute: false);
    $this->get('http://noor.lms.test'.$path)->assertOk()->assertSee('Ziad Omar Hassan')->assertSee('Q-2026-000001')->assertSee('<svg', false)
        ->assertSee(CertificateNumber::display($certificate->code));
    $this->get('http://noor.lms.test/quran/certificates/'.$certificate->id)->assertForbidden();

    // Revoked with a reason: audited by its number, never the reason; verifying says revoked; once.
    examIn($this, function () use ($certificate) {
        expect(examRefusal(fn () => app(RevokeQuranCertificate::class)->handle($certificate, $this->admin->id, '')))->toHaveKey('reason');
        app(RevokeQuranCertificate::class)->handle($certificate, $this->admin->id, 'Issued by mistake');
        expect(json_decode(DB::table('audit_logs')->where('action', 'quran.certificate_revoked')->sole()->data, true))->toBe(['number' => 'Q-2026-000001'])
            ->and(examRefusal(fn () => app(RevokeQuranCertificate::class)->handle($certificate->fresh(), $this->admin->id, 'Again')))->toHaveKey('certificate');
    });
    $revoked = examApiAs(null)->getJson('/api/v1/quran/certificates/'.$certificate->code)->assertOk()->json('data');
    expect($revoked['revoked'])->toBeTrue()->and($revoked['revoked_at'])->not->toBeNull()->and(json_encode($revoked))->not->toContain('mistake');
    $this->get('http://noor.lms.test'.$path)->assertOk()->assertSee('class="revoked"', false)->assertDontSee('<svg', false);
});

it('shows the family their exams and, while the academy allows it, their certificates; the kids\' look without counts', function () {
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    $exam = examIn($this, fn () => bookExam($this, QuranExam::query()->sole()));
    CarbonImmutable::setTestNow(examTime()->addMinutes(20));
    examIn($this, fn () => app(RecordQuranExam::class)->handle($exam, $this->supervisor->id, ['mistakes' => ['tajweed' => 1]]));

    $exams = examApiAs($this->guardian)->getJson('/api/v1/quran/exams')->assertOk()->json('data');
    expect($exams['learner_id'])->toBe($this->student->id)
        ->and($exams['exams'])->toHaveCount(1)
        ->and($exams['exams'][0])->not->toHaveKey('cancel_reason')
        ->and($exams['exams'][0]['result'])->not->toHaveKey('computed')
        ->and($exams['exams'][0]['result']['mistakes'])->toBe([['kind' => 'tajweed', 'label' => 'Tajweed', 'count' => 1]])
        ->and($exams['exams'][0]['examiner']['id'])->toBe($this->supervisor->id);

    $certificates = examApiAs($this->guardian)->getJson('/api/v1/quran/certificates')->assertOk()->json('data');
    expect($certificates['shown'])->toBeTrue()->and($certificates['certificates'])->toHaveCount(1)
        ->and($certificates['certificates'][0])->toMatchArray(['number' => 'Q-2026-000001', 'revoked' => false])
        ->and($certificates['certificates'][0]['url'])->toContain('/quran/certificates/')->toContain('signature=');
    // Another family's learner is nobody's here.
    $stranger = examIn($this, fn () => User::factory()->withRole(Role::Guardian)->create());
    examApiAs($stranger)->getJson('/api/v1/quran/exams?learner_id='.$this->student->id)->assertNotFound();

    // Kept from families: shown false, nothing listed.
    examIn($this, fn () => app(SaveQuranSettings::class)->handle(['certificates_family' => false]));
    expect(examApiAs($this->guardian)->getJson('/api/v1/quran/certificates')->assertOk()->json('data'))->toMatchArray(['shown' => false, 'certificates' => []]);

    // The kids' look: grades in words, no counts.
    examIn($this, fn () => app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2004-05-01'), LearnerStage::Kids));
    expect(examApiAs($this->student)->getJson('/api/v1/quran/exams')->assertOk()->json('data.exams.0.result'))->toMatchArray(['grade' => 'excellent', 'mistakes' => []]);
});

it('cancels an open exam with its assessment lesson, audited without the reason', function () {
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    examIn($this, function () {
        $exam = bookExam($this, QuranExam::query()->sole());
        expect(examRefusal(fn () => app(CancelQuranExam::class)->handle($exam, $this->admin->id, 'x')))->toHaveKey('reason');
        app(CancelQuranExam::class)->handle($exam, $this->admin->id, 'Student travelling');
        expect($exam->fresh())->toMatchArray(['status' => 'cancelled', 'cancel_reason' => 'Student travelling'])
            ->and(Session::query()->findOrFail($exam->session_id)->status->value)->toBe('cancelled')
            ->and(json_decode(DB::table('audit_logs')->where('action', 'quran.exam_cancelled')->sole()->data, true))->toEqual(['stop_kind' => 'surah', 'stop_number' => 1])
            ->and(examRefusal(fn () => app(CancelQuranExam::class)->handle($exam->fresh(), $this->admin->id, 'Again')))->toHaveKey('exam');
        // The stop may be suggested again.
        expect(app(SuggestQuranExam::class)->handle(QuranPlan::query()->with('program')->sole(), $this->student->id, $this->booked->enrollmentId, 'surah', 1)?->id)->not->toBe($exam->id);
    });
});

it('closes exams and certificates without the Quran in the plan: 403, nothing written, what it holds kept', function () {
    passSurahOne($this);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    $exam = examIn($this, fn () => bookExam($this, QuranExam::query()->sole()));
    app(TenantContext::class)->clear();
    $plan = SaasPlan::query()->create(['code' => 'no-quran', 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    CarbonImmutable::setTestNow(examTime()->addMinutes(20));

    examApiAs($this->supervisor)->getJson('/api/v1/quran/examiner/exams')->assertForbidden()->assertJsonPath('type', fn ($t) => str_contains((string) $t, 'feature-not-in-plan'));
    examApiAs($this->supervisor)->postJson("/api/v1/quran/exams/{$exam->id}/result", ['mistakes' => []])->assertForbidden();
    examApiAs($this->guardian)->getJson('/api/v1/quran/exams')->assertForbidden();
    examApiAs($this->guardian)->getJson('/api/v1/quran/certificates')->assertForbidden();
    examApiAs(null)->getJson('/api/v1/quran/certificates/AAAABBBBCCCCDDDD')->assertForbidden();
    examIn($this, function () use ($exam) {
        expect(fn () => app(RecordQuranExam::class)->handle($exam, $this->supervisor->id, ['mistakes' => []]))->toThrow(FeatureNotInPlan::class)
            ->and(fn () => app(CancelQuranExam::class)->handle($exam, $this->admin->id, 'No Quran now'))->toThrow(FeatureNotInPlan::class)
            ->and(fn () => app(SuggestQuranExam::class)->handle(QuranPlan::query()->with('program')->sole(), $this->student->id, null, 'surah', 2))->toThrow(FeatureNotInPlan::class);
        expect(QuranExam::query()->sole()->status)->toBe('booked')->and(QuranCertificate::query()->count())->toBe(0);
    });
});
