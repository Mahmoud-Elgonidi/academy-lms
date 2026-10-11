<?php

use App\Filament\Family\FamilyHome;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Games\Filament\Family\FamilyCreatures;
use Modules\Games\Filament\Family\FamilyGames;
use Modules\Games\Filament\Family\JourneyGuardian;
use Modules\Games\Filament\Family\JourneyPlay;
use Modules\Games\Filament\Pages\GameSettingsPage;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\AssignHomework;
use Modules\Learning\Actions\SaveJourneyStyle;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Filament\Family\FamilyHomework;
use Modules\Learning\Filament\Family\FamilyJourney;
use Modules\Learning\Filament\Family\FamilyProgress;
use Modules\Learning\Filament\Family\FamilyReports;
use Modules\Learning\Filament\Family\JourneyBoard;
use Modules\Learning\Filament\Family\JourneyWidget;
use Modules\Learning\Filament\Family\LearningHomeWidget;
use Modules\Learning\Filament\JourneyStyleSettings;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Models\LearningSettings;
use Modules\Messaging\Filament\Family\FamilyMessages;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Family\NextLessonsWidget;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\Support\StarterGames;

uses(EnrolledStudent::class, QuranProgram::class, StarterGames::class);

/*
 * The journey in the family area (/my; docs/architecture/02, section 18): a
 * student's home is their journey in their stage's look (a child's islands
 * map, middle school's levels, the calm view of older students), a guardian
 * looks at their child's in the classic look and cheers; the week's chest
 * opens from it, the egg hatches from it, and a round starts on a station
 * (the games' component in the journey's hook). Old links (the games page,
 * notifications for the games and the challenges) lead to it.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->installStarterGames();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    app(SaveNodeItems::class)->handle(Node::query()->findOrFail($this->lessons[0]), [
        ['kind' => 'choice', 'prompt' => 'أكمل: الحمد لله رب …', 'options' => ['العالمين', 'الدين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: مالك يوم …', 'options' => ['الدين', 'العالمين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: اهدنا الصراط …', 'options' => ['المستقيم', 'الضالين'], 'answer' => 0],
        ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
        ['kind' => 'pair', 'left' => 'اهدنا', 'right' => 'الصراط المستقيم'],
        ['kind' => 'pair', 'left' => 'إياك نعبد', 'right' => 'وإياك نستعين'],
    ], $this->admin->id);
    $this->tuesday = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function journeyLook(User $student, LearnerStage $stage): void
{
    app(PeopleApi::class)->saveStudentProfile($student->id, null, $stage);
    // A child's guardian has agreed to their account (GuardianConsentTest covers waiting for it).
    if (app(PeopleApi::class)->consentState($student->id)->waiting()) {
        app(GrantConsent::class)->handle($student->id, (string) DB::table('guardian_student')->where('student_id', $student->id)->value('guardian_id'), 'family');
    }
    app()->forgetInstance(FamilyView::class);
}

function onJourney(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('family');
    app()->forgetInstance(FamilyView::class);
}

/** Tuesday's lesson happens: the student comes in a minute late and stays. */
function lessonTaken(Session $lesson): void
{
    app(AttendanceApi::class)->participantJoined($lesson->id, test()->student->id, $lesson->starts_at->addMinute());
    $lesson->update(['status' => 'completed']);
    event(new SessionFinalized($lesson->academy_id, $lesson->id, 'completed', []));
}

