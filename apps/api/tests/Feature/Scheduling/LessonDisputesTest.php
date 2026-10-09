<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\RecordPayment;
use Modules\Communication\Models\Notification;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Models\TeacherEarning;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\Disputes\DecideDispute;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionDispute;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Pages\LessonDisputes;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Teacher\MyWeek;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Disputes over a lesson's outcome (docs/architecture/01, section 8; 02,
 * section 5). The fixture: a London student with a Cairo teacher, Tuesdays
 * and Saturdays 18:00 Cairo for 30 minutes from 6 October 2026, paid; the
 * teacher's card pays 120 EGP an hour.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    app(SaveRateCard::class)->handle(null, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [['unit' => 'hour', 'amount' => '120']]]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The lesson on that Cairo day at 18:00, held: both stay the whole time (teacher false: only the student comes). */
function disputedLesson(string $cairoDay, bool $teacher = true): Session
{
    $s = Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('starts_at', CarbonImmutable::parse($cairoDay.' 18:00', 'Africa/Cairo')->utc())->firstOrFail();
    CarbonImmutable::setTestNow($s->starts_at);
    foreach (array_filter([test()->student->id, $teacher ? $s->teacher_id : null]) as $who) {
        app(RecordParticipantJoin::class)->handle($s->refresh(), $who, $s->starts_at);
        app(RecordParticipantLeave::class)->handle($s->refresh(), $who, $s->ends_at);
    }
    CarbonImmutable::setTestNow($s->ends_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();

    return $s->refresh();
}

function disputeAccount(): CreditAccount
{
    return CreditAccount::query()->findOrFail(DB::table('enrollments')->where('id', test()->booked->enrollmentId)->value('credit_account_id'));
}

function disputeEntries(string $sessionId, string $type)
{
    return CreditEntry::query()->where('session_id', $sessionId)->where('type', $type)->get();
}

function livePay(string $sessionId): int
{
    return (int) TeacherEarning::query()->where('session_id', $sessionId)->sum('amount_minor');
}

function heard(User $user, string $template): int
{
    return Notification::query()->where('user_id', $user->id)->where('template_key', $template)->count();
}

it('lets the family dispute a held lesson: it waits for review, its pay waits, and the academy hears of it', function () {
    $lesson = disputedLesson('2026-10-06');
    expect($lesson->status->value)->toBe('completed')->and(livePay($lesson->id))->toBe(6000);

    $dispute = app(DisputeLesson::class)->handle($lesson, 'family', $this->guardian->id, 'teacher_absent', '  The teacher never turned on the camera and left after five minutes.  ');

    expect($dispute->only(['side', 'reason', 'status', 'outcome_before', 'raised_by']))->toBe([
        'side' => 'family', 'reason' => 'teacher_absent', 'status' => 'open', 'outcome_before' => 'completed', 'raised_by' => $this->guardian->id,
    ])->and($dispute->note)->toBe('The teacher never turned on the camera and left after five minutes.')
        ->and($lesson->refresh()->status->value)->toBe('needs_review')
        ->and($lesson->review_reason)->toBe('dispute')
        ->and(livePay($lesson->id))->toBe(0)                                    // the line is reversed while it is under review
        ->and(heard($this->admin, 'scheduling.dispute_opened'))->toBe(1)
        ->and(heard($this->guardian, 'scheduling.dispute_opened'))->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'session.disputed')->where('subject_id', $lesson->id)->count())->toBe(1);
});

it('gives the minutes back as makeup when the family\'s dispute is upheld; the teacher hears the lesson changed', function () {
    $lesson = disputedLesson('2026-10-06');
    $before = disputeAccount();
    $dispute = app(DisputeLesson::class)->handle($lesson, 'family', $this->student->id, 'teacher_absent', 'Nobody came.');

    $decided = app(DecideDispute::class)->handle($dispute, 'teacher_absent', 'The recording shows the teacher was not there. You have a makeup lesson.', $this->admin->id);
    $after = disputeAccount();
    $lot = CreditLot::query()->where('source', 'makeup')->where('source_id', $lesson->id)->sole();

    expect($decided->only(['status', 'outcome_after', 'decided_by']))->toBe(['status' => 'upheld', 'outcome_after' => 'teacher_absent', 'decided_by' => $this->admin->id])
        ->and($decided->decision_note)->toBe('The recording shows the teacher was not there. You have a makeup lesson.')
        ->and($lesson->refresh()->status->value)->toBe('teacher_absent')
        ->and($lot->granted_min)->toBe(30)
        ->and($after->available_min - $before->available_min)->toBe(30)
        ->and($before->consumed_min - $after->consumed_min)->toBe(30)
        ->and(disputeEntries($lesson->id, 'restore')->sole()->minutes)->toBe(30)
        ->and(livePay($lesson->id))->toBe(0)
        // The family reads the answer; the teacher hears the lesson changed, without the family's words or the answer.
        ->and(heard($this->guardian, 'scheduling.dispute_decided'))->toBe(1)
        ->and(heard($this->student, 'scheduling.dispute_decided'))->toBe(1)
        ->and(heard($this->teacher, 'scheduling.lesson_corrected_teacher'))->toBe(1);
});

