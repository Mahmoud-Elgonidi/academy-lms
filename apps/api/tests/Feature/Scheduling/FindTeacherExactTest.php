<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Queries\FreeTimesQuery;
use Tests\Support\EnrolledStudent;

/*
 * Find a teacher at any minute and any hour (docs/architecture/02, section 2):
 * the board covers the whole day, an exact time ("17:40") is checked for a
 * teacher with the same rules as a proposed one and joins their times or
 * says why not, a search can run at one exact time, and the hold, the trial,
 * the enrolment and the weekly time keep that minute, past midnight too.
 *
 * The fixture (EnrolledStudent): Tuesdays and Saturdays, Nour (Cairo,
 * 17:00–21:00, a lesson at 18:00) and Omar (Riyadh, 18:00–22:00). From 3
 * November no clock changes: London is two hours behind Cairo, three behind
 * Riyadh. Here Layla (Riyadh) also works Saturday nights, 22:00 to 02:00.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->teacher->update(['name' => 'Nour']);
    $this->other->update(['name' => 'Omar']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    $this->layla = User::factory()->withRole(Role::Teacher)->create(['name' => 'Layla', 'timezone' => 'Asia/Riyadh']);
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->layla->id, 'female', ['ar'], [$this->quran]));
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->layla->id, 'version' => 1, 'timezone' => 'Asia/Riyadh', 'effective' => '[2026-09-01,)']);
    $schedule->rules()->create(['teacher_id' => $this->layla->id, 'weekday' => 6, 'local_start' => '22:00', 'duration_min' => 240]);

    // A learner with no course yet.
    $this->learner = User::factory()->withRole(Role::Student)->create(['timezone' => 'Europe/London']);
    app(PeopleApi::class)->saveStudentProfile($this->learner->id, CarbonImmutable::parse('2000-01-01'), null);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The page for the learner (whose clock, London, comes with them), then the rest; a timezone given is set after. */
function exactPage(array $data = []): Testable
{
    $page = Livewire::test(FindTeacher::class)->fillForm(['student_id' => test()->learner->id])
        ->fillForm(array_diff_key($data, ['timezone' => true]) + ['subject_id' => test()->quran, 'start_date' => '2026-11-03', 'per_week' => 2]);

    return isset($data['timezone']) ? $page->fillForm(['timezone' => $data['timezone']]) : $page;
}

function resultOf(Testable $page, string $name): ?array
{
    return collect($page->instance()->results)->first(fn (array $r) => $r['teacher']->name === $name);
}

it('shows all 24 hours, the night folded, and counts teachers free at night', function () {
    $pref = new StudentPreference('Asia/Riyadh', [], 1, 30, CarbonImmutable::parse('2026-11-03'), 2, 2);
    $free = app(FreeTimesQuery::class)->run(new TeacherSearch($this->quran, $pref));

    // Layla on Saturday 23:00 and Sunday 00:00 and 01:00 (Riyadh).
    expect($free['cells'][6][23]['names'])->toBe(['Layla'])
        ->and(array_keys($free['cells'][7]))->toBe([0, 1])
        ->and($free['cells'][7][0]['names'])->toBe(['Layla']);

    $page = exactPage(['timezone' => 'Asia/Riyadh'])
        ->assertSee(__('scheduling::find.board.night_show', ['from' => '12:00 AM', 'to' => '6:00 AM']))
        ->assertSee(__('scheduling::find.board.night_free'));
    $html = $page->html();
    foreach (range(0, 23) as $hour) {
        expect($html)->toContain('data-d="7" data-h="'.$hour.'"');
    }
    // Hours 00–05 are the night: shaded and folded (their labels and 7 cells each).
    expect(substr_count($html, 'data-night'))->toBeGreaterThanOrEqual(6 * 8);

    // Marked by a drag, and taken off as a range.
    $page->call('paint', [[7, 0], [7, 1], [6, 23], [9, 3], [6, 24]], true)
        ->assertSet('cells', [7 => [0, 1], 6 => [23]])
        ->assertSee('Saturday 11 PM–12 AM')
        ->call('clearRange', 7, 0, 2)
        ->assertSet('cells', [6 => [23]]);
});