it('makes a child\'s home their islands map: levels as islands, the lesson coming up, and the stations\' games', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 14:55', 'UTC'));   // five minutes before Tuesday's lesson
    journeyLook($this->student, LearnerStage::Kids);
    onJourney($this->student);

    Livewire::test(FamilyHome::class)->assertOk()
        ->assertSeeLivewire(JourneyWidget::class)
        ->assertDontSeeLivewire(LearningHomeWidget::class)
        ->assertDontSeeLivewire(NextLessonsWidget::class);

    $map = Livewire::test(JourneyBoard::class)->assertOk();
    expect($map->instance()->look())->toBe('kids');
    $map->assertSeeHtml(__('learning::map.world', ['name' => '<bdi>'.e(strtok($this->student->name, ' ')).'</bdi>']))
        ->assertSee('Level 1')->assertSee('Level 2')
        ->assertSeeHtml('data-island=')
        ->assertSee(__('learning::map.island.here'))
        ->assertSee(__('learning::map.mission.join'))                        // the classroom is open
        ->assertSeeHtml("play({ game: 'bird")                                  // the current lesson's games
        ->assertSee(__('learning::map.island.ahead', ['title' => 'Level 1']), false);

    // The tab bar: the journey, the lessons, the teacher, and the rest under "more".
    expect([FamilyHome::getNavigationLabel(), FamilyLessons::getNavigationLabel(), FamilyMessages::getNavigationLabel()])
        ->toBe([__('family.home.kids'), __('scheduling::family.nav.kids'), __('messaging::screens.nav.kids')])
        ->and(FamilyJourney::shouldRegisterNavigation())->toBeFalse()
        ->and(FamilyProgress::shouldRegisterNavigation())->toBeFalse();

    $this->clearTenant();
    $page = $this->get('http://noor.lms.test/my')->assertOk()
        ->assertSee('class="lms-tabs"', false)
        ->assertSee(__('people::family.more'))
        ->assertSee('window.LmsIslands', false)
        ->assertDontSee('/my/games"', false);
    expect(substr_count($page->getContent(), 'class="lms-tab"'))->toBe(4);   // three tabs and "more"
});

it('starts a round on a station from the journey, plays it and counts it, and the race on it', function () {
    journeyLook($this->student, LearnerStage::Kids);
    onJourney($this->student);

    $station = $this->lessons[0];
    $game = Livewire::test(JourneyBoard::class)->instance()->stations[$station]['games'][0]['key'];
    $play = Livewire::test(JourneyPlay::class)->dispatch('journey-play', game: $game, station: $station);
    $round = $play->get('round');
    expect($round['station'])->toBe($station)
        ->and($round['src'])->toContain('/games/')
        ->and($round['stage'])->toBe('kids')
        ->and($round['items'])->not->toBeEmpty();
    $play->assertSeeHtml('data-round-player');

    $answers = array_map(fn ($i) => ['item' => $i['id'], 'correct' => true], $round['items']);
    $play->call('finish', $answers)->assertSet('round', null)->assertDispatched('journey-changed');
    expect($play->get('result'))->toMatchArray(['stars' => 3, 'correct' => count($round['items']), 'station' => $station])
        ->and(GamePlay::query()->sole()->island_key)->toBe('birds');
    $play->assertSee(__('games::games.result.3'));

    // The station keeps its race; a lesson still ahead cannot be played.
    expect(Livewire::test(JourneyBoard::class)->instance()->stations[$station]['race'])->toMatchArray(['rounds' => 1, 'best_stars' => 3]);
    Livewire::test(JourneyPlay::class)->dispatch('journey-play', game: $game, station: $this->lessons[6])->assertSet('round', null);
    expect(GamePlay::query()->count())->toBe(1);
});

