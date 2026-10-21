<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Texts\QuranText;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\Hifz\MarkMemorized;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Filament\Family\FamilyHifz;
use Modules\Learning\Filament\JourneyStyleSettings;
use Modules\Learning\Filament\Teacher\StudentProgress;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\HifzRecitation;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;
use Modules\SharedKernel\Tenancy\TenantContext;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * The hifz map (docs/architecture/02, section 22): a learner's pages of the
 * mushaf, learning or memorized, each due for review when spaced repetition
 * says; fed by the teacher's word, the lessons' reports, homework's checks
 * and the daily review. The fixture's text is made up in Tanzil's shape:
 * two surahs on three pages, in two parts, never the Quran.
 */

beforeEach(function () {
    Storage::fake('local');
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    config(['queue.connections.assist' => ['driver' => 'sync']]);
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, Program::query()->value('id'));
    // The first lesson's text is surah 1, verses 2 to 3: pages 1 and 2.
    DB::table('curriculum_nodes')->where('id', $this->lessons[0])->update(['text_source' => json_encode(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3])]);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->student->forceFill(['timezone' => 'Asia/Riyadh'])->save();
    $this->clearTenant();
});

/** A speech to text that hears what it is told. */
function hifzEars(string $text): void
{
    app()->instance(SpeechToText::class, new class($text) implements SpeechToText
    {
        public function __construct(public string $text) {}

        public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
        {
            return new Transcript($this->text, array_map(fn ($w) => new HeardWord($w, 0, 0), explode(' ', $this->text)), 4.0, 'the-ears');
        }
    });
}

function hifzApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

function hifzRecording(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('page.webm', str_repeat("\x1A\x45\xDF\xA3", 64));
}

function hifzIn(TestCase $test, callable $read): mixed
{
    $tenants = app(TenantContext::class);
    $tenants->set($test->academy);
    $value = $read();
    $tenants->clear();

    return $value;
}

it('lets the teacher say what the student already knows, and take pages off', function () {
    $hifz = "/api/v1/teacher/students/{$this->booked->enrollmentId}/hifz";
    // A course whose curriculum reads no Quran, with no pages yet: the map is not its.
    $source = hifzIn($this, fn () => DB::table('curriculum_nodes')->where('id', $this->lessons[0])->value('text_source'));
    hifzIn($this, fn () => DB::table('curriculum_nodes')->update(['text_source' => null]));
    expect(hifzApiAs($this->teacher)->getJson($hifz)->assertOk()->json('data.follows'))->toBeFalse();
    hifzIn($this, fn () => DB::table('curriculum_nodes')->where('id', $this->lessons[0])->update(['text_source' => $source]));

    $map = hifzApiAs($this->teacher)->getJson($hifz)->assertOk()->json('data');
    expect($map)->toMatchArray(['pages_total' => 3, 'follows' => true])
        ->and(array_column($map['juzs'], 'juz'))->toBe([1, 2])
        ->and($map['juzs'][0]['pages'])->toBe([['page' => 1, 'strength' => 'new'], ['page' => 2, 'strength' => 'new']])
        ->and(collect($map['surahs'])->firstWhere('number', 2)['pages'])->toBe([3]);

    hifzApiAs($this->teacher)->postJson("{$hifz}/memorized", ['juz' => [1], 'strength' => 'good'])->assertOk()
        ->assertJsonPath('data.marked', 2)->assertJsonPath('data.counts.memorized', 2)->assertJsonPath('data.juzs.0.pages.0.strength', 'strong');
    hifzApiAs($this->teacher)->postJson("{$hifz}/memorized", ['surahs' => [2], 'strength' => 'weak'])->assertOk()->assertJsonPath('data.counts.memorized', 3);
    hifzApiAs($this->teacher)->postJson("{$hifz}/clear", ['pages' => [2]])->assertOk()->assertJsonPath('data.counts.memorized', 2);
    hifzApiAs($this->teacher)->postJson("{$hifz}/memorized", ['juz' => [9], 'strength' => 'good'])->assertUnprocessable()->assertJsonValidationErrors('juz');
    hifzApiAs($this->teacher)->postJson("{$hifz}/memorized", ['pages' => [1], 'strength' => 'perfect'])->assertUnprocessable()->assertJsonValidationErrors('strength');

    $recent = hifzApiAs($this->teacher)->getJson($hifz)->json('data.recent');
    expect(array_unique(array_column($recent, 'source')))->toBe(['teacher']);

    // Another teacher does not see this student's map.
    $other = hifzIn($this, fn () => User::factory()->withRole(Role::Teacher)->create());
    hifzApiAs($other)->getJson($hifz)->assertForbidden();
});

