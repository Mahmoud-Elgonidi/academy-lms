<?php

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\LiveBoard\BoardClassifier;
use Modules\Scheduling\Domain\LiveBoard\BoardEvent;
use Modules\Scheduling\Domain\LiveBoard\BoardIssue;
use Modules\Scheduling\Domain\LiveBoard\BoardPerson;
use Modules\Scheduling\Domain\LiveBoard\BoardSession;

// 5 Oct 2026, 19:03 UTC (22:03 in Cairo and Riyadh).
const BOARD_NOW = '2026-10-05 19:03:00';

function utcAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-05 {$time}:00", 'UTC');
}

function boardSession(string $id, string $start, int $minutes, array $opts = []): BoardSession
{
    return new BoardSession(
        id: $id,
        type: $opts['type'] ?? SessionType::Regular,
        status: $opts['status'] ?? SessionStatus::Scheduled,
        funding: $opts['funding'] ?? FundingStatus::Funded,
        startsAt: utcAt($start),
        endsAt: utcAt($start)->addMinutes($minutes),
        subjectAr: 'تجويد',
        subjectEn: 'Tajweed',
        teacher: new BoardPerson($opts['teacherId'] ?? "t-{$id}", 'Teacher', 'Africa/Cairo', joinedAt: isset($opts['teacherIn']) ? utcAt($opts['teacherIn']) : null),
        student: new BoardPerson("s-{$id}", 'Student', 'Asia/Qatar', joinedAt: isset($opts['studentIn']) ? utcAt($opts['studentIn']) : null),
        guardians: [],
        events: $opts['events'] ?? [],
    );
}

function classify(BoardSession ...$sessions): array
{
    return (new BoardClassifier(AttendancePolicy::defaults()))->classify($sessions, CarbonImmutable::parse(BOARD_NOW, 'UTC'));
}

it('flags a teacher who has not joined after two minutes, with the T+10 deadline', function () {
    $groups = classify(boardSession('omar', '19:00', 45, ['studentIn' => '18:59']));

    expect($groups['attention'])->toHaveCount(1)
        ->and($groups['attention'][0]->issue)->toBe(BoardIssue::TeacherNotJoined)
        ->and($groups['attention'][0]->deadline->format('H:i'))->toBe('19:10')
        ->and($groups['attention'][0]->minutesLeft(CarbonImmutable::parse(BOARD_NOW, 'UTC')))->toBe(7);
});

it('does not flag a teacher in the first two minutes', function () {
    $groups = classify(boardSession('early', '19:02', 45, ['studentIn' => '19:01']));

    expect($groups['attention'])->toBe([])->and($groups['fine'])->toHaveCount(1);
});

it('waits 10 minutes for a 30-minute session and 15 for a 45-minute one', function () {
    $groups = classify(
        boardSession('short', '19:00', 30, ['teacherIn' => '18:58']),
        boardSession('long', '19:00', 45, ['teacherIn' => '18:58']),
    );

    $deadlines = collect($groups['attention'])->mapWithKeys(fn ($i) => [$i->session->id => $i->deadline->format('H:i')]);

    expect($deadlines->all())->toBe(['short' => '19:10', 'long' => '19:15'])
        ->and($groups['attention'][0]->issue)->toBe(BoardIssue::StudentNotJoined);
});

it('marks a session handled once someone logs a contact after the problem started', function () {
    $contact = new BoardEvent(SessionEventKind::ContactLogged, utcAt('19:03'), ['target' => 'teacher', 'outcome' => 'running_late'], 'Hala');
    $groups = classify(boardSession('omar', '19:00', 45, ['studentIn' => '18:59', 'events' => [$contact]]));

    expect($groups['attention'][0]->handledBy)->toBe($contact);
});

it('flags three connection drops within fifteen minutes, but not older ones', function () {
    $drop = fn (string $t) => new BoardEvent(SessionEventKind::ConnectionDrop, utcAt($t), ['seconds' => 30], null);

    $weak = classify(boardSession('maryam', '18:30', 45, ['teacherIn' => '18:29', 'studentIn' => '18:31', 'events' => [$drop('18:49'), $drop('18:57'), $drop('19:01')]]));
    $stale = classify(boardSession('maryam', '18:30', 45, ['teacherIn' => '18:29', 'studentIn' => '18:31', 'events' => [$drop('18:40'), $drop('18:57'), $drop('19:01')]]));

    expect($weak['attention'][0]->issue)->toBe(BoardIssue::WeakConnection)
        ->and($stale['attention'])->toBe([])
        ->and($stale['fine'][0]->minutesDone)->toBe(32);
});

