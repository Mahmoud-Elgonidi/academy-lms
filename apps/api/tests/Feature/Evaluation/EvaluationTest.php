<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\RecordPayment;
use Modules\Communication\Models\Notification;
use Modules\Evaluation\Actions\ExcludeLesson;
use Modules\Evaluation\Actions\RateLesson;
use Modules\Evaluation\Filament\Family\RateLessonChip;
use Modules\Evaluation\Filament\Pages\TeacherPerformance;
use Modules\Evaluation\Filament\Pages\TeachersPerformance;
use Modules\Evaluation\Filament\Teacher\MyPerformance;
use Modules\Evaluation\Models\LessonRating;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Lesson ratings and teacher KPIs (docs/architecture/01, sections 12 and
 * "تقييم الحصة بضغطة واحدة"; 02, section 7). The fixture: a London student
 * with a Cairo teacher, Tuesdays and Saturdays 18:00 Cairo for 30 minutes
 * from 6 October 2026, paid. Lessons are taught the way the classroom
 * records them: joins, leaves, then the deadlines close them.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function lessonOn(string $cairoDay): Session
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('starts_at', CarbonImmutable::parse($cairoDay.' 18:00', 'Africa/Cairo')->utc())->firstOrFail();
}

/** Teaches a lesson: the teacher joins $late minutes in (null: never), leaves $early minutes before the end; then the deadlines close it. */
function teachLesson(string $cairoDay, ?int $late = 0, int $early = 0): Session
{
    $s = lessonOn($cairoDay);
    $join = app(RecordParticipantJoin::class);
    CarbonImmutable::setTestNow($s->starts_at);
    $join->handle($s->refresh(), test()->student->id, $s->starts_at);
    if ($late !== null) {
        $join->handle($s->refresh(), test()->teacher->id, $s->starts_at->addMinutes($late));
        app(RecordParticipantLeave::class)->handle($s->refresh(), test()->teacher->id, $s->ends_at->subMinutes($early));
    }
    app(RecordParticipantLeave::class)->handle($s->refresh(), test()->student->id, $s->ends_at);
    CarbonImmutable::setTestNow($s->ends_at->addMinutes(11));
    app(ApplyAttendanceDeadlines::class)->handle();

    return $s->refresh();
}

function evalSent(string $userId, string $template): int
{
    return Notification::query()->where('user_id', $userId)->where('template_key', $template)->count();
}

it('asks the family for a rating after a lesson, once a week, with each person\'s own link', function () {
    $first = teachLesson('2026-10-06');
    $link = Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'evaluation.rate_lesson')->value('body');

    expect($first->status->value)->toBe('completed')
        ->and($link)->toContain('/rate/'.$first->id)->toContain('signature=')
        ->and(evalSent($this->student->id, 'evaluation.rate_lesson'))->toBe(1);

    teachLesson('2026-10-10');      // the same week (Monday to Sunday): not asked again
    expect(evalSent($this->guardian->id, 'evaluation.rate_lesson'))->toBe(1);

    teachLesson('2026-10-13');      // the next week
    expect(evalSent($this->guardian->id, 'evaluation.rate_lesson'))->toBe(2);
});

it('takes one rating per person per lesson, from the family only, for lessons that took place in the last two weeks', function () {
    $lesson = teachLesson('2026-10-06');
    $rate = app(RateLesson::class);

    $rating = $rate->handle($lesson->id, $this->guardian->id, 5, ['clear', 'patient']);
    expect([$rating->stars, $rating->teacher_id, $rating->student_id, $rating->tags])->toBe([5, $this->teacher->id, $this->student->id, ['clear', 'patient']]);

    $rate->handle($lesson->id, $this->guardian->id, 4);
    expect(LessonRating::query()->where('session_id', $lesson->id)->count())->toBe(1)
        ->and(LessonRating::query()->value('stars'))->toBe(4)
        ->and(fn () => $rate->handle($lesson->id, User::factory()->withRole(Role::Guardian)->create()->id, 5))->toThrow(ValidationException::class)
        ->and(fn () => $rate->handle($lesson->id, $this->guardian->id, 6))->toThrow(ValidationException::class)
        ->and(fn () => $rate->handle($lesson->id, $this->guardian->id, 4, ['nonsense']))->toThrow(ValidationException::class)
        ->and(fn () => $rate->handle(lessonOn('2026-10-10')->id, $this->guardian->id, 5))->toThrow(ValidationException::class);   // not taught yet

    CarbonImmutable::setTestNow('2026-10-25 12:00 UTC');
    expect(fn () => $rate->handle($lesson->id, $this->student->id, 5))->toThrow(ValidationException::class);     // more than 14 days ago
});

it('tells the reviewers of a low rating once, with what the family wrote', function () {
    $lesson = teachLesson('2026-10-06');

    app(RateLesson::class)->handle($lesson->id, $this->guardian->id, 2, ['connection'], 'The connection kept dropping.');
    app(RateLesson::class)->handle($lesson->id, $this->guardian->id, 1, ['connection'], 'The connection kept dropping.');
    app(RateLesson::class)->handle($lesson->id, $this->student->id, 5);

    expect(evalSent($this->supervisor->id, 'evaluation.low_rating'))->toBe(1)
        ->and(Notification::query()->where('user_id', $this->supervisor->id)->where('template_key', 'evaluation.low_rating')->value('body'))->toContain('The connection kept dropping.')
        ->and(evalSent($this->teacher->id, 'evaluation.low_rating'))->toBe(0);
});

