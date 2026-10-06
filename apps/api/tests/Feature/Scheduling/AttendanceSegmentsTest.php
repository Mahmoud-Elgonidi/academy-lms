<?php

use Carbon\CarbonImmutable;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\AttendanceSegment;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\TaughtMinutes;

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['name' => 'Sara']);
    $this->student = User::factory()->withRole(Role::Student)->create(['name' => 'Omar']);
    $subject = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
    // 45 minutes, 19:00–19:45 UTC.
    $this->session = app(ScheduleSession::class)->handle(new NewSession($this->teacher->id, $this->student->id, $subject, CarbonImmutable::parse('2026-10-05 19:00', 'UTC'), 45, 'Africa/Cairo'));
    $this->api = app(AttendanceApi::class);
});

function at(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-05 {$time}", 'UTC');
}

it('opens a stretch per join and closes it on leave, ignoring repeated webhooks', function () {
    $this->api->participantJoined($this->session->id, $this->teacher->id, at('18:58'));
    $this->api->participantJoined($this->session->id, $this->teacher->id, at('18:59'));   // repeated
    $this->api->participantLeft($this->session->id, $this->teacher->id, at('19:10'));
    $this->api->participantLeft($this->session->id, $this->teacher->id, at('19:11'));     // repeated
    $this->api->participantJoined($this->session->id, $this->teacher->id, at('19:12'));

    expect(AttendanceSegment::query()->where('user_id', $this->teacher->id)->orderBy('joined_at')->get()->map(fn ($s) => [$s->joined_at->format('H:i'), $s->left_at?->format('H:i')])->all())
        ->toBe([['18:58', '19:10'], ['19:12', null]])
        ->and($this->api->inClassroom($this->session->id))->toBe([$this->teacher->id])
        ->and($this->session->refresh()->status)->toBe(SessionStatus::InProgress)
        ->and($this->session->participants()->where('user_id', $this->teacher->id)->value('attended_min'))->toBe(12);
});

it('counts taught time as the overlap of both people inside the lesson', function () {
    $this->api->participantJoined($this->session->id, $this->teacher->id, at('18:55'));   // early: the lesson starts at 19:00
    $this->api->participantJoined($this->session->id, $this->student->id, at('19:05'));
    $this->api->participantLeft($this->session->id, $this->student->id, at('19:15'));     // dropped for 10 minutes
    $this->api->participantJoined($this->session->id, $this->student->id, at('19:25'));
    $this->api->participantLeft($this->session->id, $this->teacher->id, at('19:50'));     // stayed after the end

    // Student 19:05–19:15 and 19:25–(19:45), teacher all along: 10 + 20 = 30 of 45.
    expect(app(TaughtMinutes::class)->of($this->session->id, at('19:00'), at('19:45'), at('19:55')))->toBe(30);
});

it('closes a lesson by what was really taught: too little goes to review', function () {
    $this->api->participantJoined($this->session->id, $this->teacher->id, at('18:59'));
    $this->api->participantJoined($this->session->id, $this->student->id, at('19:00'));
    $this->api->participantLeft($this->session->id, $this->student->id, at('19:20'));     // left after 20 of 45 minutes

    app(ApplyAttendanceDeadlines::class)->handle(at('19:55'));

    $session = Session::query()->find($this->session->id);
    expect($session->status)->toBe(SessionStatus::NeedsReview)->and($session->review_reason)->toBe('short_delivery');
});

it('completes a lesson taught almost in full, with the minutes on the record', function () {
    $this->api->participantJoined($this->session->id, $this->teacher->id, at('18:59'));
    $this->api->participantJoined($this->session->id, $this->student->id, at('19:02'));

    app(ApplyAttendanceDeadlines::class)->handle(at('19:55'));

    $session = Session::query()->find($this->session->id);
    expect($session->status)->toBe(SessionStatus::Completed)->and($session->decision['taught_min'])->toBe(43);
});

it('describes the lesson for the classroom, and finds the classrooms open now', function () {
    $card = $this->api->lesson($this->session->id);

    expect($card->teacherName)->toBe('Sara')->and($card->studentName)->toBe('Omar')->and($card->subject('en'))->toBe('Tajweed')->and($card->isOpen())->toBeTrue()
        ->and($this->api->lessonsInClass(at('18:49'), 10, 10))->toBe([])
        ->and($this->api->lessonsInClass(at('18:50'), 10, 10))->toBe([$this->session->id])
        ->and($this->api->lessonsInClass(at('19:55'), 10, 10))->toBe([$this->session->id])
        ->and($this->api->lessonsOf($this->teacher->id, at('00:00'), at('23:59')))->toHaveCount(1);
});