it('opens a homework\'s round from the homework page and the old games link, and never shows a practice homework an empty form', function () {
    journeyLook($this->student, LearnerStage::Kids);
    $homework = app(AssignHomework::class)->handle([
        'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id, 'enrollment_id' => $this->booked->enrollmentId,
        'title' => 'جولة على الفاتحة', 'accepts' => 'practice', 'due_at' => CarbonImmutable::now()->addDays(2), 'practice_nodes' => [$this->lessons[0]],
    ]);
    onJourney($this->student);

    Livewire::test(FamilyHomework::class)->assertOk()
        ->assertSee('جولة على الفاتحة')
        ->assertSee(__('learning::homework.practice.play'))
        ->assertSee(FamilyJourney::getUrl(['homework' => $homework->id]), false)
        ->assertSee(__('learning::homework.practice.by_playing'))
        ->assertDontSee(__('learning::family.homework.hand_in'));

    Livewire::withQueryParams(['homework' => $homework->id])->test(FamilyGames::class)
        ->assertRedirect(FamilyJourney::getUrl(['homework' => $homework->id]));
    Livewire::withQueryParams(['homework' => $homework->id])->test(FamilyJourney::class)
        ->assertRedirect(Filament::getUrl().'?homework='.$homework->id);

    $map = Livewire::withQueryParams(['homework' => $homework->id])->test(JourneyBoard::class);
    $start = $map->get('start');
    expect($start['homework'])->toBe($homework->id)->and($start['game'])->not->toBeNull();
    $play = Livewire::test(JourneyPlay::class, ['start' => $start]);
    expect($play->get('round')['homework'])->toBe($homework->id)->and($play->get('round')['focus'])->toBe('جولة على الفاتحة');

    $play->call('finish', array_map(fn ($i) => ['item' => $i['id'], 'correct' => true], $play->get('round')['items']));
    expect($play->get('result')['homework'])->toBe('جولة على الفاتحة')
        ->and($homework->fresh()->status)->toBe('submitted');
    Livewire::test(FamilyHomework::class)->assertSee(__('learning::homework.practice.result', $homework->fresh()->practice_result));
});

it('opens the week\'s chest from the journey once, with a child\'s sticker', function () {
    lessonTaken($this->tuesday);
    $homework = app(AssignHomework::class)->handle([
        'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id, 'enrollment_id' => $this->booked->enrollmentId,
        'title' => 'سمّع سورة الفلق', 'due_at' => CarbonImmutable::parse('2026-10-08 18:00', 'Africa/Cairo'),
    ]);
    app(SubmitHomework::class)->handle($homework, $this->student->id, 'سمّعتها', null);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    journeyLook($this->student, LearnerStage::Kids);
    onJourney($this->student);

    $map = Livewire::test(JourneyBoard::class)
        ->assertSee(__('learning::map.chest.ready'))
        ->assertSee(__('learning::map.chest.open'))
        ->assertSee(__('learning::map.lesson.on_time'));
    $map->call('openChest', '2026-10-03')->assertNotified(__('learning::map.chest.got', ['stars' => 10]))
        ->assertSee(__('learning::map.chest.done'))
        ->assertSee(__('learning::challenges.stickers.lion'), false);
    $map->call('openChest', '2026-10-03')->assertNotified(__('learning::map.chest.opened'));
    expect(ChallengeClaim::query()->where('student_id', $this->student->id)->where('challenge', 'chest')->count())->toBe(1);

    // A guardian sees how far the week is, and never opens it.
    onJourney($this->guardian);
    Livewire::test(JourneyBoard::class)->call('openChest', '2026-10-03')->assertForbidden();
});

it('hatches the egg the journey\'s stars filled, names the creature and opens the album', function () {
    lessonTaken($this->tuesday);                                   // 4 stars
    $homework = app(AssignHomework::class)->handle([
        'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id, 'enrollment_id' => $this->booked->enrollmentId,
        'title' => 'سمّع سورة الفلق', 'due_at' => CarbonImmutable::parse('2026-10-08 18:00', 'Africa/Cairo'),
    ]);
    app(SubmitHomework::class)->handle($homework, $this->student->id, 'سمّعتها', null);   // 2 stars
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    journeyLook($this->student, LearnerStage::Kids);
    onJourney($this->student);
    Livewire::test(JourneyBoard::class)->call('openChest', '2026-10-03');   // 10 more: 16, an egg

    Livewire::test(JourneyBoard::class)->assertSee(__('learning::map.egg.ready'))->assertSeeHtml('hatch(');
    $play = Livewire::test(JourneyPlay::class)->dispatch('journey-hatch', island: 'birds')->assertDispatched('journey-changed');
    $creature = $play->get('hatched');
    expect($creature['key'])->toStartWith('birds.')->and($creature['names'])->toHaveCount(3);
    $play->assertSee(__('games::games.hatch.ready'));
    $play->call('nameCreature', $creature['key'], $creature['names'][1])->assertRedirect(FamilyCreatures::getUrl(['new' => $creature['key']]));
    expect(CreatureFind::query()->where('student_id', $this->student->id)->where('source', 'stars')->count())->toBe(1);

    // No second egg yet; a guardian never hatches.
    Livewire::test(JourneyPlay::class)->dispatch('journey-hatch', island: 'birds')->assertSet('hatched', null)->assertNotified(__('games::games.hatch.not_ready'));
    onJourney($this->guardian);
    Livewire::test(JourneyPlay::class)->call('hatch', 'birds')->assertForbidden();
});