it('takes makeup back when the teacher was there after all; makeup the family already booked stays theirs', function () {
    $absent = disputedLesson('2026-10-06', teacher: false);
    expect($absent->status->value)->toBe('teacher_absent');
    $before = disputeAccount();
    $dispute = app(DisputeLesson::class)->handle($absent, 'teacher', $this->teacher->id, 'teacher_attended', 'I was there.');
    app(DecideDispute::class)->handle($dispute, 'completed', 'Confirmed from the room log.', $this->admin->id);

    expect(CreditLot::query()->where('source', 'makeup')->where('source_id', $absent->id)->sole()->remaining_min)->toBe(0)
        ->and($before->available_min - disputeAccount()->available_min)->toBe(30)
        ->and(disputeEntries($absent->id, 'reclaim')->sole()->minutes)->toBe(30)
        ->and($absent->refresh()->decision)->not->toHaveKey('credit_short')
        ->and(livePay($absent->id))->toBe(6000)
        ->and(heard($this->teacher, 'scheduling.dispute_decided_teacher'))->toBe(1)
        ->and(heard($this->guardian, 'scheduling.lesson_corrected'))->toBe(1);

    // This time the family booked its makeup before the academy decided.
    $second = disputedLesson('2026-10-10', teacher: false);
    $lot = CreditLot::query()->where('source', 'makeup')->where('source_id', $second->id)->sole();
    app(BookMakeup::class)->handle($this->booked->enrollmentId, CarbonImmutable::parse('2026-10-13 19:00', 'Africa/Cairo'), $this->admin->id, $second->id);
    expect($lot->refresh()->remaining_min)->toBe(0);

    $dispute = app(DisputeLesson::class)->handle($second, 'teacher', $this->teacher->id, 'teacher_attended', 'I was in the room; the camera did not start.');
    $mid = disputeAccount();
    app(DecideDispute::class)->handle($dispute, 'completed', 'The room log shows you there for the whole lesson.', $this->admin->id);

    expect($second->refresh()->status->value)->toBe('completed')
        ->and($second->decision['credit_short'])->toBe(30)
        ->and(disputeEntries($second->id, 'reclaim')->sole()->minutes)->toBe(0)
        ->and(disputeAccount()->available_min)->toBe($mid->available_min)
        ->and(livePay($second->id))->toBe(6000);
});

it('keeps credit and pay as they were when a dispute is rejected, and answers only whoever disputed', function () {
    $lesson = disputedLesson('2026-10-06');
    $before = disputeAccount();
    $dispute = app(DisputeLesson::class)->handle($lesson, 'family', $this->guardian->id, 'cut_short', 'It ended ten minutes early.');

    // The board's own "decide" needs an answer while a dispute waits on the lesson.
    expect(fn () => app(ResolveReview::class)->handle($lesson->refresh(), 'completed', '  ', $this->admin->id))->toThrow(ValidationException::class)
        ->and(fn () => app(DecideDispute::class)->handle($dispute, 'completed', '', $this->admin->id))->toThrow(ValidationException::class);

    app(DecideDispute::class)->handle($dispute, 'completed', 'Both stayed the full thirty minutes.', $this->admin->id);

    expect($dispute->refresh()->status)->toBe('rejected')
        ->and($lesson->refresh()->status->value)->toBe('completed')
        ->and(disputeAccount()->only(['available_min', 'consumed_min']))->toBe($before->only(['available_min', 'consumed_min']))
        ->and(CreditEntry::query()->where('session_id', $lesson->id)->whereIn('type', ['restore', 'reclaim'])->count())->toBe(0)
        ->and(livePay($lesson->id))->toBe(6000)
        ->and(heard($this->guardian, 'scheduling.dispute_decided'))->toBe(1)
        ->and(heard($this->teacher, 'scheduling.lesson_corrected_teacher'))->toBe(0)
        ->and(fn () => app(DecideDispute::class)->handle($dispute, 'teacher_absent', 'Again', $this->admin->id))->toThrow(ValidationException::class)
        // Once per lesson, whoever raised it.
        ->and(fn () => app(DisputeLesson::class)->handle($lesson->refresh(), 'teacher', $this->teacher->id, 'other', 'Me too.'))->toThrow(ValidationException::class, __('scheduling::disputes.errors.already'));
});