it('rates in one tap from the link in the message, without signing in', function () {
    $lesson = teachLesson('2026-10-06');
    $this->clearTenant();
    $url = 'http://noor.lms.test'.URL::temporarySignedRoute('evaluation.rate', now()->addDays(7), ['session' => $lesson->id, 'u' => $this->guardian->id], absolute: false);

    $this->get($url)->assertOk()->assertSee($this->student->name);
    $this->get($url.'x')->assertForbidden();

    $page = $this->get($url)->getContent();
    preg_match('/action="([^"]+)"/', $page, $m);
    $this->post('http://noor.lms.test'.html_entity_decode($m[1]), ['stars' => 5])->assertRedirect();

    $this->actAsTenant($this->academy);
    expect(LessonRating::query()->where('rater_id', $this->guardian->id)->first(['stars', 'via'])->only(['stars', 'via']))->toBe(['stars' => 5, 'via' => 'link']);
});

it('rates from the stars under the lesson in the family\'s recent lessons', function () {
    $lesson = teachLesson('2026-10-06');
    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');

    Livewire::test(RateLessonChip::class, ['sessionId' => $lesson->id])
        ->assertSet('open', true)
        ->call('rate', 4)
        ->assertSet('stars', 4)
        ->call('toggle', 'clear')
        ->assertSet('tags', ['clear'])
        ->call('rate', 2)               // fewer stars: the good words go
        ->assertSet('tags', []);
    expect(LessonRating::query()->value('stars'))->toBe(2);

    // Not under a lesson that did not take place.
    Livewire::test(RateLessonChip::class, ['sessionId' => lessonOn('2026-10-10')->id])->assertSet('open', false);
});

it('measures the teacher over 90 days: on time, absent, taught, leaving early, reports and ratings, without the lessons taken out', function () {
    teachLesson('2026-10-06');
    teachLesson('2026-10-10', late: 5);
    teachLesson('2026-10-13', early: 6);
    $absent = teachLesson('2026-10-17', late: null);
    teachLesson('2026-10-20');
    $last = teachLesson('2026-10-24');

    // One report in on time; the rest are due by now.
    $report = SessionReport::query()->where('session_id', $last->id)->firstOrFail();
    app(SubmitSessionReport::class)->handle($report, $this->teacher->id, ['engagement' => 4, 'overall' => 4, 'notes_for_student' => 'Good.']);
    app(RateLesson::class)->handle($last->id, $this->guardian->id, 5);
    CarbonImmutable::setTestNow('2026-10-26 12:00 UTC');

    $k = app(TeacherKpis::class)->forTeachers([$this->teacher->id])[$this->teacher->id];
    expect($absent->status->value)->toBe('teacher_absent')
        ->and([$k['punctuality']['value'], $k['punctuality']['n'], $k['punctuality']['meets']])->toBe([0.8, 5, false])
        ->and($k['avg_late']['value'])->toBe(5.0)
        ->and([round($k['absence']['value'], 3), $k['absence']['n']])->toBe([round(1 / 6, 3), 6])
        ->and(round($k['taught']['value'], 3))->toBe(round(139 / 150, 3))
        ->and($k['early_leave']['value'])->toBe(0.2)
        ->and([$k['reports']['value'], $k['reports']['n']])->toBe([0.2, 5])
        ->and([$k['rating']['value'], $k['rating']['n']])->toBe([5.0, 1])       // the academy's mean is this one rating
        ->and($k['trial_conversion']['value'])->toBeNull();

    // The absence was the platform's doing: taken out, it no longer counts.
    app(ExcludeLesson::class)->handle($absent->id, 'platform', 'Outage that evening', $this->supervisor->id);
    $k = app(TeacherKpis::class)->forTeachers([$this->teacher->id])[$this->teacher->id];
    expect([$k['absence']['value'], $k['absence']['n']])->toBe([0.0, 5]);
});

it('shows the teachers\' numbers to reviewers, and each teacher their own without who rated', function () {
    $lesson = teachLesson('2026-10-06', late: 4);
    app(RateLesson::class)->handle($lesson->id, $this->guardian->id, 3, ['late'], 'Started late.');

    $this->actingAs($this->supervisor);
    Filament::setCurrentPanel('academy');
    Livewire::test(TeachersPerformance::class)->assertSee($this->teacher->name)->assertSee(__('evaluation::kpis.names.punctuality'));
    Livewire::test(TeacherPerformance::class, ['teacher' => $this->teacher->id])
        ->assertSee(__('evaluation::kpis.events.late_join', ['minutes' => 4]))
        ->assertSee('Started late.')
        ->assertSee($this->guardian->name)
        ->callAction('exclude', data: ['reason' => 'provider', 'note' => 'LiveKit outage'], arguments: ['session' => $lesson->id])
        ->assertHasNoActionErrors()
        ->assertSee('LiveKit outage');

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(MyPerformance::class)
        ->assertSee(__('evaluation::kpis.names.punctuality'))
        ->assertSee('Started late.')
        ->assertDontSee($this->guardian->name);
});

it('keeps the numbers to reviewers and the teacher, never the family', function () {
    $accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->clearTenant();

    $this->actingAs($this->supervisor)->get('http://noor.lms.test/admin/teachers/performance')->assertOk();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($accountant)->get('http://noor.lms.test/admin/teachers/performance')->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->teacher)->get('http://noor.lms.test/teach/performance')->assertOk();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->actingAs($this->guardian)->get('http://noor.lms.test/my/lessons')->assertOk()->assertDontSee(__('evaluation::kpis.names.punctuality'));
});
