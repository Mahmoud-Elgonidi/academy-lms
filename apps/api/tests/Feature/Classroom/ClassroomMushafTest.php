<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Live\LessonMushafChanged;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Texts\QuranText;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Models\RecitationMistake;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Realtime\LiveSignals;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * The shared mushaf on the class page (docs/architecture/02, section 22; 03,
 * section 6): beside the room, or beside Zoom or Meet in a tab of their own,
 * the teacher turns the pages and marks words and the student follows, on
 * the lesson's own channel, which the page signs for whoever has a seat (a
 * student by the signed link has no session to sign in with). The fixture's
 * text is made up in Tanzil's shape, never the Quran.
 */

beforeEach(function () {
    Mail::fake();
    Http::fake(['*/twirp/livekit.RoomService/CreateRoom' => Http::response(['name' => 'r'])]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, Program::query()->value('id'));
    DB::table('curriculum_nodes')->where('id', $this->lessons[0])->update(['text_source' => json_encode(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3])]);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->stranger = User::factory()->withRole(Role::Student)->create();
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    CarbonImmutable::setTestNow(CarbonImmutable::parse($this->lesson->starts_at)->subMinutes(5));
    $this->link = app(NotificationLinks::class)->personal(['screen' => 'session', 'id' => $this->lesson->id], $this->student->id);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The class page as someone signed in on the academy's host. */
function classPageAs(?User $user, string $path = ''): string
{
    app('auth')->forgetGuards();
    test()->flushSession();
    if ($user) {
        test()->actingAs($user);
    }

    return 'http://noor.lms.test/class/'.test()->lesson->id.$path;
}

/** The student's own signed link from their message: the page remembers who came by it. */
function byStudentLink(): void
{
    app('auth')->forgetGuards();
    test()->flushSession();
    test()->get(test()->link)->assertOk();
}

function lessonChannelAuth(array $body): TestResponse
{
    return test()->postJson('http://noor.lms.test/class/'.test()->lesson->id.'/live/auth', $body);
}

function reverbOn(): void
{
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
        'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test',
        'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        'client' => ['host' => null, 'port' => 443, 'scheme' => 'https'],
    ]]);
    foreach (Broadcast::driver('null')->getChannels() as $pattern => $callback) {
        Broadcast::driver('reverb')->channel($pattern, $callback);
    }
}

it('shows the mushaf on a Quran lesson\'s class page to its teacher and its student, and to nobody watching', function () {
    $page = test()->get(classPageAs($this->teacher))->assertOk()
        ->assertSee(__('classroom::classroom.mushaf.title'))->assertSee('fonts/quran/AmiriQuran-Regular.ttf')->assertSee('<main class="with-plan with-mushaf">', false);
    expect($page->viewData('mushaf')['first'])->toMatchArray(['role' => 'teacher', 'page' => 1, 'can_mark' => true])
        ->and($page->viewData('mushaf')['channel'])->toBe("lesson.{$this->lesson->id}")
        ->and($page->viewData('mushaf')['echo'])->toBeNull();       // no Reverb here: the room's own signal alone

    byStudentLink();
    $student = $this->get("http://noor.lms.test/class/{$this->lesson->id}")->assertOk()->assertSee(__('classroom::classroom.mushaf.hint_follow'));
    expect($student->viewData('mushaf')['first'])->toMatchArray(['role' => 'student', 'can_mark' => false]);

    // The supervisor watching unseen, and a lesson that reads no Quran: the page as it was.
    $watching = test()->get(classPageAs($this->admin))->assertOk()->assertDontSee('id="mushaf"', false);
    expect($watching->viewData('mushaf'))->toBeNull();
    $this->actAsTenant($this->academy);
    DB::table('curriculum_nodes')->update(['text_source' => null]);
    $this->clearTenant();
    expect(test()->get(classPageAs($this->teacher))->assertOk()->assertDontSee('id="mushaf"', false)->viewData('mushaf'))->toBeNull();
});

