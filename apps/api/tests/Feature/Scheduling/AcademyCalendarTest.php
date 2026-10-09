<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\ExportLessons;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Calendar\Lanes;
use Modules\Scheduling\Filament\Pages\AcademyCalendar;
use Modules\Scheduling\Queries\Calendar\CalendarFilter;
use Modules\Scheduling\Queries\Calendar\CalendarRange;
use Modules\Scheduling\Queries\Calendar\LessonsCalendar;
use Modules\SharedKernel\Time\Timezones;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    // Monday 5 October 2026: the student's 16 lessons, Tuesdays and Saturdays at 18:00 Cairo (15:00 UTC) from 6 October.
    $this->enrolStudentOnPlan();
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create(['timezone' => 'Africa/Cairo']);
    $this->lessons = Session::query()->orderBy('starts_at')->get();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The week from Saturday 10 October on the clock given, as instants. */
function calendarWeek(string $tz = 'Africa/Cairo', string $unit = 'week', string $date = '2026-10-12'): array
{
    return CalendarRange::make($unit, $date, $tz, CarbonImmutable::now())->bounds();
}

it('makes days, weeks from Saturday and months on the viewer\'s clock, a 25-hour day when the clock goes back', function () {
    $now = CarbonImmutable::now();
    $week = CalendarRange::make('week', '2026-10-14', 'Africa/Cairo', $now);
    $month = CalendarRange::make('month', '2026-10-14', 'Africa/Cairo', $now);
    $london = CalendarRange::make('day', '2026-10-25', 'Europe/London', $now);
    [$from, $to] = $london->bounds();

    expect($week->first->toDateString())->toBe('2026-10-10')->and($week->last->toDateString())->toBe('2026-10-16')
        ->and($week->step(1))->toBe('2026-10-17')->and($week->step(-1))->toBe('2026-10-03')
        ->and(CalendarRange::make('week', null, 'Africa/Cairo', $now)->first->toDateString())->toBe('2026-10-03')
        ->and(CalendarRange::make('nonsense', 'not-a-day', 'Africa/Cairo', $now)->unit)->toBe('week')
        ->and($month->first->toDateString())->toBe('2026-10-01')->and($month->last->toDateString())->toBe('2026-10-31')
        ->and($month->weeks()[0][0]->toDateString())->toBe('2026-09-26')
        ->and(count($month->weeks()))->toBe(6)
        ->and($from->toIso8601String())->toBe('2026-10-24T23:00:00+00:00')
        ->and($from->diffInHours($to))->toEqual(25.0);
});

it('lists a range\'s lessons and counts them by status, by day on the viewer\'s clock, by teacher and by subject', function () {
    $calendar = app(LessonsCalendar::class);
    [$from, $to] = calendarWeek();
    [$saturday, $tuesday] = [$this->lessons[1], $this->lessons[2]];
    Session::query()->whereKey($saturday->id)->update(['status' => 'completed']);
    app(CancelSession::class)->handle(Session::query()->with('participants')->findOrFail($tuesday->id), 'student', 'travel', 'Umrah', $this->admin->id);

    $rows = $calendar->lessons(new CalendarFilter, $from, $to);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->starts_at->setTimezone('Africa/Cairo')->format('D H:i'))->toBe('Sat 18:00')
        ->and($rows[0]->teacher)->toBe($this->teacher->name)->and($rows[0]->student)->toBe($this->student->name)->and($rows[0]->subject_en)->toBe('Quran')
        ->and($rows[0]->student_tz)->toBe('Europe/London')->and($rows[0]->minutes)->toBe(30)
        ->and($rows[1]->status)->toBe('cancelled')->and($rows[1]->reason)->toBe('travel')->and($rows[1]->reason_by)->toBe('student')->and($rows[1]->reason_note)->toBe('Umrah');

    $byStatus = $calendar->byStatus(new CalendarFilter, $from, $to);
    expect(array_keys($byStatus))->toBe(LessonsCalendar::statuses())
        ->and($byStatus['completed'])->toBe(['count' => 1, 'minutes' => 30])
        ->and($byStatus['cancelled'])->toBe(['count' => 1, 'minutes' => 30])
        ->and($byStatus['scheduled']['count'])->toBe(0);

    // Narrowing: by teacher, student, subject, kind and statuses (ids that are not ids are dropped).
    $count = fn (CalendarFilter $f) => count($calendar->lessons($f, $from, $to));
    $other = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    expect($count(new CalendarFilter(teacherId: $this->other->id)))->toBe(0)
        ->and($count(new CalendarFilter(teacherId: $this->teacher->id)))->toBe(2)
        ->and($count(new CalendarFilter(studentId: $this->student->id)))->toBe(2)
        ->and($count(new CalendarFilter(studentId: $this->admin->id)))->toBe(0)
        ->and($count(new CalendarFilter(subjectId: $this->quran)))->toBe(2)
        ->and($count(new CalendarFilter(subjectId: $other)))->toBe(0)
        ->and($count(new CalendarFilter(type: 'trial')))->toBe(0)
        ->and($count(new CalendarFilter(statuses: ['cancelled', 'bogus'])))->toBe(1)
        ->and(CalendarFilter::of('x', null, null, 'party', ['cancelled'])->isEmpty())->toBeFalse()
        ->and(CalendarFilter::of('x', null, null, 'party', [])->isEmpty())->toBeTrue();

    // 15:00 UTC on Saturday is 04:00 on Sunday in Auckland: the lesson is Sunday's there.
    [$aFrom, $aTo] = calendarWeek('Pacific/Auckland', 'month', '2026-10-01');
    expect($calendar->byDay(new CalendarFilter, $aFrom, $aTo, 'Pacific/Auckland'))->toHaveKey('2026-10-11')
        ->and($calendar->byDay(new CalendarFilter, $aFrom, $aTo, 'Pacific/Auckland'))->not->toHaveKey('2026-10-10')
        ->and($calendar->byDay(new CalendarFilter, $from, $to, 'Africa/Cairo'))->toBe(['2026-10-10' => ['completed' => 1], '2026-10-13' => ['cancelled' => 1]]);

    // October's eight lessons on the Cairo clock (the 31st's is October's too, after the clocks go back).
    $byTeacher = $calendar->byTeacher(new CalendarFilter, ...calendarWeek('Africa/Cairo', 'month'));
    expect($byTeacher)->toHaveCount(1)
        ->and($byTeacher[0]['total'])->toBe(8)->and($byTeacher[0]['statuses']['completed'])->toBe(1)->and($byTeacher[0]['statuses']['scheduled'])->toBe(6)
        ->and($byTeacher[0]['minutes'])->toBe(7 * 30)   // the cancelled one is not taught time
        ->and($calendar->bySubject(new CalendarFilter, ...calendarWeek('Africa/Cairo', 'month'))[0]['name'])->toBe(app()->getLocale() === 'ar' ? 'قرآن' : 'Quran');
});

