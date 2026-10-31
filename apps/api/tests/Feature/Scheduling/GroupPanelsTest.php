<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Classroom\Filament\Teacher\MyLessonsToday;
use Modules\Communication\Models\Notification;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Teacher\MyWeek;
use Modules\Scheduling\Queries\ShiftImpactQuery;
use Tests\Support\GroupLessons;

uses(GroupLessons::class);

/*
 * Group lessons on the panels (docs/architecture/02, "الحصص الجماعية"): the
 * family's "I won't attend" excuses their own seat and never cancels the
 * lesson; the live board asks whose seat; the teacher's lists show the whole
 * roster; the teacher's messages and shift conflicts name the group, never
 * its first student.
 */

beforeEach(function () {
    $this->setUpGroups(teacherMax: 5);
    $this->group = $this->group(capacity: 5);
    $this->kids = [];
    foreach (['Amira Hassan', 'Badr Salem', 'Dina Khalil'] as $name) {
        $s = $this->student();
        $s->forceFill(['name' => $name])->save();
        $this->kids[] = $s;
        $this->pay($this->join($this->group, $s));
    }
    [$this->a, $this->b, $this->c] = $this->kids;
    $this->lesson = Session::query()->where('group_id', $this->group->id)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

$seat = fn (Session $lesson, string $studentId) => SessionParticipant::query()->where('session_id', $lesson->id)->where('user_id', $studentId)->sole();

it('lets a family say "I won\'t attend" a group lesson: their own seat is excused, the lesson goes on', function () use ($seat) {
    $this->actingAs($this->a);
    Filament::setCurrentPanel('family');

    $page = Livewire::test(FamilyLessons::class)
        ->assertSee(__('scheduling::group_outcomes.excuse.button'))
        ->assertSee(trans_choice('scheduling::group_outcomes.with_classmates', 2, ['count' => 2]))
        ->assertSee('Badr')->assertDontSee('Badr Salem')                                   // classmates by first name only
        ->assertSee(__('scheduling::group_outcomes.course.seat', ['seat' => 1, 'capacity' => 5]));
    $card = collect($page->instance()->lessons['upcoming'])->firstWhere('id', $this->lesson->id);
    expect($card['cancellable'])->toBeTrue()
        ->and($card['group']['badge'])->toContain(__('scheduling::group_outcomes.format.group'));

    $page->assertActionVisible(TestAction::make('cancel')->arguments(['session' => $this->lesson->id, 'group' => true]))
        ->callAction(TestAction::make('cancel')->arguments(['session' => $this->lesson->id, 'group' => true]), data: ['reason' => 'travel'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('scheduling::group_outcomes.excuse.done'));

    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Scheduled)
        ->and($seat($this->lesson, $this->a->id)->outcome)->toBe(GroupOutcomes::EXCUSED)
        ->and($seat($this->lesson, $this->b->id)->outcome)->toBeNull();

    // Excused once: the lesson stays on their list with their seat's outcome, and nothing left to excuse.
    $card = collect(Livewire::test(FamilyLessons::class)->instance()->lessons['upcoming'])->firstWhere('id', $this->lesson->id);
    expect($card['cancellable'])->toBeFalse()
        ->and($card['group']['seat'])->toBe(__('scheduling::group_outcomes.family.seat', ['outcome' => __('scheduling::group_outcomes.outcomes.excused')]));
});

it('shows a child in the kids\' look how many are with them, never their names', function () {
    foreach ($this->kids as $kid) {
        app(PeopleApi::class)->saveStudentProfile($kid->id, CarbonImmutable::parse('2017-03-01'), null);
    }
    $this->actingAs($this->b);
    Filament::setCurrentPanel('family');

    Livewire::test(FamilyLessons::class)
        ->assertSee(trans_choice('scheduling::group_outcomes.with_classmates', 2, ['count' => 2]))
        ->assertDontSee('Amira')->assertDontSee('Dina')
        ->assertDontSee(__('scheduling::group_outcomes.excuse.button'));
});

it('asks the live board whose seat to excuse on a group lesson, and refuses without one', function () use ($seat) {
    CarbonImmutable::setTestNow($this->lesson->starts_at->subHours(30));
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    // No student picked: refused with the reason, nothing changes.
    Livewire::test(LiveBoard::class)
        ->callAction('cancel', data: ['by' => 'student', 'reason' => 'illness', 'waive' => false], arguments: ['session' => $this->lesson->id, 'by' => 'student', 'reason' => 'illness'])
        ->assertHasActionErrors(['student' => 'required']);
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Scheduled);

    Livewire::test(LiveBoard::class)
        ->callAction('cancel', data: ['by' => 'student', 'student' => $this->b->id, 'reason' => 'illness', 'waive' => false], arguments: ['session' => $this->lesson->id, 'by' => 'student', 'reason' => 'illness'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('scheduling::board.done.excused'));

    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Scheduled)
        ->and($seat($this->lesson, $this->b->id)->outcome)->toBe(GroupOutcomes::EXCUSED)
        ->and($seat($this->lesson, $this->a->id)->outcome)->toBeNull();

    // A seat already excused is not offered again.
    Livewire::test(LiveBoard::class)
        ->callAction('cancel', data: ['by' => 'student', 'student' => $this->b->id, 'reason' => 'illness', 'waive' => false], arguments: ['session' => $this->lesson->id, 'by' => 'student', 'reason' => 'illness'])
        ->assertHasActionErrors(['student']);
});

it('shows the teacher the whole roster of a group lesson today and in their week', function () {
    CarbonImmutable::setTestNow($this->lesson->starts_at->subHours(2));
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');

    $today = Livewire::test(MyLessonsToday::class)
        ->assertSee(__('classroom::classroom.today.group', ['taken' => 3, 'capacity' => 5]))
        ->assertSee('Amira Hassan')->assertSee('Badr Salem')->assertSee('Dina Khalil')
        ->instance()->lessons;
    expect($today)->toHaveCount(1)
        ->and($today[0]['group']['names'])->toBe('Amira Hassan, Badr Salem, Dina Khalil');

    $week = Livewire::test(MyWeek::class)->assertSee('Dina Khalil')->instance()->week;
    $card = collect($week['days'])->flatMap(fn (array $d) => $d['lessons'])->firstWhere('id', $this->lesson->id);
    expect($card['with'])->toBe('Amira Hassan, Badr Salem, Dina Khalil')
        ->and($card['group']['badge'])->toBe(__('scheduling::group_outcomes.family.teacher_badge', ['taken' => 3, 'capacity' => 5]));
});

it('names the group, not its first student, in the teacher\'s messages', function () {
    $this->teacher->forceFill(['locale' => 'en'])->save();
    app(CancelSession::class)->handle($this->lesson, 'academy', 'other', null, $this->admin->id);

    $body = Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'scheduling.session_cancelled_teacher')->sole();
    expect($body->title)->toContain('Quran group')->toContain('6:00 PM')->not->toContain('Amira')
        ->and(Notification::query()->where('user_id', $this->a->id)->where('template_key', 'scheduling.session_cancelled')->sole()->body)->toContain('Amira Hassan');
});