it('takes a dispute only from the lesson\'s family or teacher, out of the kids\' look, about a decided lesson, within 48 hours', function () {
    $lesson = disputedLesson('2026-10-06');
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $dispute = fn (string $side, User $who, string $reason = 'other') => app(DisputeLesson::class)->handle($lesson->refresh(), $side, $who->id, $reason, 'Something was wrong.');

    expect(fn () => $dispute('family', $stranger))->toThrow(AccessDeniedHttpException::class)
        ->and(fn () => $dispute('teacher', $this->other))->toThrow(AccessDeniedHttpException::class)
        ->and(fn () => $dispute('family', $this->teacher))->toThrow(AccessDeniedHttpException::class)
        // A reason that does not fit what the lesson was: "the student was there" about a held lesson.
        ->and(fn () => $dispute('family', $this->guardian, 'student_attended'))->toThrow(ValidationException::class, __('scheduling::disputes.errors.reason'))
        ->and(fn () => app(DisputeLesson::class)->handle($lesson, 'family', $this->guardian->id, 'other', str_repeat('a', DisputeLesson::NOTE_MAX + 1)))->toThrow(ValidationException::class);

    // A lesson still ahead cannot be disputed.
    $ahead = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->where('status', 'scheduled')->orderBy('starts_at')->firstOrFail();
    expect(fn () => app(DisputeLesson::class)->handle($ahead, 'family', $this->guardian->id, 'other', 'Not yet.'))->toThrow(ValidationException::class);

    // A child in the kids' look cannot; their guardian can.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2019-03-01'), null);
    expect(fn () => $dispute('family', $this->student))->toThrow(AccessDeniedHttpException::class);

    // 48 hours after the end, it is too late.
    CarbonImmutable::setTestNow($lesson->ends_at->addHours(48)->addMinute());
    expect(fn () => $dispute('family', $this->guardian))->toThrow(ValidationException::class, __('scheduling::disputes.errors.closed', ['hours' => 48]));

    CarbonImmutable::setTestNow($lesson->ends_at->addHours(47));
    expect($dispute('family', $this->guardian))->toBeInstanceOf(SessionDispute::class);
});

it('lets the family dispute from /my, the teacher from /teach, and the academy decide on its disputes page', function () {
    $lesson = disputedLesson('2026-10-06');

    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');
    Livewire::test(FamilyLessons::class)
        ->assertActionVisible(TestAction::make('dispute')->arguments(['session' => $lesson->id]))
        ->callAction(TestAction::make('dispute')->arguments(['session' => $lesson->id]), data: ['reason' => 'cut_short', 'note' => 'The teacher left after twenty minutes.'])
        ->assertHasNoActionErrors()
        ->assertSee(__('scheduling::disputes.form.state', ['status' => __('scheduling::disputes.statuses.open')]));
    $dispute = SessionDispute::query()->where('session_id', $lesson->id)->sole();
    expect($dispute->only(['side', 'reason', 'raised_by']))->toBe(['side' => 'family', 'reason' => 'cut_short', 'raised_by' => $this->guardian->id]);

    // The teacher sees the lesson under review, not the family's words, and cannot dispute it now.
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    $week = Livewire::test(MyWeek::class)->assertDontSee('The teacher left after twenty minutes.')->instance()->week;
    expect(collect($week['days'])->flatMap(fn (array $d) => $d['lessons'])->firstWhere('id', $lesson->id))->toMatchArray(['disputable' => false, 'dispute' => null]);

    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/disputes')->assertOk()
        ->assertSee('The teacher left after twenty minutes.')
        ->assertSee(__('scheduling::disputes.reasons.family.cut_short'));
    $this->actAsTenant($this->academy);     // the request above leaves no academy behind
    Filament::setCurrentPanel('academy');
    Livewire::test(LessonDisputes::class, ['dispute' => $dispute->id])
        ->assertActionVisible(TestAction::make('decide')->arguments(['id' => $dispute->id]))
        ->callAction(TestAction::make('decide')->arguments(['id' => $dispute->id]), data: ['outcome' => 'completed', 'answer' => 'The room shows both of you there for 30 minutes.'])
        ->assertHasNoFormErrors()
        ->assertNotified(__('scheduling::disputes.decide.done.rejected'))
        ->assertSee('The room shows both of you there for 30 minutes.');
    expect($dispute->refresh()->status)->toBe('rejected');

    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');
    $mine = Livewire::test(FamilyLessons::class)->assertSee('The room shows both of you there for 30 minutes.')->instance()->lessons;
    expect(collect($mine['upcoming'])->firstWhere('id', $lesson->id))->toMatchArray(['disputable' => false])
        ->and(collect($mine['upcoming'])->firstWhere('id', $lesson->id)['dispute']['status'])->toBe('rejected');

    // The teacher's own dispute, on the next lesson, from their week.
    $absent = disputedLesson('2026-10-10', teacher: false);
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(MyWeek::class)
        ->callAction(TestAction::make('dispute')->arguments(['session' => $absent->id]), data: ['reason' => 'teacher_attended', 'note' => 'My camera failed; I was there.'])
        ->assertHasNoActionErrors();
    expect(SessionDispute::query()->where('session_id', $absent->id)->sole()->side)->toBe('teacher');

    // The board's own "decide" asks for the answer while a dispute waits on the lesson.
    $other = disputedLesson('2026-10-13');
    app(DisputeLesson::class)->handle($other, 'family', $this->guardian->id, 'cut_short', 'Short again.');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(LiveBoard::class)
        ->callAction(TestAction::make('decide')->arguments(['session' => $other->id]), data: ['outcome' => 'completed'])
        ->assertHasFormErrors(['note' => 'required']);
    expect($other->refresh()->status->value)->toBe('needs_review');

    // Someone who may only watch the board sees the page but cannot decide.
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $this->actingAs($supervisor);
    Filament::setCurrentPanel('academy');
    $canDecide = Gate::forUser($supervisor)->allows(Ability::ManageSessions->value);
    $page = Livewire::test(LessonDisputes::class);
    $canDecide
        ? $page->assertActionVisible(TestAction::make('decide')->arguments(['id' => SessionDispute::query()->where('status', 'open')->value('id')]))
        : $page->assertActionHidden(TestAction::make('decide')->arguments(['id' => SessionDispute::query()->where('status', 'open')->value('id')]));
});

