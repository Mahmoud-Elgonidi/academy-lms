<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Billing\Contracts\BillingApi;
use Modules\Communication\Actions\SaveCommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Actions\SendLessonReminders;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

beforeEach(function () {
    Mail::fake();
    $this->enrolStudentOnPlan();
    app(SaveCommunicationSettings::class)->handle(['whatsapp_enabled' => true]);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->guardian->update(['phone_e164' => '+966500000009']);
    $this->first = fn () => Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** @return list<string> template keys the user received, oldest first */
function inbox(User $user): array
{
    return Notification::query()->where('user_id', $user->id)->orderBy('created_at')->pluck('template_key')->all();
}

it('sends the payer the invoice on enrolment and a receipt when it is paid', function () {
    expect(inbox($this->guardian))->toBe(['billing.invoice_issued']);
    $issued = Notification::query()->where('user_id', $this->guardian->id)->sole();
    expect($issued->body)->toContain($this->student->name)->toContain('400')
        // Issued during enrolment, before this academy switched WhatsApp on; no app installed, so no push.
        ->and($issued->deliveries()->pluck('status', 'channel')->sortKeys()->all())->toBe(['email' => 'sent', 'push' => 'skipped']);

    app(BillingApi::class)->recordPayment($this->booked->invoice->id, 'cash', $this->booked->invoice->total, null, CarbonImmutable::now(), $this->admin->id);
    expect(inbox($this->guardian))->toBe(['billing.invoice_issued', 'billing.invoice_paid'])
        ->and(inbox($this->student))->toBe([]);
});

it('tells the family about the report and the homework, and the teacher about the hand-in', function () {
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $session = ($this->first)();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    $report = SessionReport::query()->sole();

    app(SubmitSessionReport::class)->handle($report, $this->teacher->id, [
        'engagement' => 5, 'overall' => 4, 'notes_for_student' => 'Great start',
        'items' => [['node_id' => $this->lessons[0], 'outcome' => 'mastered', 'score' => 5]],
        'homework' => ['title' => 'Recite lesson 1', 'accepts' => 'text'],
    ]);

    foreach ([$this->student, $this->guardian] as $person) {
        expect(inbox($person))->toContain('learning.report_submitted', 'learning.homework_assigned', 'learning.node_mastered');
    }
    $mastered = Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'learning.node_mastered')->sole();
    expect($mastered->deliveries()->count())->toBe(0)                    // inbox only
        ->and($mastered->body)->toContain('درس 1')->toContain('12%');   // the lesson's Arabic title, for an Arabic reader

    $homework = Homework::query()->sole();
    app(SubmitHomework::class)->handle($homework, $this->student->id, 'Done', null);
    expect(inbox($this->teacher))->toBe(['learning.homework_submitted']);

    app(ReviewHomework::class)->handle($homework->refresh(), $this->teacher->id, 5, 'Excellent');
    expect(inbox($this->guardian))->toContain('learning.homework_reviewed');
});

it('tells the family and the teacher about a cancellation or a move, but not whoever made it', function () {
    app(BillingApi::class)->recordPayment($this->booked->invoice->id, 'cash', $this->booked->invoice->total, null, CarbonImmutable::now(), $this->admin->id);
    $session = ($this->first)();

    app(CancelSession::class)->handle($session, 'teacher', 'illness', null, $this->teacher->id);
    expect(inbox($this->guardian))->toContain('scheduling.session_cancelled')
        ->and(inbox($this->teacher))->not->toContain('scheduling.session_cancelled_teacher');
    expect(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'scheduling.session_cancelled')->value('body'))
        ->toContain('حصة تعويض');

    $next = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->where('status', 'scheduled')->orderBy('starts_at')->first();
    app(RescheduleSession::class)->handle($next, $next->starts_at->addMinutes(30), 'academy', $this->admin->id);
    expect(inbox($this->guardian))->toContain('scheduling.session_moved')
        ->and(inbox($this->teacher))->toContain('scheduling.session_moved_teacher');
});

it('reminds a day, an hour and ten minutes before, and says "join now" three minutes in, once each', function () {
    $session = ($this->first)();

    // Unpaid: no day-before reminder (the lesson is about to be cancelled under rule 16).
    app(SendLessonReminders::class)->handle($session->starts_at->subHours(24)->addMinute());
    expect(inbox($this->guardian))->not->toContain('scheduling.reminder_day');

    app(BillingApi::class)->recordPayment($this->booked->invoice->id, 'cash', $this->booked->invoice->total, null, CarbonImmutable::now(), $this->admin->id);
    app(SendLessonReminders::class)->handle($session->starts_at->subHours(24)->addMinute());
    app(SendLessonReminders::class)->handle($session->starts_at->subMinutes(59));
    app(SendLessonReminders::class)->handle($session->starts_at->subMinutes(58));
    expect(array_count_values(inbox($this->guardian)))->toMatchArray(['scheduling.reminder_day' => 1, 'scheduling.reminder_hour' => 1])
        ->and(inbox($this->teacher))->toBe(['scheduling.reminder_hour_teacher']);

    // Ten minutes before, as the classroom opens: each one's way in, once.
    app(SendLessonReminders::class)->handle($session->starts_at->subMinutes(10));
    app(SendLessonReminders::class)->handle($session->starts_at->subMinutes(9));
    expect(array_count_values(inbox($this->guardian)))->toMatchArray(['scheduling.reminder_hour' => 1, 'scheduling.reminder_soon' => 1])
        ->and(array_count_values(inbox($this->teacher)))->toBe(['scheduling.reminder_hour_teacher' => 1, 'scheduling.reminder_soon_teacher' => 1])
        ->and(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'scheduling.reminder_soon')->value('body'))->toContain('/class/');

    app(RecordParticipantJoin::class)->handle($session->refresh(), $this->teacher->id, $session->starts_at);
    app(SendLessonReminders::class)->handle($session->starts_at->addMinutes(2));
    expect(inbox($this->guardian))->not->toContain('scheduling.join_now');

    CarbonImmutable::setTestNow($session->starts_at->addMinutes(3));
    app(SendLessonReminders::class)->handle();
    $joinNow = Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'scheduling.join_now')->sole();
    expect($joinNow->urgent)->toBeTrue()
        ->and($joinNow->deliveries()->where('channel', 'whatsapp')->value('status'))->toBe('sent');
});

it('nudges a late teacher and tells the supervisors', function () {
    $session = ($this->first)();
    app(RecordParticipantJoin::class)->handle($session, $this->student->id, $session->starts_at);

    app(SendLessonReminders::class)->handle($session->starts_at->addMinutes(2));

    expect(inbox($this->teacher))->toContain('scheduling.teacher_join_now')
        ->and(inbox($this->admin))->toContain('scheduling.teacher_late')
        ->and(inbox($this->guardian))->not->toContain('scheduling.join_now');
});

it('tells the reviewers about a shift proposal and the teacher about the answer', function () {
    event(new ShiftProposalSubmitted($this->academy->id, 'proposal-1', $this->teacher->id, $this->teacher->id));
    event(new ShiftProposalApproved($this->academy->id, 'proposal-1', $this->teacher->id, $this->admin->id));

    expect(inbox($this->admin))->toBe(['scheduling.proposal_submitted'])
        ->and(inbox($this->teacher))->toBe(['scheduling.proposal_answered'])
        ->and(Notification::query()->where('user_id', $this->teacher->id)->value('body'))->toContain('اتعتمد');
});