it('places overlapping lessons side by side and folds what does not fit into one "+n"', function () {
    $item = fn (string $id, int $start, int $end) => ['id' => $id, 'start' => $start, 'end' => $end];

    $two = Lanes::place([$item('a', 600, 630), $item('b', 615, 645), $item('c', 700, 730)]);
    expect(collect($two['items'])->keyBy('id')->map(fn ($i) => [$i['lane'], $i['lanes']])->all())
        ->toBe(['a' => [0, 2], 'b' => [1, 2], 'c' => [0, 1]])
        ->and($two['more'])->toBe([]);

    $crowd = Lanes::place([$item('a', 600, 630), $item('b', 600, 630), $item('c', 600, 630), $item('d', 610, 650), $item('e', 640, 660)], 3);
    expect(array_column($crowd['items'], 'id'))->toBe(['a', 'b', 'e'])
        ->and(collect($crowd['items'])->every(fn ($i) => $i['lanes'] === 3 && $i['lane'] < 2))->toBeTrue()
        ->and($crowd['more'])->toHaveCount(1)
        ->and($crowd['more'][0])->toMatchArray(['start' => 600, 'end' => 650, 'count' => 2, 'lane' => 2, 'lanes' => 3, 'ids' => ['c', 'd']]);
});

it('shows the calendar to whoever runs the lessons: week, day, month, list and by status, narrowed, with details and a spreadsheet', function () {
    Session::query()->whereKey($this->lessons[0]->id)->update(['status' => 'completed']);
    $accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->clearTenant();

    $this->actingAs($this->scheduler)->get('http://noor.lms.test/admin/calendar?date=2026-10-12')->assertOk()
        ->assertSee(__('scheduling::calendar.title'))
        ->assertSee('data-live-channel="academy.'.$this->academy->id.'.board"', false)
        ->assertSee($this->student->name);
    // The teacher's, the student's and the course's pages open their lessons on the calendar.
    $this->get('http://noor.lms.test/admin/people/teachers/'.$this->teacher->id.'/edit')->assertOk()->assertSee('/admin/calendar?teacher='.$this->teacher->id, false);
    $this->get('http://noor.lms.test/admin/people/students/'.$this->student->id.'/edit')->assertOk()->assertSee('/admin/calendar?student='.$this->student->id, false);
    $this->get('http://noor.lms.test/admin/enrollments/'.$this->booked->enrollmentId)->assertOk()
        ->assertSee(e('/admin/calendar?student='.$this->student->id.'&subject='.$this->quran), false);
    foreach ([$this->teacher, $accountant] as $someone) {
        app('auth')->forgetGuards();
        $this->flushSession();
        $this->actingAs($someone)->get('http://noor.lms.test/admin/calendar')->assertForbidden();
    }

    $this->actAsTenant($this->academy);
    $this->actingAs($this->scheduler);
    Filament::setCurrentPanel('academy');
    $l = 'scheduling::calendar.';
    $page = Livewire::test(AcademyCalendar::class)
        ->assertSet('unit', 'week')->assertSet('zone', 'Africa/Cairo')
        ->assertSee('3 – 9 '.CarbonImmutable::parse('2026-10-03')->locale(app()->getLocale())->translatedFormat('F Y'))
        ->assertSee($this->student->name)->assertSeeHtml('data-status="completed"');

    // The teacher's week shows their shift hours behind the lessons.
    $page->set('teacher', $this->teacher->id)->assertSeeHtml('data-kind="shift"')
        ->set('teacher', $this->other->id)->assertDontSee($this->student->name)
        ->call('clearFilters')->assertSet('teacher', null)->assertSee($this->student->name);

    // Statuses narrow; the chips still count every status.
    $page->call('toggleStatus', 'cancelled')->assertSet('statuses', ['cancelled'])->assertDontSeeHtml('class="cal-ev"')
        ->call('toggleStatus', 'completed')->assertSet('statuses', ['completed', 'cancelled'])
        ->call('clearStatuses')->assertSet('statuses', []);

    // Day: a row per teacher on shift (the other one is free that day), and the student's lesson.
    $page->call('openDay', '2026-10-06')->assertSet('unit', 'day')
        ->assertSee($this->teacher->name)->assertSee($this->other->name)->assertSee(__($l.'free_today'))
        ->call('go', 1)->assertSet('date', '2026-10-07')->assertSee(__($l.'empty.day'));

    // Month, list and by status.
    $page->call('setUnit', 'month')->assertSet('unit', 'month')->assertSeeHtml('class="cal-mday"')
        ->call('setDisplay', 'list')->assertSee('18:00 – 18:30')
        ->call('setDisplay', 'summary')->assertSee(__($l.'summary.teachers'))->assertSee($this->teacher->name)
        ->call('focus', 'teacher', $this->teacher->id)->assertSet('teacher', $this->teacher->id)->assertSet('display', 'calendar');

    // A lesson's details: who, when on each clock, and the way to the student's account.
    $page->call('today')->mountAction('lesson', ['id' => $this->lessons[0]->id])
        ->assertMountedActionModalSee([$this->student->name, __($l.'statuses.completed'), __($l.'details.account'),
            trim(__($l.'details.their_time', ['day' => '', 'time' => '16:00', 'city' => Timezones::city('Europe/London')]))]);

    Livewire::test(AcademyCalendar::class)->callAction('export')->assertFileDownloaded('lessons-2026-10-03-2026-10-09.xlsx');
});

