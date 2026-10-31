<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Billing\Models\InvoiceLine;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Filament\GroupView;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Filament\Pages\GroupDetails;
use Modules\Scheduling\Filament\Pages\Groups;
use Modules\Scheduling\Filament\Pages\GroupSettings;
use Tests\Support\GroupLessons;

/*
 * The back office's group screens (docs/architecture/02, "الحصص
 * الجماعية"): Settings › Group lessons, the Groups page and a group's own
 * page (seats, members, ending it), and Find a teacher's group mode: the
 * open groups first, joining one (with what makes up for the lessons a late
 * joiner missed, and the first invoice with it), a new group from the free
 * times, and private mode's hint that a teacher has a group at those hours.
 */

uses(GroupLessons::class);

beforeEach(function () {
    $this->setUpGroups();
    // The teacher's evenings in Cairo: Tuesdays and Saturdays from 17:00, four hours.
    $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->teacher->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
    foreach ([2, 6] as $weekday) {
        $schedule->rules()->create(['teacher_id' => $this->teacher->id, 'weekday' => $weekday, 'local_start' => '17:00', 'duration_min' => 240]);
    }
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    app()->setLocale('ar');
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The group's first $held lessons taught, and the clock just after them. */
function groupScreensHeld(LessonGroup $group, int $held): void
{
    $lessons = Session::query()->where('group_id', $group->id)->orderBy('starts_at')->take($held)->get();
    Session::query()->whereIn('id', $lessons->modelKeys())->update(['status' => 'completed']);
    CarbonImmutable::setTestNow($lessons->last()->ends_at->addHour());
}

it('saves the academy\'s group settings, and warns when ages mix', function () {
    Livewire::test(GroupSettings::class)->assertOk()
        ->assertFormSet(['late_join' => 'discount', 'allow_mixed_ages' => false, 'group_min_default' => null])
        ->assertSeeHtml(['data-value="discount"', 'data-value="private"', 'data-value="group"', 'data-value="none"'])
        ->assertDontSee(__('scheduling::group_screens.settings.ages.warning_title'))
        ->fillForm(['late_join' => 'private', 'allow_mixed_ages' => true, 'group_min_default' => 3])
        ->assertSee(__('scheduling::group_screens.settings.ages.warning_title'))
        ->call('save')->assertHasNoFormErrors()->assertNotified(__('scheduling::group_screens.settings.saved'));

    $settings = SchedulingSettings::current();
    expect([$settings->late_join, $settings->allow_mixed_ages, $settings->group_min_default])->toBe(['private', true, 3])
        ->and(json_decode(DB::table('audit_logs')->where('action', 'lesson_group.settings')->value('data'), true))->toMatchArray(['late_join' => 'private', 'mixed' => 'on', 'min' => 3]);

    // A new group waits for three, within its seats.
    expect($this->group(capacity: 4)->min_to_start)->toBe(3);

    Livewire::test(GroupSettings::class)->fillForm(['group_min_default' => 30])->call('save')->assertHasFormErrors(['group_min_default']);
});

it('lists the groups with their counts, filters them, and explains groups when there are none', function () {
    Livewire::test(Groups::class)->assertOk()
        ->assertSee(__('scheduling::group_screens.list.empty.title'))
        ->assertSee(__('scheduling::group_screens.list.empty.points.plan.title'));

    $running = $this->group(capacity: 3);
    $this->join($running, $this->student());
    $this->join($running, $this->student());
    $forming = $this->otherGroup();             // another teacher's, running with one
    $waiting = $this->group(capacity: 4, minToStart: 2, teacherId: $forming->teacher_id);
    expect($waiting->refresh()->status)->toBe(LessonGroup::FORMING);

    $page = Livewire::test(Groups::class)
        ->assertSee(__('scheduling::group_screens.list.stats.running'))->assertSee(__('scheduling::group_screens.list.stats.free'))
        ->assertSee(__('scheduling::group_screens.status.running'))->assertSee(__('scheduling::group_screens.status.forming'))
        ->assertSee(__('scheduling::group_screens.card.seats', ['taken' => 2, 'capacity' => 3]))
        ->assertSee(trans_choice('scheduling::group_screens.card.waits', 2, ['n' => 2]));
    expect($page->instance()->board['counts'])->toBe(['running' => 2, 'forming' => 1, 'ended' => 0, 'free' => 1 + 2 + 4]);

    $page->set('status', 'forming');
    expect(collect($page->instance()->board['groups'])->pluck('id')->all())->toBe([$waiting->id]);
    $page->set('status', null)->set('teacher', $this->teacher->id);
    expect(collect($page->instance()->board['groups'])->pluck('id')->all())->toBe([$running->id]);
    $page->call('clearFilters');
    expect($page->instance()->board['groups'])->toHaveCount(3);
});

it('shows a group\'s members by first name and initial, changes its seats within the rules, and ends it after saying what that does', function () {
    $group = $this->group(capacity: 3);
    $one = $this->student();
    $one->forceFill(['name' => 'Ahmad Mansour Saleh'])->save();
    $this->join($group, $one);
    $this->join($group, $this->student());

    $page = Livewire::test(GroupDetails::class, ['group' => $group->id])->assertOk()
        ->assertSee('Ahmad S.')->assertDontSee('Ahmad Mansour Saleh')
        ->assertSee(__('scheduling::group_screens.details.members.privacy'))
        ->assertSee(__('scheduling::group_screens.card.seats', ['taken' => 2, 'capacity' => 3]));

    // Up to the teacher's most (4); never below the two in it: refused with the reason.
    $page->fillForm(['capacity' => 4], 'seatsForm')->call('saveSeats')->assertNotified(__('scheduling::group_screens.details.seats.saved'));
    expect($group->refresh()->capacity)->toBe(4);
    $page->fillForm(['capacity' => 5], 'seatsForm')->call('saveSeats')->assertHasFormErrors(['capacity'], 'seatsForm');
    expect($group->refresh()->capacity)->toBe(4);

    $lessons = Session::query()->where('group_id', $group->id)->where('status', 'scheduled')->count();
    $page->mountAction('end')
        ->assertMountedActionModalSee([
            trans_choice('scheduling::group_screens.details.end.effects.courses', 2, ['n' => 2, 'date' => CarbonImmutable::now()->locale('ar')->translatedFormat('j F')]),
            trans_choice('scheduling::group_screens.details.end.effects.lessons', $lessons, ['n' => $lessons]),
        ])
        ->callMountedAction()->assertHasActionErrors(['reason']);
    $page->mountAction('end')->fillForm(['reason' => 'انتقل المعلّم'])->callMountedAction()->assertHasNoActionErrors()
        ->assertNotified(__('scheduling::group_screens.details.end.done'));
    expect($group->refresh()->status)->toBe(LessonGroup::ENDED)
        ->and(Session::query()->where('group_id', $group->id)->where('status', 'scheduled')->count())->toBe(0);

    Livewire::test(GroupDetails::class, ['group' => $group->id])->assertSee(__('scheduling::group_screens.status.ended'))->assertActionHidden('end')->assertActionHidden('add');
});

it('lists the open groups first in group mode, for the student, with the seat they take', function () {
    $group = $this->group(capacity: 3);
    $this->join($group, $this->student());
    $late = $this->student();

    $page = Livewire::withQueryParams(['mode' => 'group', 'student' => $late->id, 'subject' => $this->quran])->test(FindTeacher::class)->assertOk()
        ->assertSet('data.mode', 'group')
        ->assertSee(__('scheduling::group_screens.find.mode.group.title'))
        ->assertSee(__('scheduling::group_screens.find.open.title'))
        ->assertSee(__('scheduling::group_screens.find.or_new'))
        ->assertSee(__('scheduling::group_screens.card.seats', ['taken' => 1, 'capacity' => 3]))
        ->assertSee(__('scheduling::group_screens.find.open.join'));
    expect(collect($page->instance()->openGroups)->pluck('id')->all())->toBe([$group->id]);

    // Hours marked away from the group's: it is left out, and the reason says so.
    $page->call('toggleCell', 1, 9);
    expect($page->instance()->openGroups)->toBe([]);
    $page->assertSee(__('scheduling::group_screens.find.open.none.hours'));

    // Without a student, joining asks for one first.
    Livewire::withQueryParams(['mode' => 'group', 'subject' => $this->quran])->test(FindTeacher::class)
        ->call('join', $group->id)->assertNotified(__('scheduling::group_screens.find.join.student_first'));
});

it('joins a running group with the lessons missed taken off the first invoice, as the quote shows', function () {
    $group = $this->group(capacity: 3);
    $this->join($group, $this->student());
    groupScreensHeld($group, 3);
    $late = $this->student();

    $page = Livewire::withQueryParams(['group' => $group->id, 'student' => $late->id])->test(FindTeacher::class)
        ->assertSet('data.mode', 'group')->assertSet('focusGroup', $group->id)
        ->call('join', $group->id)
        ->assertActionMounted('joinGroup')
        ->assertMountedActionModalSee([
            __('scheduling::group_screens.find.join.seat', ['to' => 2, 'capacity' => 3]),
            trans_choice('scheduling::group_screens.find.join.late.title', 3, ['n' => 3]),
            __('scheduling::group_screens.late_join.discount.title'), __('scheduling::group_screens.late_join.private.title'),
            __('scheduling::group_screens.late_join.group.title'), __('scheduling::group_screens.late_join.none.title'),
            __('billing::discounts.quote.late_join', ['count' => 3]),
            __('scheduling::group_screens.late_join.notes.private', ['minutes' => 90, 'days' => 30]),
        ])
        ->assertActionDataSet(['late_join' => 'discount', 'late_lessons' => 3, 'plan_id' => $this->groupPlan->id, 'payer_id' => $this->payer->id]);

    // Fewer lessons, never more.
    $page->fillForm(['late_lessons' => 5])->callMountedAction()->assertHasActionErrors(['late_lessons']);
    $page->fillForm(['late_lessons' => 2])
        ->assertMountedActionModalSee(__('billing::discounts.quote.late_join', ['count' => 2]))
        ->callMountedAction()->assertHasNoActionErrors()
        ->assertNotified(__('scheduling::group_screens.find.join.done', ['name' => $late->name, 'seat' => 2, 'capacity' => 3]));

    $enrollment = DB::table('group_members')->where('group_id', $group->id)->where('student_id', $late->id)->value('enrollment_id');
    $invoice = DB::table('invoice_lines')->where('subscription_id', DB::table('subscriptions')->where('enrollment_id', $enrollment)->value('id'))->value('invoice_id');
    $line = InvoiceLine::query()->where('invoice_id', $invoice)->where('kind', 'discount')->sole();
    expect($line->meta['source'])->toBe('late_join')->and(-$line->amount_minor)->toBe(6000);
});

it('gives private makeup minutes when the office chooses it, and says why a full group is refused', function () {
    $group = $this->group(capacity: 2);
    $this->join($group, $this->student());
    groupScreensHeld($group, 3);
    $late = $this->student();

    Livewire::withQueryParams(['group' => $group->id, 'student' => $late->id])->test(FindTeacher::class)
        ->call('join', $group->id)
        ->fillForm(['late_join' => 'private'])
        ->assertMountedActionModalDontSee(__('billing::discounts.quote.late_join', ['count' => 3]))
        ->callMountedAction()->assertHasNoActionErrors();
    $enrollment = DB::table('group_members')->where('group_id', $group->id)->where('student_id', $late->id)->value('enrollment_id');
    $account = DB::table('enrollments')->where('id', $enrollment)->value('credit_account_id');
    expect((int) DB::table('credit_lots')->where('account_id', $account)->where('source', 'makeup')->sum('granted_min'))->toBe(90);

    // Full now: the refusal is said.
    $third = $this->student();
    Livewire::withQueryParams(['mode' => 'group', 'subject' => $this->quran, 'student' => $third->id])->test(FindTeacher::class)
        ->tap(fn ($p) => expect($p->instance()->openGroups)->toBe([]));
    Livewire::withQueryParams(['mode' => 'group', 'subject' => $this->quran, 'student' => $third->id])->test(FindTeacher::class)
        ->call('join', $group->id)->callMountedAction()
        ->assertNotified(__('scheduling::groups.errors.full', ['capacity' => 2]));
});

it('makes a new group from the free times of a teacher who takes groups, and seats the student in it', function () {
    $student = $this->student(timezone: 'Africa/Cairo');
    $page = Livewire::withQueryParams(['mode' => 'group', 'student' => $student->id, 'subject' => $this->quran])->test(FindTeacher::class)
        ->fillForm(['timezone' => 'Africa/Cairo', 'start_date' => '2026-10-06', 'per_week' => 2]);
    $page->call('useSuggestion', 0);
    expect($page->get('pickedTeacher'))->toBe($this->teacher->id)->and($page->get('picked'))->toHaveCount(2);
    $page->assertSee(__('scheduling::group_screens.find.create.bar'))
        ->assertSee(trans_choice('scheduling::group_screens.find.teacher_max', 4, ['n' => 4]));

    $page->call('startGroup')->assertActionMounted('createGroup')
        ->assertActionDataSet(['capacity' => 4])
        ->fillForm(['capacity' => 5])->callMountedAction()->assertHasActionErrors(['capacity'])
        ->fillForm(['capacity' => 3, 'name' => 'مساءً'])->callMountedAction()->assertHasNoActionErrors()
        ->assertNotified(__('scheduling::group_screens.find.create.done'))
        // The student is next: the join step on the new group.
        ->assertActionMounted('joinGroup');
    $group = LessonGroup::query()->where('name', 'مساءً')->sole();
    expect([$group->teacher_id, $group->capacity, $group->sessions_per_week, $group->status])->toBe([$this->teacher->id, 3, 2, LessonGroup::FORMING]);

    $page->callMountedAction()->assertHasNoActionErrors();
    expect($group->refresh()->status)->toBe(LessonGroup::RUNNING)
        ->and(DB::table('group_members')->where('group_id', $group->id)->where('student_id', $student->id)->exists())->toBeTrue();

    // A teacher who takes no groups is not offered here, and the reason says so.
    DB::table('teacher_profiles')->where('user_id', $this->teacher->id)->update(['accepts_groups' => false]);
    Livewire::withQueryParams(['mode' => 'group', 'subject' => $this->quran])->test(FindTeacher::class)
        ->assertSee(__('scheduling::group_screens.find.no_group_teachers.title'));
});

it('tells in private mode that a teacher has a group with a seat at the hours marked', function () {
    $group = $this->group(capacity: 3);
    $this->join($group, $this->student());

    $page = Livewire::withQueryParams(['subject' => $this->quran])->test(FindTeacher::class)
        ->fillForm(['timezone' => 'Africa/Cairo', 'start_date' => '2026-10-06', 'per_week' => 2])
        ->call('showTimes', 'mine')->call('toggleCell', 2, 18)->call('toggleCell', 6, 18)->call('search');
    expect($page->instance()->groupsOf($this->teacher->id))->toHaveCount(1);
    $page->assertSee(trans_choice('scheduling::group_screens.find.hint', 1, ['n' => 1, 'times' => GroupView::timesLine($page->instance()->groupsOf($this->teacher->id)[0]['times'])]))
        ->call('setMode', 'group')->assertSet('data.mode', 'group')
        ->assertSee(__('scheduling::group_screens.find.open.title'));
});
