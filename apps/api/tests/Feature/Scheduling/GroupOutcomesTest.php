<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Models\Notification;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Disputes\DecideDispute;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Actions\Groups\CreateGroup;
use Modules\Scheduling\Actions\MarkStudentAbsent;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\NewGroup;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionDispute;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Tests\Support\GroupLessons;

uses(GroupLessons::class);

/*
 * Each student's outcome in a group lesson (docs/architecture/02, "الحصص
 * الجماعية"): attendance and credit per seat, the teacher's absence owing
 * everyone a makeup, "I won't attend" excusing one seat under the notice
 * rule, and a dispute about one student's seat decided for them alone.
 * The fixture's first group lesson is Tuesday 6 October, 18:00–18:30 Cairo.
 */

beforeEach(function () {
    $this->setUpGroups();
    $this->group = $this->group();
    [$this->a, $this->b] = [$this->student(), $this->student()];
    $this->ja = $this->join($this->group, $this->a);
    $this->jb = $this->join($this->group, $this->b);
    $this->pay($this->ja);
    $this->pay($this->jb);
    $this->lesson = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->firstOrFail();
    $this->account = fn (string $enrollmentId) => DB::table('enrollments')->where('id', $enrollmentId)->value('credit_account_id');
    $this->held = fn (string $enrollmentId) => CreditReservation::query()->where('session_id', $this->lesson->id)->where('account_id', ($this->account)($enrollmentId))->value('status');
    $this->seat = fn (string $studentId) => SessionParticipant::query()->where('session_id', $this->lesson->id)->where('user_id', $studentId)->value('outcome');
});

afterEach(fn () => CarbonImmutable::setTestNow());

$join = fn (Session $s, string $userId, int $minutesAfterStart) => app(AttendanceApi::class)->participantJoined($s->id, $userId, $s->starts_at->addMinutes($minutesAfterStart));

$refusal = function (Closure $do): string {
    try {
        $do();
    } catch (ValidationException $e) {
        return (string) collect($e->errors())->flatten()->first();
    }

    return '';
};

it('decides each student on their own presence: one came, one did not; the lesson was held', function () use ($join) {
    Event::fake([SessionFinalized::class]);
    expect(($this->held)($this->ja->enrollmentId))->toBe('held')->and(($this->held)($this->jb->enrollmentId))->toBe('held');

    $join($this->lesson, $this->teacher->id, 0);
    $join($this->lesson, $this->a->id, 1);
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));

    $lesson = $this->lesson->refresh();
    expect($lesson->status)->toBe(SessionStatus::Completed)
        ->and(($this->seat)($this->a->id))->toBe('attended')
        ->and(($this->seat)($this->b->id))->toBe('absent')
        ->and(($this->held)($this->ja->enrollmentId))->toBe('consumed')
        ->and(($this->held)($this->jb->enrollmentId))->toBe('consumed')       // rule 5: an absence uses the lesson
        ->and($lesson->decision['teacher_pay_pct'])->toBe(100)
        ->and(collect($lesson->decision['seats'])->pluck('outcome', 'student_id')->all())->toBe([$this->a->id => 'attended', $this->b->id => 'absent']);
    Event::assertDispatchedTimes(SessionFinalized::class, 1);
    Event::assertDispatched(SessionFinalized::class, fn (SessionFinalized $e) => $e->outcome === 'completed' && count($e->decision['seats']) === 2);
});

it('owes every student a makeup when the teacher never came', function () use ($join) {
    $join($this->lesson, $this->a->id, 0);
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->starts_at->addMinutes(10));

    expect($this->lesson->refresh()->status)->toBe(SessionStatus::TeacherAbsent)
        ->and(($this->seat)($this->a->id))->toBe('makeup')
        ->and(($this->seat)($this->b->id))->toBe('makeup')
        ->and(($this->held)($this->ja->enrollmentId))->toBe('released')
        ->and(($this->held)($this->jb->enrollmentId))->toBe('released')
        ->and(CreditLot::query()->where('source', 'makeup')->where('source_id', $this->lesson->id)->pluck('account_id')->sort()->values()->all())
        ->toBe(collect([($this->account)($this->ja->enrollmentId), ($this->account)($this->jb->enrollmentId)])->sort()->values()->all());
});

it('closes as the students\' absence when nobody came by the end of the wait, and waits for review when nobody joined at all', function () use ($join) {
    $join($this->lesson, $this->teacher->id, 0);
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->starts_at->addMinutes(16));
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::StudentAbsent)
        ->and(($this->seat)($this->a->id))->toBe('absent')->and(($this->seat)($this->b->id))->toBe('absent');

    $next = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->skip(1)->firstOrFail();
    app(ApplyAttendanceDeadlines::class)->handle($next->starts_at->addMinutes(10));
    expect($next->refresh()->status)->toBe(SessionStatus::NeedsReview)->and($next->review_reason)->toBe('nobody_joined');
});