it('takes disputes from the apps: each side about its own lessons, once, with what it may still do', function () {
    $lesson = disputedLesson('2026-10-06');
    $app = function (User $user): string {
        $token = $user->createToken('phone')->plainTextToken;
        app('auth')->forgetGuards();

        return $token;
    };
    [$guardian, $teacher, $other] = [$app($this->guardian), $app($this->teacher), $app($this->other)];
    $this->clearTenant();

    $today = $this->withToken($guardian)->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/family/lessons')->assertOk()->json('data');
    $row = collect($today)->firstWhere('id', $lesson->id);
    expect($row['can_dispute'])->toBeTrue()
        ->and(array_column($row['dispute_reasons'], 'key'))->toBe(['teacher_absent', 'cut_short', 'technical', 'other'])
        ->and($row['dispute_until']['utc'])->toBe($lesson->ends_at->addHours(48)->toIso8601ZuluString())
        ->and($row['dispute'])->toBeNull();

    // Each call signs in afresh (the guard keeps whoever came last).
    $as = function (string $token) {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    };
    $send = fn (string $token, string $path, array $body) => $as($token)->withHeaders(['Idempotency-Key' => (string) Str::uuid(), 'Accept-Language' => 'en'])->postJson($path, $body);
    $send($other, "/api/v1/teacher/lessons/{$lesson->id}/dispute", ['reason' => 'other', 'note' => 'Not mine'])->assertNotFound();
    $send($guardian, "/api/v1/lessons/{$lesson->id}/dispute", ['reason' => 'nonsense', 'note' => 'x'])->assertUnprocessable();
    $answer = $send($guardian, "/api/v1/lessons/{$lesson->id}/dispute", ['reason' => 'teacher_absent', 'note' => 'Nobody came.'])->assertOk()->json('data');
    expect($answer['status'])->toBe('needs_review')
        ->and($answer['can_dispute'])->toBeFalse()
        ->and($answer['dispute'])->toMatchArray(['status' => 'open', 'reason' => 'The teacher did not come', 'answer' => null]);
    $send($guardian, "/api/v1/lessons/{$lesson->id}/dispute", ['reason' => 'teacher_absent', 'note' => 'Again.'])->assertUnprocessable();

    // The teacher's list shows the lesson under review, without the family's dispute.
    $week = $as($teacher)->getJson('/api/v1/teacher/lessons?from=2026-10-06&days=1')->assertOk();
    $mine = collect($week->json('data'))->firstWhere('id', $lesson->id);
    expect($mine)->not->toBeNull($week->getContent());
    expect($mine['status'])->toBe('needs_review')->and($mine['dispute'])->toBeNull()->and($mine['can_dispute'])->toBeFalse();

    // The teacher disputes the next lesson, recorded as their absence.
    $this->actAsTenant($this->academy);
    $absent = disputedLesson('2026-10-10', teacher: false);
    $this->clearTenant();
    $theirs = $send($teacher, "/api/v1/teacher/lessons/{$absent->id}/dispute", ['reason' => 'teacher_attended', 'note' => 'I was there.'])->assertOk()->json('data');
    expect($theirs['dispute']['status'])->toBe('open');
});