it('gives middle school levels with stars to the next rank and the race, and a teenager the calm view', function () {
    lessonTaken($this->tuesday);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    journeyLook($this->student, LearnerStage::Middle);
    onJourney($this->student);

    $middle = Livewire::test(JourneyBoard::class)->assertOk();
    expect($middle->instance()->look())->toBe('middle');
    $middle->assertSee(__('learning::map.title.self'))
        ->assertSee(__('learning::map.to_next', ['n' => 26, 'rank' => __('learning::journey.ranks.explorer')]))   // 4 of 30
        ->assertSee(trans_choice('learning::map.streak', 1, ['count' => 1]))
        ->assertSee(__('learning::map.station.race'))
        ->assertSee('Level 1');
    expect([FamilyHome::getNavigationLabel(), FamilyLessons::getNavigationLabel(), FamilyMessages::getNavigationLabel()])
        ->toBe([__('family.home.middle'), __('scheduling::family.nav.middle'), __('messaging::screens.nav.middle')]);

    journeyLook($this->student, LearnerStage::Teen);
    onJourney($this->student);
    $teen = Livewire::test(JourneyBoard::class)->assertOk();
    expect($teen->instance()->look())->toBe('calm');
    $teen->assertSee(__('learning::map.calm.level', ['n' => 1, 'of' => 2]))
        ->assertSee(__('learning::map.calm.next_lesson'))
        ->assertSee(__('learning::map.calm.levels'))
        ->assertSee(__('learning::map.calm.roadmap'))
        ->assertDontSeeHtml('class="gw-isl"');
    // Secondary school's tab bar: the journey, the lessons, the homework, the reports.
    expect(collect([FamilyHomework::class, FamilyReports::class, FamilyMessages::class])->map(fn ($p) => $p::getNavigationSort())->all())->toBe([12, 13, 15]);

    // An academy that chose the plain style: levels and missions, no stars.
    $this->actingAs($this->admin);
    app(SaveJourneyStyle::class)->handle('plain');
    onJourney($this->student);
    journeyLook($this->student, LearnerStage::Kids);
    $plain = Livewire::test(JourneyBoard::class);
    expect($plain->instance()->look())->toBe('calm');
    $plain->assertDontSee(__('learning::map.chest.title'))->assertDontSeeHtml('data-count=');
});