it('writes the range to a spreadsheet: counts by status, teacher and subject, then every lesson', function () {
    app(CancelSession::class)->handle(Session::query()->with('participants')->findOrFail($this->lessons[1]->id), 'student', 'travel', null, $this->admin->id);
    app()->setLocale('en');
    $range = CalendarRange::make('month', '2026-10-01', 'Africa/Cairo', CarbonImmutable::now());
    $export = app(ExportLessons::class);
    $path = $export->handle($range, new CalendarFilter);

    $reader = new Reader;
    $reader->open($path);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);

    $lessons = $sheets['Lessons'];
    expect(array_keys($sheets))->toBe(['Summary', 'Lessons'])
        ->and($export->filename($range))->toBe('lessons-2026-10-01-2026-10-31.xlsx')
        ->and($sheets['Summary'][0][0])->toBe('Lessons from 2026-10-01 to 2026-10-31')
        ->and(collect($sheets['Summary'])->first(fn ($r) => ($r[0] ?? null) === 'Cancelled'))->toEqual(['Cancelled', 1, 0.5])
        ->and(collect($sheets['Summary'])->first(fn ($r) => ($r[0] ?? null) === $this->teacher->name))->toEqual([$this->teacher->name, 7, 0, 0, 0, 0, 0, 1, 8, 3.5])
        ->and(count($lessons))->toBe(1 + 8)   // October holds 8 of the 16
        ->and($lessons[1])->toEqual(['2026-10-06', '18:00', '18:30', 30, $this->teacher->name, $this->student->name, 'Quran', 'Regular', 'Booked', 'Awaiting payment', '', ''])
        ->and($lessons[2][8])->toBe('Cancelled')->and($lessons[2][10])->toBe('Travel');
});

it('keeps another academy\'s lessons out of the calendar', function () {
    $mine = Session::query()->count();
    $this->clearTenant();
    $this->actAsTenant($this->createAcademy(['slug' => 'rawda']));
    [$from, $to] = calendarWeek('Africa/Cairo', 'month', '2026-10-01');

    expect(app(LessonsCalendar::class)->lessons(new CalendarFilter, $from, $to))->toBe([])
        ->and(app(LessonsCalendar::class)->lesson($this->lessons[0]->id))->toBeNull()
        ->and($mine)->toBeGreaterThan(0)
        ->and(DB::table('sessions')->count())->toBe(0);
});