it('opens with hours sent along from midnight to midnight', function () {
    $page = Livewire::withQueryParams(['subject' => $this->quran, 'days' => '6,7', 'from' => '0', 'to' => '24'])->test(FindTeacher::class)
        ->assertSet('timesTab', 'mine')->assertSet('searched', true);

    expect($page->get('cells'))->toBe([6 => range(0, 23), 7 => range(0, 23)]);
});

it('checks another time for a teacher: added when it fits, else says why', function () {
    $page = exactPage()->call('showTimes', 'mine')->call('paint', [[2, 17], [6, 17]], true)->call('search');
    $nour = $this->teacher->id;

    // 17:40 London is 19:40 in Cairo: free every week. It joins her Tuesday, marked as typed, and can be picked.
    $page->call('checkTime', $nour, 2, '17:40')
        ->assertSet('typedNotes.'.$nour.'.ok', true)
        ->assertSee(__('scheduling::find.exact.typed'));
    $typed = collect(resultOf($page, 'Nour')['chips'][2])->first(fn ($c) => $c->typed);
    expect($typed->studentTime)->toBe('17:40')
        ->and($typed->key())->toBe('2|19:40')
        ->and($typed->coverage)->toBe(8);
    $page->call('pick', $nour, '2|19:40')->assertSet('picked', ['2|19:40']);

    // 16:10 is 18:10 in Cairo, her lesson every week; noon is before her shifts.
    $page->call('checkTime', $nour, 2, '16:10')
        ->assertSet('typedNotes.'.$nour.'.ok', false)
        ->assertSee(__('scheduling::find.exact.reasons.busy'))
        ->call('checkTime', $nour, 6, '12:00')
        ->assertSee(__('scheduling::find.exact.reasons.shifts').' '.__('scheduling::find.exact.when.all'));

    // On leave one Saturday: free on 7 of 8 weeks only, and the date is said.
    $leave = app(RequestTimeOff::class)->handle($nour, '2026-11-14', '2026-11-14', null, null, 'sick', null, $nour);
    app(DecideTimeOff::class)->handle($leave, true, null, $this->admin->id);
    $page->call('checkTime', $nour, 6, '17:40');
    expect($page->get('typedNotes')[$nour])->toBe(['ok' => false, 'text' => __('scheduling::find.exact.short', ['at' => 'Saturday 5:40 PM', 'n' => 7, 'weeks' => 8, 'min' => 8])
        .' '.__('scheduling::find.exact.reasons.time_off').' '.trans_choice('scheduling::find.exact.when.some', 1, ['n' => 1, 'weeks' => 8, 'dates' => '14 Nov']).'.']);

    // Not a time.
    $page->call('checkTime', $nour, 2, '7:5')->assertHasErrors('typed');
});

it('searches at an exact time and enrols at that minute', function () {
    $page = exactPage()->call('searchExact', [2, 6], null, '17:40')
        ->assertSet('searched', true)
        ->assertSee(__('scheduling::find.exact.searched', ['at' => 'Tuesday and Saturday 5:40 PM']));

    // Both are free then; Layla works only Saturday nights.
    expect(collect($page->instance()->results)->pluck('teacher.name')->sort()->values()->all())->toBe(['Nour', 'Omar'])
        ->and(collect($page->instance()->exactAnswer['refused'])->pluck('teacher.name')->all())->toBe(['Layla']);

    $omar = resultOf($page, 'Omar');
    expect(array_map(fn ($c) => $c->key(), $omar['pattern']))->toEqualCanonicalizing(['2|20:40', '6|20:40']);
    $page->call('usePattern', $this->other->id)->call('hold');
    expect($page->get('holdBatch'))->not->toBeNull();

    $plan = Plan::query()->where('session_minutes', 30)->where('sessions_per_week', 2)->sole();
    $page->call('confirm', 'series')
        ->set('mountedActions.0.data.plan_id', $plan->id)
        ->callMountedAction()
        ->assertHasNoErrors();

    $slots = RecurringSlot::query()->where('student_id', $this->learner->id)->orderBy('weekday')->get();
    expect($slots->map(fn ($s) => [$s->weekday, substr((string) $s->local_start, 0, 5)])->all())->toBe([[2, '20:40'], [6, '20:40']]);
    $first = Session::query()->where('teacher_id', $this->other->id)->whereHas('participants', fn ($q) => $q->where('user_id', $this->learner->id))->orderBy('starts_at')->first();
    expect($first->starts_at->setTimezone('Europe/London')->format('D Y-m-d H:i'))->toBe('Tue 2026-11-03 17:40');
});

