<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Domain\Pay;
use Modules\Payroll\Domain\RateLine;
use Modules\Payroll\Models\TeacherEarning;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\GroupLessons;

uses(GroupLessons::class);

/*
 * A group lesson pays its teacher a fixed group rate (docs/architecture/02,
 * "الحصص الجماعية"): one line per lesson whatever the number of students,
 * from the card's group line, or its private line when it has none.
 */

beforeEach(function () {
    $this->setUpGroups();
    $this->group = $this->group();
    foreach ([$this->student(), $this->student(), $this->student()] as $s) {
        $this->pay($this->join($this->group, $s));
    }
    $this->lesson = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->firstOrFail();
    $this->held = function () {
        $api = app(AttendanceApi::class);
        $api->participantJoined($this->lesson->id, $this->teacher->id, $this->lesson->starts_at);
        foreach ($this->lesson->participants()->where('role', 'student')->pluck('user_id') as $id) {
            $api->participantJoined($this->lesson->id, $id, $this->lesson->starts_at);
        }
        app(ApplyAttendanceDeadlines::class)->handle($this->lesson->ends_at->addMinutes(10));
    };
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('picks a group lesson\'s own line, falling back to the private one', function () {
    $private = new RateLine(null, null, 'lesson', 10000);
    $group = new RateLine(null, null, 'lesson', 15000, format: 'group');
    $quran = new RateLine('quran', null, 'lesson', 12000);

    expect(Pay::pick([$private, $group, $quran], 'quran', 'regular', 'group'))->toBe($group)
        ->and(Pay::pick([$private, $group, $quran], 'quran', 'regular'))->toBe($quran)
        ->and(Pay::pick([$private, $quran], 'quran', 'regular', 'group'))->toBe($quran)
        ->and(Pay::pick([$group], 'quran', 'regular'))->toBeNull();
});

it('pays a group lesson once, at the group rate, however many students it had', function () {
    app(SaveRateCard::class)->handle(null, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [
        ['unit' => 'lesson', 'amount' => '100'],
        ['unit' => 'lesson', 'amount' => '150', 'session_format' => 'group'],
    ]]);
    ($this->held)();

    $lines = TeacherEarning::query()->where('session_id', $this->lesson->id)->get();
    expect($lines)->toHaveCount(1)
        ->and($lines[0]->kind)->toBe('lesson')
        ->and($lines[0]->amount_minor)->toBe(15000)
        ->and($lines[0]->teacher_id)->toBe($this->teacher->id);
});

it('pays a group lesson at the private rate when the card has no group line', function () {
    app(SaveRateCard::class)->handle(null, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [['unit' => 'hour', 'amount' => '120']]]);
    ($this->held)();

    expect(TeacherEarning::query()->where('session_id', $this->lesson->id)->pluck('amount_minor')->all())->toBe([6000]);   // 30 minutes of 120 an hour
});

it('keeps a private and a group line for the same lesson apart on a card, each once', function () {
    $card = app(SaveRateCard::class)->handle(null, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [
        ['unit' => 'lesson', 'amount' => '100'],
        ['unit' => 'lesson', 'amount' => '150', 'session_format' => 'group'],
    ]]);
    expect($card->lines->pluck('session_format')->sort()->values()->all())->toBe(['group', 'private'])
        ->and(fn () => app(SaveRateCard::class)->handle($card, ['name' => 'Standard', 'currency' => 'EGP', 'lines' => [
            ['unit' => 'lesson', 'amount' => '150', 'session_format' => 'group'],
            ['unit' => 'hour', 'amount' => '150', 'session_format' => 'group'],
        ]]))->toThrow(ValidationException::class);
});
