<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Actions\SubmitGroupReport;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\GroupLessons;

uses(GroupLessons::class);

/*
 * A group lesson's reports (docs/architecture/02, "الحصص الجماعية"): one
 * per student, a lesson report for each who came and an absence note for
 * each who did not; the teacher writes the class's part once (and its
 * homework goes to every student) and a short row for each.
 */

beforeEach(function () {
    $this->setUpGroups();
    $this->group = $this->group();
    [$this->a, $this->b, $this->c] = [$this->student(), $this->student(), $this->student()];
    foreach ([$this->a, $this->b, $this->c] as $s) {
        $this->pay($this->join($this->group, $s));
    }
    $this->lesson = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->firstOrFail();
    $api = app(AttendanceApi::class);
    foreach ([$this->teacher, $this->a, $this->b] as $u) {
        $api->participantJoined($this->lesson->id, $u->id, $this->lesson->starts_at);
    }
    app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));
    CarbonImmutable::setTestNow($this->lesson->ends_at->addMinutes(30));
    $this->reports = SessionReport::query()->where('session_id', $this->lesson->id)->get()->keyBy('student_id');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('asks for a report per student: a lesson report for who came, an absence note for who did not', function () {
    expect($this->reports)->toHaveCount(3)
        ->and($this->reports[$this->a->id]->kind)->toBe('lesson')
        ->and($this->reports[$this->b->id]->kind)->toBe('lesson')
        ->and($this->reports[$this->c->id]->kind)->toBe('absence')
        ->and($this->reports->pluck('enrollment_id')->filter()->count())->toBe(3)
        ->and($this->reports->pluck('teacher_id')->unique()->all())->toBe([$this->teacher->id]);
});

it('takes the class\'s part once and a row for each student, the homework going to every one', function () {
    $token = $this->teacher->createToken('phone')->plainTextToken;
    $report = $this->reports[$this->a->id];

    $this->withToken($token)->getJson("/api/v1/teacher/reports/{$report->id}")->assertOk()
        ->assertJsonCount(3, 'data.group.students')
        ->assertJsonPath('data.group.students.2.kind', fn ($k) => in_array($k, ['lesson', 'absence'], true));

    $this->withToken($token)->putJson("/api/v1/teacher/reports/{$report->id}", [
        'notes_internal' => 'Surah Al-Fil, the whole class.',
        'homework' => ['title' => 'Recite Al-Fil twice', 'due_days' => 3],
        'students' => [
            ['student_id' => $this->a->id, 'engagement' => 5, 'overall' => 4, 'notes_for_student' => 'Lovely tajweed.'],
            ['student_id' => $this->b->id, 'engagement' => 3, 'overall' => 3, 'notes_for_student' => 'Practise the madd.'],
            ['student_id' => $this->c->id, 'absence_note' => 'We missed you; the homework is for you too.'],
        ],
    ])->assertOk()->assertJsonPath('data.id', $report->id);
    $this->actAsTenant($this->academy);

    $after = SessionReport::query()->where('session_id', $this->lesson->id)->get()->keyBy('student_id');
    expect($after->pluck('status')->unique()->all())->toBe(['submitted'])
        ->and($after[$this->a->id]->overall)->toBe(4)->and($after[$this->a->id]->notes_for_student)->toBe('Lovely tajweed.')
        ->and($after[$this->b->id]->engagement)->toBe(3)
        ->and($after[$this->c->id]->absence_note)->toBe('We missed you; the homework is for you too.')
        ->and(Homework::query()->where('session_id', $this->lesson->id)->pluck('student_id')->sort()->values()->all())
        ->toBe(collect([$this->a->id, $this->b->id, $this->c->id])->sort()->values()->all())
        ->and(Homework::query()->where('student_id', $this->b->id)->value('title'))->toBe('Recite Al-Fil twice');
});

it('refuses a group report that leaves a student out, and hands in none of it', function () {
    expect(fn () => app(SubmitGroupReport::class)->handle($this->reports[$this->a->id], $this->teacher->id, [], [
        ['student_id' => $this->a->id, 'engagement' => 5, 'overall' => 5],
        ['student_id' => $this->b->id, 'engagement' => 4, 'overall' => 4],
    ]))->toThrow(ValidationException::class, __('learning::group_reports.errors.students'));

    expect(fn () => app(SubmitGroupReport::class)->handle($this->reports[$this->a->id], $this->teacher->id, [], [
        ['student_id' => $this->a->id, 'engagement' => 5, 'overall' => 5],
        ['student_id' => $this->b->id],                                         // no scores
        ['student_id' => $this->c->id, 'absence_note' => 'Missed.'],
    ]))->toThrow(ValidationException::class);
    expect(SessionReport::query()->where('session_id', $this->lesson->id)->where('status', 'submitted')->count())->toBe(0);
});