it('marks one student absent while the lesson goes on for the others', function () use ($join, $refusal) {
    $join($this->lesson, $this->teacher->id, 0);
    $join($this->lesson, $this->a->id, 0);

    expect($refusal(fn () => app(MarkStudentAbsent::class)->handle($this->lesson->refresh(), $this->admin->id)))->toBe(__('scheduling::group_outcomes.errors.no_seat'));
    app(MarkStudentAbsent::class)->handle($this->lesson->refresh(), $this->admin->id, studentId: $this->b->id, at: $this->lesson->starts_at->addMinutes(5));
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::InProgress)
        ->and(($this->seat)($this->b->id))->toBe('absent')
        ->and(($this->held)($this->jb->enrollmentId))->toBe('consumed')
        ->and(($this->held)($this->ja->enrollmentId))->toBe('held');

    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Completed)->and(($this->seat)($this->a->id))->toBe('attended');
});

it('sends a group lesson to review when a student in it was taught too little', function () {
    $api = app(AttendanceApi::class);
    $api->participantJoined($this->lesson->id, $this->teacher->id, $this->lesson->starts_at);
    $api->participantJoined($this->lesson->id, $this->a->id, $this->lesson->starts_at);
    $api->participantJoined($this->lesson->id, $this->b->id, $this->lesson->starts_at);
    $api->participantLeft($this->lesson->id, $this->b->id, $this->lesson->starts_at->addMinutes(10));    // 10 of 30

    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::NeedsReview)->and($this->lesson->review_reason)->toBe('short_delivery');

    // Decided seat by seat: the lesson leaves review once both are.
    app(ResolveReview::class)->handle($this->lesson, 'completed', null, $this->admin->id, $this->a->id);
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::NeedsReview)->and(($this->seat)($this->a->id))->toBe('attended');
    app(ResolveReview::class)->handle($this->lesson, 'student_absent', 'Left early', $this->admin->id, $this->b->id);
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Completed)
        ->and(($this->seat)($this->b->id))->toBe('absent')
        ->and(($this->held)($this->jb->enrollmentId))->toBe('consumed');
});

it('excuses one seat in time: the minutes come back, the lesson stays for the others', function () {
    CarbonImmutable::setTestNow($this->lesson->starts_at->subDays(2));
    $before = DB::table('credit_accounts')->where('id', ($this->account)($this->ja->enrollmentId))->value('available_min');

    app(CancelSession::class)->handle($this->lesson, 'student', 'travel', null, $this->payer->id, studentId: $this->a->id);

    $lesson = $this->lesson->refresh();
    expect($lesson->status)->toBe(SessionStatus::Scheduled)
        ->and(($this->seat)($this->a->id))->toBe('excused')
        ->and(SessionParticipant::query()->where('session_id', $lesson->id)->where('user_id', $this->a->id)->value('is_active'))->toBeFalse()
        ->and(($this->seat)($this->b->id))->toBeNull()
        ->and(($this->held)($this->jb->enrollmentId))->toBe('held')
        ->and(CreditReservation::query()->where('session_id', $lesson->id)->where('account_id', ($this->account)($this->ja->enrollmentId))->value('status'))->toBe('released');
    // The 30 minutes came back and funded A's next seat that was waiting, if any; either way nothing was used.
    expect(DB::table('credit_accounts')->where('id', ($this->account)($this->ja->enrollmentId))->value('consumed_min'))->toBe(0)
        ->and($before)->toBeInt();

    // At the lesson: B alone; A's excuse stands.
    app(AttendanceApi::class)->participantJoined($lesson->id, $this->teacher->id, $lesson->starts_at);
    app(AttendanceApi::class)->participantJoined($lesson->id, $this->b->id, $lesson->starts_at);
    app(ApplyAttendanceDeadlines::class)->handle($lesson->ends_at->addMinutes(10));
    expect($lesson->refresh()->status)->toBe(SessionStatus::Completed)
        ->and(collect($lesson->decision['seats'])->pluck('outcome', 'student_id')->all())->toBe([$this->a->id => 'excused', $this->b->id => 'attended']);
});

it('excuses a seat late: the lesson counts as taken for that student', function () {
    CarbonImmutable::setTestNow($this->lesson->starts_at->subHours(2));
    expect(app(CancelSession::class)->preview($this->lesson, 'student', 'travel', studentId: $this->a->id)->credit)->toBe('forfeit');

    app(CancelSession::class)->handle($this->lesson, 'student', 'illness', null, $this->payer->id, studentId: $this->a->id);
    expect(($this->seat)($this->a->id))->toBe('excused')
        ->and(($this->held)($this->ja->enrollmentId))->toBe('consumed')
        ->and($this->lesson->refresh()->status)->toBe(SessionStatus::Scheduled);
});