it('reviews a Quran lesson\'s pages from its report, once', function () {
    hifzIn($this, function () {
        $session = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
        $session->update(['status' => 'completed']);
        event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
        $report = SessionReport::query()->firstOrFail();
        $send = fn (string $outcome) => app(SubmitSessionReport::class)->handle($report->fresh(), $this->teacher->id, [
            'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => $outcome]],
        ]);
        $send('mastered');
        expect(HifzPage::query()->orderBy('page')->pluck('state', 'page')->all())->toBe([1 => 'memorized', 2 => 'memorized'])
            ->and(DB::table('hifz_reviews')->where('source', 'report')->count())->toBe(2);

        // The same report sent again (corrected) counts once.
        $send('needs_review');
        expect(DB::table('hifz_reviews')->count())->toBe(2)->and(HifzPage::query()->sum('lapses'))->toBe(0);
    });
});

it('reviews each page of a recited homework by the words said right on it', function () {
    hifzIn($this, function () {
        config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test', 'platform.ai.speech.key' => 'another-key-for-the-test']);
        app(SaveAiSettings::class)->handle(['recitation_check' => true], $this->admin->id);
        $homework = Homework::query()->create([
            'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id, 'enrollment_id' => $this->booked->enrollmentId,
            'title' => 'سمّع', 'accepts' => 'audio', 'due_at' => CarbonImmutable::now()->addDays(2), 'expected' => ['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3],
        ]);
        // Page 1 (verse 2): three of four words right; page 2 (verse 3): two of four.
        hifzEars('كلمه اولي ثالثه جمله قصيره طويله');
        app(SubmitHomework::class)->handle($homework, $this->student->id, null, hifzRecording());

        expect(DB::table('hifz_reviews')->where('source', 'check')->orderBy('page')->pluck('grade', 'page')->all())->toBe([1 => 2, 2 => 1])
            ->and(HifzPage::query()->orderBy('page')->pluck('state', 'page')->all())->toBe([1 => 'learning', 2 => 'learning']);
    });
});

it('shows the family the map and today\'s review on the learner\'s clock, and lets them say how a page went', function () {
    hifzApiAs($this->teacher)->postJson("/api/v1/teacher/students/{$this->booked->enrollmentId}/hifz/memorized", ['juz' => [1], 'strength' => 'weak'])->assertOk();

    // Three days on, the weak pages are due.
    $this->travel(3)->days();
    $mine = hifzApiAs($this->student)->getJson('/api/v1/learning/hifz')->assertOk()->json('data');
    expect($mine)->toMatchArray(['mode' => 'self', 'pages_total' => 3, 'latest_recitation' => null])
        ->and($mine['counts'])->toMatchArray(['memorized' => 2, 'due' => 2])
        ->and($mine['wird'])->toMatchArray(['daily' => 3, 'done_today' => 0])
        ->and(array_column($mine['wird']['pages'], 'page'))->toBe([1, 2])
        ->and($mine['wird']['pages'][0]['title'])->toContain('الأولى');
    // The journey says so too: two pages left today.
    hifzApiAs($this->student)->getJson('/api/v1/learning/journey/map')->assertOk()->assertJsonPath('data.hifz', ['due' => 2, 'done_today' => 0, 'daily' => 3]);
    $guardians = hifzApiAs($this->guardian)->getJson('/api/v1/learning/hifz?learner_id='.$this->student->id)->assertOk();
    expect($guardians->json('data.counts'))->toBe($mine['counts']);

    // The page's text, and the learner's own word on how it went: once a day.
    hifzApiAs($this->student)->getJson('/api/v1/learning/hifz/pages/2')->assertOk()
        ->assertJsonPath('data.verses.0.verse', 3)->assertJsonPath('data.attribution', fn ($a) => str_contains($a, 'Tanzil'));
    hifzApiAs($this->student)->postJson('/api/v1/learning/hifz/pages/1/grade', ['grade' => 3])->assertOk()->assertJsonPath('data.strength', 'strong');
    hifzApiAs($this->guardian)->postJson('/api/v1/learning/hifz/pages/1/grade?learner_id='.$this->student->id, ['grade' => 1])->assertOk();
    expect(hifzIn($this, fn () => DB::table('hifz_reviews')->where('source', 'self')->count()))->toBe(1);
    expect(hifzApiAs($this->student)->getJson('/api/v1/learning/hifz')->json('data.wird'))->toMatchArray(['done_today' => 1])
        ->and(array_column(hifzApiAs($this->student)->getJson('/api/v1/learning/hifz')->json('data.wird.pages'), 'page'))->toBe([2]);

    hifzApiAs($this->student)->getJson('/api/v1/learning/hifz/pages/9')->assertNotFound();
    hifzApiAs($this->student)->postJson('/api/v1/learning/hifz/pages/1/grade', ['grade' => 7])->assertUnprocessable();
    // Someone else's child: not theirs to see.
    $stranger = hifzIn($this, fn () => User::factory()->withRole(Role::Guardian)->create());
    hifzApiAs($stranger)->getJson('/api/v1/learning/hifz?learner_id='.$this->student->id)->assertNotFound();
});

it('hears a page recited for the daily review, grades it, and deletes the recording', function () {
    hifzApiAs($this->student)->post('/api/v1/learning/hifz/pages/3/recitations', ['audio' => hifzRecording()], ['Accept' => 'application/json'])
        ->assertForbidden()->assertJsonPath('reason', 'off');

    hifzIn($this, function () {
        config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test', 'platform.ai.speech.key' => 'another-key-for-the-test']);
        app(SaveAiSettings::class)->handle(['recitation_check' => true], $this->admin->id);
    });
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test', 'platform.ai.speech.key' => 'another-key-for-the-test']);
    hifzEars('حرف نص اخر للتجربه فقط');
    expect(hifzApiAs($this->student)->getJson('/api/v1/learning/hifz')->json('data.mode'))->toBe('check');

    $id = hifzApiAs($this->student)->post('/api/v1/learning/hifz/pages/3/recitations', ['audio' => hifzRecording()], ['Accept' => 'application/json'])
        ->assertStatus(202)->json('data.id');
    $done = hifzApiAs($this->student)->getJson("/api/v1/learning/hifz/recitations/{$id}")->assertOk()->json('data');
    expect($done)->toMatchArray(['status' => 'ready', 'grade' => 4, 'page' => 3])
        ->and(array_column($done['verses'][0]['words'], 'state'))->toBe(['ok']);

    hifzIn($this, function () use ($id) {
        $recitation = HifzRecitation::query()->findOrFail($id);
        expect($recitation->file_path)->toBeNull()
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and(HifzPage::query()->where('page', 3)->sole()->reviews)->toBe(1)
            ->and(DB::table('hifz_reviews')->where('source', 'wird')->sole()->grade)->toBe(4);
    });
    expect(hifzApiAs($this->student)->getJson('/api/v1/learning/hifz')->json('data.latest_recitation.id'))->toBe($id);
});

it('lets the teacher say what the student knows from their page in /teach', function () {
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('teach');
    $this->actingAs($this->teacher);

    Livewire::test(StudentProgress::class, ['enrollment' => $this->booked->enrollmentId])
        ->assertSee(__('learning::hifz.title'))->assertSee(__('learning::hifz.no_recent'))
        ->assertActionVisible('hifzMark')->assertActionHidden('hifzClear')
        ->callAction('hifzMark', data: ['juz' => [], 'strength' => 'good'])->assertHasActionErrors(['juz'])
        ->fillForm(['juz' => [9]])->callMountedAction()->assertHasActionErrors(['juz'])
        ->fillForm(['juz' => [1]])->callMountedAction()->assertHasNoActionErrors()
        ->assertNotified(trans_choice('learning::hifz.mark.done', 2, ['count' => 2]))
        ->assertSee(__('learning::hifz.sources.teacher'))->assertActionVisible('hifzClear')
        ->callAction('hifzClear', data: ['pages' => ['2']])->assertHasNoActionErrors()
        ->assertNotified(trans_choice('learning::hifz.clear.done', 1, ['count' => 1]));
    expect(HifzPage::query()->pluck('state', 'page')->all())->toBe([1 => 'memorized']);

    // Another subject's course (no Quran in its curriculum, no pages) has no map.
    DB::table('hifz_pages')->delete();
    DB::table('curriculum_nodes')->update(['text_source' => null]);
    Livewire::test(StudentProgress::class, ['enrollment' => $this->booked->enrollmentId])
        ->assertDontSee(__('learning::hifz.title'))->assertActionHidden('hifzMark');

    // Without the Quran's text there is no map to show or mark.
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/none')));
    Livewire::test(StudentProgress::class, ['enrollment' => $this->booked->enrollmentId])
        ->assertDontSee(__('learning::hifz.title'))->assertActionHidden('hifzMark');
});

it('opens the learner\'s map in /my: a page to recite from memory, its text on asking, and their word on it', function () {
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('family');
    $this->actingAs($this->student);
    expect(FamilyHifz::canAccess())->toBeTrue()->and(FamilyHifz::shouldRegisterNavigation())->toBeFalse();

    app(MarkMemorized::class)->handle($this->student->id, [1, 2], 'weak');
    $this->travel(3)->days();
    expect(FamilyHifz::shouldRegisterNavigation())->toBeTrue();

    Livewire::test(FamilyHifz::class)
        ->assertSee(__('learning::hifz.wird_done', ['done' => 0, 'daily' => 3]))->assertDontSee(__('learning::hifz.recite'))
        ->call('openPage', 1)->assertSet('open', 1)->assertSee(__('learning::hifz.reveal'))->assertDontSee('Tanzil')
        ->call('reveal')->assertSee('Tanzil')
        ->call('grade', 3)->assertSet('open', null)->assertNotified(__('learning::hifz.graded', ['grade' => __('learning::hifz.grades.3')]))
        ->assertSee(__('learning::hifz.wird_done', ['done' => 1, 'daily' => 3]))
        ->call('openPage', 9)->assertSet('open', null);
    expect(DB::table('hifz_reviews')->where('source', 'self')->sole()->page)->toBe(1);

    // Their guardian sees the child's map by the child's first name.
    $this->actingAs($this->guardian);
    Livewire::test(FamilyHifz::class)->assertSee(__('learning::hifz.title_of', ['name' => explode(' ', $this->student->name)[0]]))
        ->assertSee(__('learning::hifz.wird_done', ['done' => 1, 'daily' => 3]));
});

it('lets the academy choose how many pages the daily review holds', function () {
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    Livewire::test(JourneyStyleSettings::class)->assertSee(__('learning::hifz.settings.title'))->assertSet('hifzPages', 3)
        ->set('hifzPages', 0)->call('saveHifz')->assertHasErrors('hifzPages')
        ->set('hifzPages', 5)->call('saveHifz')->assertHasNoErrors()->assertNotified(__('learning::hifz.settings.saved'));
    expect(LearningSettings::current()->hifz_daily_pages)->toBe(5)
        ->and(DB::table('audit_logs')->where('action', 'learning.hifz_daily_saved')->sole()->data)->toContain('"pages": 5');

    $this->actingAs($this->teacher);
    Livewire::test(JourneyStyleSettings::class)->assertDontSee(__('learning::hifz.settings.title'))
        ->set('hifzPages', 9)->call('saveHifz')->assertForbidden();
    expect(LearningSettings::current()->hifz_daily_pages)->toBe(5);
});