it('makes a group one shift conflict, "group · n students"', function () {
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ['ar'], [$this->quran], acceptsGroups: true, maxGroupSize: 5));
    $shifts = WeeklyShifts::fromArray([['weekday' => 1, 'start' => '09:00', 'end' => '12:00']], 'Africa/Cairo');
    $conflicts = app(ShiftImpactQuery::class)->conflicts($this->teacher->id, $shifts, CarbonImmutable::now());

    expect($conflicts)->toHaveCount(2)                                  // Tuesdays and Saturdays, each the group's
        ->and(array_column($conflicts, 'student'))->each->toBe(trans_choice('scheduling::group_outcomes.conflict', 3, ['count' => 3]))
        ->and(array_column($conflicts, 'group_id'))->each->toBe($this->group->id);
});

it('gives the family\'s apps a group course\'s seat, on the balance, the journey and each lesson', function () {
    $token = function ($user) {
        $this->actAsTenant($this->academy);
        $plain = $user->createToken('phone')->plainTextToken;
        $this->clearTenant();
        app('auth')->forgetGuards();

        return $plain;
    };
    $guardian = $token($this->payer);
    $balance = collect($this->withToken($guardian)->getJson('http://noor.lms.test/api/v1/family/balance?learner_id='.$this->b->id)->assertOk()->json('data'));
    expect($balance->firstWhere('format', 'group')['group'])->toBe(['id' => $this->group->id, 'capacity' => 5, 'seats_taken' => 3, 'seat' => 2]);

    $student = $token($this->c);
    $course = $this->withToken($student)->getJson('http://noor.lms.test/api/v1/learning/journey/map')->assertOk()->json('data.courses.0');
    expect($course['format'])->toBe('group')
        ->and($course['group'])->toMatchArray(['capacity' => 5, 'seats_taken' => 3, 'seat' => 3]);

    $lesson = collect($this->withToken($student)->getJson('http://noor.lms.test/api/v1/family/lessons')->assertOk()->json('data'))->firstWhere('id', $this->lesson->id);
    expect($lesson['group'])->toMatchArray(['capacity' => 5, 'seats_taken' => 3, 'seat_number' => 3, 'classmates' => ['Amira', 'Badr']]);
});
