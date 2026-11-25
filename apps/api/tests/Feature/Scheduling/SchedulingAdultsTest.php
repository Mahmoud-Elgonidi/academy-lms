<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Queries\Start\StartRules;
use Modules\Scheduling\Support\StartRefused;

/*
 * Lessons in an academy that trains adults only (docs/architecture/02,
 * section 29): every learner starts their own programme (nobody is sent to
 * a guardian), and what they and their trainers receive says a session, a
 * trainer and «you», never a lesson, a teacher, a family or a child. A
 * families academy reads as before.
 */

beforeEach(function () {
    Mail::fake();
    $this->families = $this->createAcademy(['slug' => 'noor']);
    $this->adults = $this->createAcademy(['slug' => 'khibra', 'audience' => 'adults']);
});

/** A student whose saved look is the kids' one (as an academy that changed kind may hold). */
function kidsLookStudent(): User
{
    $student = User::factory()->withRole(Role::Student)->create(['locale' => 'ar']);
    app(PeopleApi::class)->saveStudentProfile($student->id, CarbonImmutable::now()->subYears(9), LearnerStage::Kids);

    return $student;
}

it('sends a child to the guardian in a families academy, and lets every learner start in an adults academy', function () {
    $this->actAsTenant($this->families);
    expect(fn () => app(StartRules::class)->assertCanStart(kidsLookStudent()->id))
        ->toThrow(fn (StartRefused $e) => expect($e->reason)->toBe('family_only'));

    $this->actAsTenant($this->adults);
    app(StartRules::class)->assertCanStart(kidsLookStudent()->id);
    expect(true)->toBeTrue();
});

it('words the reminders, the join link and a cancellation for a trainee in an adults academy', function (string $template) {
    $send = function ($academy) use ($template): string {
        $this->actAsTenant($academy);
        $user = User::factory()->withRole(Role::Student)->create(['locale' => 'ar']);
        app(NotificationsApi::class)->send(new Message("scheduling.{$template}", [$user->id], [
            'subject' => 'PMP', 'student' => 'عبد الرحمن', 'teacher' => 'أ. ريم', 'when' => 'الأحد 10 أكتوبر · 7:00 م', 'link' => 'https://khibra.lms.test/j/1',
        ]));
        $sent = Notification::query()->where('user_id', $user->id)->sole();

        return $sent->title.' / '.$sent->body;
    };

    $families = $send($this->families);
    $adults = $send($this->adults);
    expect($families)->toContain('حصة');
    foreach (['حصة', 'الحصة', 'المعلّم', 'ولي', 'الأسرة', 'طفل', 'ادخلوا', 'عبد الرحمن'] as $word) {
        expect($adults)->not->toContain($word);
    }
    expect($adults)->toContain('جلس');
})->with(['reminder_day', 'reminder_hour', 'join_now', 'session_cancelled']);