it('lets the teacher turn and mark from the class page, and the student only follow', function () {
    $this->postJson(classPageAs($this->teacher, '/mushaf'), ['change' => 'turn', 'page' => 2])->assertOk()
        ->assertJsonPath('data.page', 2)->assertJsonPath('data.teacher_page', 2);
    $this->postJson(classPageAs($this->teacher, '/mushaf'), ['change' => 'mark', 'surah' => 1, 'verse' => 3, 'word' => 2, 'kind' => 'hesitation'])->assertOk()
        ->assertJsonPath('data.marks', [['surah' => 1, 'verse' => 3, 'word' => 2, 'kind' => 'hesitation']]);

    byStudentLink();
    $this->getJson("http://noor.lms.test/class/{$this->lesson->id}/mushaf")->assertOk()
        ->assertJsonPath('data.page', 2)->assertJsonPath('data.marks.0.kind', 'hesitation');
    $this->getJson("http://noor.lms.test/class/{$this->lesson->id}/mushaf?page=3")->assertOk()->assertJsonPath('data.page', 3)->assertJsonPath('data.teacher_page', 2);
    $this->postJson("http://noor.lms.test/class/{$this->lesson->id}/mushaf", ['change' => 'turn', 'page' => 3])->assertForbidden();
    $this->postJson("http://noor.lms.test/class/{$this->lesson->id}/mushaf", ['change' => 'mark', 'surah' => 1, 'verse' => 3, 'word' => 1, 'kind' => 'wrong'])->assertForbidden();

    $this->postJson(classPageAs($this->teacher, '/mushaf'), ['change' => 'unmark', 'surah' => 1, 'verse' => 3, 'word' => 2, 'page' => 2])->assertOk()->assertJsonPath('data.marks', []);
    $this->postJson(classPageAs($this->teacher, '/mushaf'), ['change' => 'mark', 'surah' => 1, 'verse' => 3, 'word' => 9, 'kind' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('word');
    $this->postJson(classPageAs($this->teacher, '/mushaf'), ['change' => 'erase'])->assertUnprocessable()->assertJsonValidationErrors('change');
    $this->getJson(classPageAs($this->teacher, '/mushaf?page=605'))->assertUnprocessable();

    // Nobody signed in or by a link; someone with no seat; a lesson that is not there.
    $this->getJson(classPageAs(null, '/mushaf'))->assertForbidden();
    $this->getJson(classPageAs($this->stranger, '/mushaf'))->assertNotFound();
    $this->postJson(classPageAs($this->other, '/mushaf'), ['change' => 'turn', 'page' => 1])->assertNotFound();
    classPageAs($this->teacher);
    $this->getJson('http://noor.lms.test/class/not-a-lesson/mushaf')->assertNotFound();

    $this->actAsTenant($this->academy);
    expect(RecitationMistake::query()->count())->toBe(0)
        ->and(DB::table('lesson_mushaf')->where('session_id', $this->lesson->id)->value('page'))->toBe(2);
});

it('signs the lesson\'s own channel for whoever has a seat, by the signed link too, and nothing else', function () {
    reverbOn();
    $channel = "private-lesson.{$this->lesson->id}";
    $signed = 'test-key:'.hash_hmac('sha256', "1234.5678:{$channel}", 'test-secret');

    byStudentLink();
    lessonChannelAuth(['socket_id' => '1234.5678', 'channel_name' => $channel])->assertOk()->assertExactJson(['auth' => $signed]);
    lessonChannelAuth(['socket_id' => '1234.5678', 'channel_name' => "private-user.{$this->student->id}"])->assertForbidden();
    lessonChannelAuth(['socket_id' => '1234.5678', 'channel_name' => 'private-lesson.'.Str::uuid()])->assertForbidden();
    lessonChannelAuth(['socket_id' => 'x', 'channel_name' => $channel])->assertUnprocessable();

    $this->postJson(classPageAs($this->teacher, '/live/auth'), ['socket_id' => '1.2', 'channel_name' => $channel])->assertOk();
    $this->postJson(classPageAs($this->stranger, '/live/auth'), ['socket_id' => '1.2', 'channel_name' => $channel])->assertForbidden();
    $this->postJson(classPageAs(null, '/live/auth'), ['socket_id' => '1.2', 'channel_name' => $channel])->assertForbidden();

    // Signed in on the academy's host, the panels' own door says the same.
    classPageAs($this->guardian);
    $this->post('http://noor.lms.test/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => $channel])->assertOk();
    classPageAs($this->stranger);
    $this->post('http://noor.lms.test/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => $channel])->assertForbidden();

    // The page reaches Reverb on its own host, and with no Reverb there is nothing to sign.
    expect(test()->get(classPageAs($this->teacher))->viewData('mushaf')['echo'])->toMatchArray(['key' => 'test-key', 'wsHost' => 'noor.lms.test']);
    config(['broadcasting.default' => 'null']);
    $this->postJson(classPageAs($this->teacher, '/live/auth'), ['socket_id' => '1.2', 'channel_name' => $channel])->assertNotFound();
});

it('turns a raised mushaf signal into the lesson\'s own event, with nothing in it', function () {
    $event = app(LiveSignals::class)->event("lesson-mushaf:{$this->lesson->id}");
    expect($event)->toBeInstanceOf(LessonMushafChanged::class)
        ->and($event->broadcastOn()[0]->name)->toBe("private-lesson.{$this->lesson->id}")
        ->and($event->broadcastAs())->toBe('mushaf.changed')
        ->and($event->broadcastWith())->toBe([]);
});

it('opens Zoom or Meet in a tab of its own beside a Quran lesson\'s mushaf, so the page stays', function () {
    $this->actAsTenant($this->academy);
    app(SaveClassroomSettings::class)->handle('external');
    app(SaveMeetingLink::class)->handle($this->teacher->id, 'https://meet.google.com/abc-defg-hij');
    $this->clearTenant();

    $go = "http://noor.lms.test/class/{$this->lesson->id}/go";
    test()->get(classPageAs($this->teacher))->assertOk()
        ->assertSee('id="outside"', false)->assertSee('href="'.$go.'" target="_blank" rel="noopener"', false)->assertSee(__('classroom::classroom.external.opened'));
    $this->postJson(classPageAs($this->teacher, '/ticket'))->assertOk()->assertJsonPath('data.provider', 'external');

    // Without a mushaf the page goes to the meeting as before.
    $this->actAsTenant($this->academy);
    DB::table('curriculum_nodes')->update(['text_source' => null]);
    $this->clearTenant();
    test()->get(classPageAs($this->teacher))->assertOk()->assertDontSee('id="outside"', false);
});