it('says why nobody is free at an exact time', function () {
    exactPage()->call('searchExact', [1], null, '09:05')
        ->assertSee(__('scheduling::find.exact.nobody.title', ['at' => 'Monday 9:05 AM']))
        ->assertSee('Layla')
        ->assertSee(__('scheduling::find.exact.reasons.shifts'));

    exactPage()->call('searchExact', [], null, '09:05')->assertHasErrors('exact');
    exactPage()->call('searchExact', [1, 2, 3], null, '09:05')->assertHasErrors('exact');
});

it('books a trial on a date at an exact minute', function () {
    $page = exactPage(['mode' => 'trial'])->assertSet('timesTab', 'exact')
        ->call('searchExact', [], '2026-10-10', '17:40');

    // Saturday 10 October, 17:40 in London (before the clocks change): 19:40 in Riyadh.
    $omar = resultOf($page, 'Omar');
    $chip = $omar['chips'][6][0];
    expect($chip->typed)->toBeTrue()->and($chip->occurrences)->toHaveCount(1);

    $page->call('pick', $this->other->id, $chip->key())->call('hold')->call('confirm', 'trial')->assertHasNoErrors();
    $trial = Session::query()->where('type', 'trial')->sole();
    expect($trial->starts_at->setTimezone('Europe/London')->format('Y-m-d H:i'))->toBe('2026-10-10 17:40')
        ->and($trial->teacher_id)->toBe($this->other->id);

    // A date already gone is refused.
    exactPage(['mode' => 'trial'])->call('searchExact', [], '2026-10-01', '17:40')->assertHasErrors('exact');
});

it('books a weekly time that starts at night and runs past midnight', function () {
    $region = PriceRegion::query()->where('is_default', true)->value('id');
    $plan = Plan::query()->create(['name_ar' => 'ليلي', 'name_en' => 'Night', 'session_minutes' => 45, 'sessions_per_block' => 4, 'sessions_per_week' => 1, 'validity_days' => 42]);
    $plan->prices()->create(['price_region_id' => $region, 'amount_minor' => 20000, 'currency' => 'SAR']);

    // Sunday 00:10 is inside Layla's Saturday night: a Sunday lesson.
    $sunday = exactPage(['timezone' => 'Asia/Riyadh', 'per_week' => 1])->call('searchExact', [7], null, '00:10');
    expect(resultOf($sunday, 'Layla')['candidates'][0]->key())->toBe('7|00:10');

    // Saturday 23:30 in Riyadh for 45 minutes ends at 00:15 on Sunday, inside Layla's night.
    $page = exactPage(['timezone' => 'Asia/Riyadh', 'per_week' => 1, 'duration' => 45])
        ->call('searchExact', [6], null, '23:30');
    $layla = resultOf($page, 'Layla');
    expect($layla['candidates'][0]->studentWeekday)->toBe(6)
        ->and($page->instance()->lessonLabel($layla['candidates'][0]))->toBe('Saturday 11:30 PM–12:15 AM');

    $page->call('usePattern', $this->layla->id)->call('hold')
        ->call('confirm', 'series')->set('mountedActions.0.data.plan_id', $plan->id)->callMountedAction()->assertHasNoErrors();

    $slot = RecurringSlot::query()->where('teacher_id', $this->layla->id)->sole();
    expect([$slot->weekday, substr((string) $slot->local_start, 0, 5), $slot->duration_min])->toBe([6, '23:30', 45]);
    $first = Session::query()->where('teacher_id', $this->layla->id)->orderBy('starts_at')->first();
    expect($first->starts_at->setTimezone('Asia/Riyadh')->format('D H:i'))->toBe('Sat 23:30')
        ->and($first->ends_at->setTimezone('Asia/Riyadh')->format('D H:i'))->toBe('Sun 00:15');

});
