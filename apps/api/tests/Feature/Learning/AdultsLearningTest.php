<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Models\Notification;
use Modules\Learning\Actions\Digest\QueueWeeklyDigests;
use Modules\Learning\Actions\Digest\SaveWeeklyDigest;
use Modules\Learning\Actions\SaveJourneyStyle;
use Modules\Learning\Assist\ReportDraftPrompt;
use Modules\Learning\Filament\Pages\WeeklyNoteSettings;
use Modules\Learning\Models\LearningSettings;
use Modules\Platform\Models\Academy;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Learning in an adults academy (docs/architecture/02, section 29): the
 * journey starts plain and no lesson ever pushes its stars, the weekly note
 * to guardians has no place, the notifications speak to the trainee in the
 * second person and the trainer in a training's words, and the report
 * helper writes to an adult. A families academy keeps all of it as it was.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->student->forceFill(['locale' => 'ar'])->save();
    $this->lessons = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->get();
});

/** The fixture's academy becomes an adults one (as a console owner would make it), for the rest of the test. */
function learningAsAdults(): void
{
    DB::connection('pgsql_platform')->table('academies')->where('id', test()->academy->id)->update(['audience' => 'adults']);
    test()->academy = test()->actAsTenant(Academy::query()->findOrFail(test()->academy->id)->toTenantRef());
}

/** A lesson took place: the student came in a minute late and stayed. */
function learningLessonHeld(Session $lesson): void
{
    app(AttendanceApi::class)->participantJoined($lesson->id, test()->student->id, $lesson->starts_at->addMinute());
    $lesson->update(['status' => 'completed']);
    event(new SessionFinalized($lesson->academy_id, $lesson->id, 'completed', []));
}

function starsSent(): int
{
    return Notification::query()->where('template_key', 'learning.lesson_stars')->where('user_id', test()->student->id)->count();
}

it('pushes a lesson\'s stars in a families academy, but never while its journey is plain', function () {
    learningLessonHeld($this->lessons[0]);
    expect(starsSent())->toBe(1);

    app(SaveJourneyStyle::class)->handle('plain');
    learningLessonHeld($this->lessons[1]);
    expect(starsSent())->toBe(1);
});

it('starts an adults academy\'s journey plain and never pushes stars there, whatever the style', function () {
    expect(LearningSettings::current()->journey_style)->toBe('playful');
    learningAsAdults();
    expect(LearningSettings::current()->journey_style)->toBe('plain');

    learningLessonHeld($this->lessons[0]);
    // The academy may choose another look; the push still has no place for adults.
    app(SaveJourneyStyle::class)->handle('playful');
    expect(LearningSettings::current()->journey_style)->toBe('playful');
    learningLessonHeld($this->lessons[1]);
    expect(starsSent())->toBe(0);
});

it('keeps the first saved setting of an adults academy plain', function () {
    learningAsAdults();
    app(SaveWeeklyDigest::class)->handle(false);
    expect(DB::table('learning_settings')->where('academy_id', $this->academy->id)->value('journey_style'))->toBe('plain');
});

it('has no weekly note to guardians in an adults academy', function () {
    $this->actingAs($this->admin);
    app(SaveWeeklyDigest::class)->handle(true);
    expect(WeeklyNoteSettings::canAccess())->toBeTrue();

    learningAsAdults();
    expect(WeeklyNoteSettings::canAccess())->toBeFalse()
        // Even switched on before, nothing goes on its way (Friday 9 October, 8 pm in London).
        ->and(app(QueueWeeklyDigests::class)->handle(CarbonImmutable::parse('2026-10-09 19:30', 'UTC')))->toBe(0)
        ->and(DB::table('weekly_digests')->count())->toBe(0);
});

it('speaks to the trainee and the trainer in a training\'s words in an adults academy', function () {
    $send = function () {
        app(NotificationsApi::class)->send(new Message('learning.homework_assigned', [$this->student->id], ['student' => 'زياد', 'title' => 'خطة مشروع', 'due' => 'الخميس']));
        app(NotificationsApi::class)->send(new Message('learning.report_overdue', [$this->teacher->id], ['student' => 'زياد', 'when' => 'الأحد']));
        $student = Notification::query()->where('user_id', $this->student->id)->where('template_key', 'learning.homework_assigned')->latest('created_at')->firstOrFail();
        $teacher = Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'learning.report_overdue')->latest('created_at')->firstOrFail();
        Notification::query()->whereIn('template_key', ['learning.homework_assigned', 'learning.report_overdue'])->delete();

        return [$student->title, $teacher->title];
    };
    $this->teacher->forceFill(['locale' => 'ar'])->save();

    expect($send())->toBe(['واجب جديد لـ زياد', 'تقرير حصة زياد متأخر']);
    learningAsAdults();
    expect($send())->toBe(['مهمة جديدة لك', 'تقرير جلسة زياد متأخر'])
        ->and(__('learning::teacher.write.notes_for_student', [], 'ar'))->toBe('ملاحظات للمتدرّب');
});

it('asks the report helper to write to an adult in an adults academy', function () {
    $families = ReportDraftPrompt::for('lesson', 'شرحنا خطة المشروع.', [], [])->system;
    expect($families)->not->toContain('adult professionals');

    learningAsAdults();
    $adults = ReportDraftPrompt::for('lesson', 'شرحنا خطة المشروع.', [], [])->system;
    expect($adults)->toStartWith($families)->toContain('adult professionals')->toContain('never mention parents');
});