it('shows a guardian their child\'s journey in the classic look, to cheer and play together, with the games\' limits', function () {
    lessonTaken($this->tuesday);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 09:00', 'UTC'));
    journeyLook($this->student, LearnerStage::Kids);
    onJourney($this->guardian);

    Livewire::test(FamilyHome::class)->assertOk()
        ->assertSeeLivewire(LearningHomeWidget::class)
        ->assertSeeLivewire(JourneyWidget::class)
        ->assertDontSeeLivewire(JourneyBoard::class);     // the journey has its own page; the home has its card
    Livewire::test(JourneyWidget::class)
        ->assertSeeHtml(__('learning::map.home.title', ['name' => '<bdi>'.e(strtok($this->student->name, ' ')).'</bdi>']))
        ->assertSee(FamilyJourney::getUrl(), false);
    expect(FamilyJourney::shouldRegisterNavigation())->toBeTrue()
        ->and(FamilyJourney::getNavigationLabel())->toBe(__('learning::map.nav'))
        ->and(FamilyProgress::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(FamilyJourney::class)->assertOk()->assertSeeLivewire(JourneyBoard::class);
    $map = Livewire::test(JourneyBoard::class)->assertOk();
    expect($map->instance()->look())->toBe('guardian');
    $map->assertSeeHtml(__('learning::map.title.guardian', ['name' => '<bdi>'.e(strtok($this->student->name, ' ')).'</bdi>']))
        ->assertSee(__('learning::map.guardian.punctual'))
        ->assertSee(__('learning::map.lesson.on_time'))
        ->assertSee(__('learning::map.guardian.together'))
        ->assertDontSee(__('learning::map.chest.open'))
        ->assertSeeLivewire(JourneyGuardian::class);
    $map->call('cheer', 'heart')->assertNotified(__('learning::map.cheers.sent', ['name' => strtok($this->student->name, ' ')]));
    expect(Cheer::query()->where('student_id', $this->student->id)->count())->toBe(1);

    $limits = Livewire::test(JourneyGuardian::class)->assertOk()->assertSee(__('games::games.guardian.time'));
    $limits->call('setMinutes', 20)->call('setNight', true)->assertDispatched('journey-changed');
    expect((array) DB::table('game_limits')->where('student_id', $this->student->id)->first(['daily_minutes', 'night_lock']))->toBe(['daily_minutes' => 20, 'night_lock' => true]);

    // Playing together on the child's screen: the round is the child's, played by the guardian.
    $station = $this->lessons[0];
    $game = $map->instance()->stations[$station]['games'][0]['key'];
    Livewire::test(JourneyPlay::class)->dispatch('journey-play', game: $game, station: $station)->assertSet('round.station', $station);
    expect(GamePlay::query()->sole()->played_by)->toBe($this->guardian->id);

    // The child sees the cheer once.
    onJourney($this->student);
    Livewire::test(JourneyBoard::class)->assertSee(__('learning::challenges.cheers.heart'))->call('cheersSeen');
    expect(Cheer::query()->whereNull('seen_at')->count())->toBe(0);
    Livewire::test(JourneyBoard::class)->call('cheer', 'heart')->assertForbidden();
    Livewire::test(JourneyGuardian::class)->call('setMinutes', 120)->assertForbidden();
});

it('opens the journey from notifications about it, the games and the challenges', function () {
    $links = app(NotificationLinks::class);
    foreach (['journey', 'games', 'challenges'] as $screen) {
        expect($links->resolve(['screen' => $screen], 'family'))->toBe(FamilyJourney::getUrl(panel: 'family'))
            ->and($links->resolve(['screen' => $screen], 'academy'))->toBeNull();
    }
});

it('lets the academy choose the journey\'s style on the games\' settings page', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    Livewire::test(GameSettingsPage::class)->assertOk()
        ->assertSeeLivewire(JourneyStyleSettings::class)
        ->assertSee(__('learning::map.settings.title'))
        ->assertSee(__('learning::map.styles.calm'));
    Livewire::test(JourneyStyleSettings::class)->assertSet('style', 'playful')
        ->set('style', 'calm')->call('save')->assertNotified(__('learning::map.settings.saved'));
    expect(LearningSettings::current()->journey_style)->toBe('calm');
    Livewire::test(JourneyStyleSettings::class)->set('style', 'loud')->call('save')->assertHasErrors('journey_style');

    $this->actingAs($this->teacher);
    Livewire::test(JourneyStyleSettings::class)->set('style', 'plain')->call('save')->assertForbidden();
    expect(LearningSettings::current()->journey_style)->toBe('calm');
});