it('excuses through the family app, never cancelling the group lesson', function () {
    CarbonImmutable::setTestNow($this->lesson->starts_at->subDays(2));
    $token = $this->payer->createToken('phone')->plainTextToken;

    $this->withToken($token)->withHeaders(['Accept-Language' => 'en'])->getJson("/api/v1/lessons/{$this->lesson->id}/cancellation?learner_id={$this->b->id}")
        ->assertOk()->assertJsonPath('excuse_only', true)
        ->assertJsonPath('outcome', fn (string $t) => str_contains($t, 'goes on for the group'));
    $this->withToken($token)->withHeaders(['Idempotency-Key' => 'x-1'])
        ->postJson("/api/v1/lessons/{$this->lesson->id}/cancel", ['reason' => 'travel', 'learner_id' => $this->b->id])->assertOk();
    $this->actAsTenant($this->academy);

    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Scheduled)
        ->and(($this->seat)($this->b->id))->toBe('excused')
        ->and(($this->seat)($this->a->id))->toBeNull();
});

it('disputes one student\'s seat and decides it for them alone', function () use ($join, $refusal) {
    $join($this->lesson, $this->teacher->id, 0);
    $join($this->lesson, $this->a->id, 0);
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));
    CarbonImmutable::setTestNow($this->lesson->ends_at->addHour());

    // The family disputes B's absence: about B's seat, named by the learner they follow.
    $dispute = app(DisputeLesson::class)->handle($this->lesson->refresh(), 'family', $this->payer->id, 'student_attended', 'He was there, the camera failed.', $this->b->id);
    expect($dispute->student_id)->toBe($this->b->id)
        ->and($dispute->outcome_before)->toBe('student_absent')
        ->and($this->lesson->refresh()->status)->toBe(SessionStatus::NeedsReview)
        ->and($refusal(fn () => app(DisputeLesson::class)->handle($this->lesson->refresh(), 'family', $this->payer->id, 'student_attended', 'Again', $this->b->id)))
        ->toBe(__('scheduling::disputes.errors.already'));

    // While it waits, A's seat may be disputed too (by the teacher, naming it).
    $other = app(DisputeLesson::class)->handle($this->lesson->refresh(), 'teacher', $this->teacher->id, 'student_absent', 'A was not really there.', $this->a->id);
    expect(SessionDispute::query()->where('session_id', $this->lesson->id)->count())->toBe(2);

    app(DecideDispute::class)->handle($dispute, 'completed', 'The recording shows him.', $this->admin->id);
    expect($dispute->refresh()->status)->toBe('upheld')->and($dispute->outcome_after)->toBe('completed')
        ->and(($this->seat)($this->b->id))->toBe('attended')
        ->and(($this->seat)($this->a->id))->toBe('attended')
        ->and($this->lesson->refresh()->status)->toBe(SessionStatus::NeedsReview);     // A's dispute still waits

    app(DecideDispute::class)->handle($other, 'completed', 'A was there.', $this->admin->id);
    expect($other->refresh()->status)->toBe('rejected')
        ->and($this->lesson->refresh()->status)->toBe(SessionStatus::Completed);
});

it('corrects one seat\'s credit when its dispute is upheld', function () use ($join) {
    $join($this->lesson, $this->a->id, 0);
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->starts_at->addMinutes(10));     // the teacher's absence: both owed a makeup
    CarbonImmutable::setTestNow($this->lesson->ends_at->addHour());

    $dispute = app(DisputeLesson::class)->handle($this->lesson->refresh(), 'teacher', $this->teacher->id, 'teacher_attended', 'I taught A from my phone.', $this->a->id);
    app(DecideDispute::class)->handle($dispute, 'completed', 'Confirmed.', $this->admin->id);

    $lesson = $this->lesson->refresh();
    expect($lesson->status)->toBe(SessionStatus::Completed)
        ->and(($this->seat)($this->a->id))->toBe('attended')
        ->and(($this->seat)($this->b->id))->toBe('makeup')
        ->and(DB::table('credit_entries')->where('session_id', $lesson->id)->where('account_id', ($this->account)($this->ja->enrollmentId))->where('type', 'reclaim')->exists())->toBeTrue()
        ->and(DB::table('credit_entries')->where('session_id', $lesson->id)->where('account_id', ($this->account)($this->jb->enrollmentId))->where('type', 'reclaim')->exists())->toBeFalse();
});

it('tells every member and the teacher when a group starts', function () {
    $group = app(CreateGroup::class)->handle(new NewGroup(
        $this->teacher->id, $this->quran, 30, [['weekday' => 1, 'start' => '18:00'], ['weekday' => 4, 'start' => '18:00']], capacity: 3, minToStart: 2,
    ), $this->admin->id);
    [$c, $d] = [$this->student(), $this->student()];
    $this->join($group, $c);
    $key = 'group-started:'.$group->id;
    expect(Notification::query()->where('dedupe_key', $key)->count())->toBe(0);
    $this->join($group, $d);

    $family = Notification::query()->where('template_key', 'scheduling.group_started_family')->where('dedupe_key', $key);
    expect((clone $family)->where('user_id', $c->id)->count())->toBe(1)
        ->and((clone $family)->where('user_id', $d->id)->count())->toBe(1)
        ->and((clone $family)->where('user_id', $c->id)->value('body'))->toContain($c->name)->not->toContain($d->name)
        ->and(Notification::query()->where('template_key', 'scheduling.group_started_teacher')->where('dedupe_key', $key)->where('user_id', $this->teacher->id)->count())->toBe(1);
});