it('puts an unpaid session starting within the hour in front of a manager until one allows it', function () {
    $allowed = new BoardEvent(SessionEventKind::DecisionLogged, utcAt('19:02'), ['decision' => 'allow_unfunded'], 'Hala');

    $unpaid = classify(boardSession('hamza', '19:30', 30, ['funding' => FundingStatus::Unfunded]));
    $exception = classify(boardSession('hamza', '19:30', 30, ['funding' => FundingStatus::Unfunded, 'events' => [$allowed]]));

    expect($unpaid['attention'][0]->issue)->toBe(BoardIssue::Unfunded)
        ->and($unpaid['attention'][0]->deadline->format('H:i'))->toBe('19:30')
        ->and($exception['attention'])->toBe([])
        ->and($exception['upcoming'][0]->checks)->toContain(['key' => 'unfunded_allowed', 'ok' => false]);
});

it('notices when a teacher is still in another session at the start of the next one', function () {
    $groups = classify(
        boardSession('maryam', '18:30', 45, ['teacherId' => 'sara', 'teacherIn' => '18:29', 'studentIn' => '18:31']),
        boardSession('hamza', '19:10', 30, ['teacherId' => 'sara']),
    );

    expect($groups['upcoming'][0]->checks[0])->toBe(['key' => 'teacher_busy', 'ok' => false]);
});

it('leaves out sessions more than an hour away and orders the rest', function () {
    $groups = classify(
        boardSession('later', '20:30', 30),
        boardSession('soon', '19:40', 30),
        boardSession('sooner', '19:20', 30),
        boardSession('unpaid', '19:50', 30, ['funding' => FundingStatus::Unfunded]),
        boardSession('late-teacher', '19:00', 45, ['studentIn' => '18:59']),
        boardSession('done', '18:00', 45, ['status' => SessionStatus::Completed, 'teacherIn' => '17:59', 'studentIn' => '18:00']),
    );

    expect(collect($groups['upcoming'])->map(fn ($i) => $i->session->id)->all())->toBe(['sooner', 'soon'])
        ->and(collect($groups['attention'])->map(fn ($i) => $i->session->id)->all())->toBe(['late-teacher', 'unpaid'])
        ->and($groups['done'][0]->minutesDone)->toBe(45);
});

it('keeps sessions that need review in front of a manager', function () {
    $groups = classify(boardSession('review', '17:00', 45, ['status' => SessionStatus::NeedsReview]));

    expect($groups['attention'][0]->issue)->toBe(BoardIssue::NeedsReview)
        ->and($groups['attention'][0]->deadline)->toBeNull();
});

it('knows the next moment the clock alone changes the board, and nothing sooner', function () {
    $now = CarbonImmutable::parse(BOARD_NOW, 'UTC');
    $classifier = new BoardClassifier(AttendancePolicy::defaults());
    $late = boardSession('late', '19:00', 45, ['studentIn' => '18:59']);                 // already flagged at 19:02
    $soon = boardSession('soon', '19:20', 45);                                             // starts at 19:20
    $waiting = boardSession('waiting', '19:01', 45, ['teacherIn' => '19:00']);             // the student's nudge is due at 19:04
    $done = boardSession('done', '19:30', 30, ['status' => SessionStatus::Cancelled]);     // nothing will happen to it

    expect($classifier->nextChange([$late, $soon, $waiting, $done], $now)->format('H:i'))->toBe('19:04')
        ->and($classifier->nextChange([$late, $soon, $done], $now)->format('H:i'))->toBe('19:20')
        ->and($classifier->nextChange([$late], $now, utcAt('20:30'))->format('H:i'))->toBe('19:30')   // the next lesson comes into the hour ahead
        ->and($classifier->nextChange([$late, $done], $now))->toBeNull();

    $drops = boardSession('weak', '18:30', 60, ['teacherIn' => '18:29', 'studentIn' => '18:30', 'events' => [
        new BoardEvent(SessionEventKind::ConnectionDrop, utcAt('18:55'), [], null),
    ]]);
    expect($classifier->nextChange([$drops], $now)->format('H:i'))->toBe('19:10');      // the drop leaves the 15-minute window
});
