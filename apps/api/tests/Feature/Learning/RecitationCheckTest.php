<?php

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Texts\QuranText;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\Assist\CheckRecitation;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Assist\RecitationScore;
use Modules\Learning\Filament\Teacher\HomeworkToMark;
use Modules\Learning\Filament\Teacher\WriteReport;
use Modules\Learning\Jobs\CheckRecitationJob;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Models\SubmissionCheck;
use Modules\Learning\Privacy\LearningData;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * The teacher's help with recited homework (docs/architecture/02, section 22):
 * homework says what to recite, a recording handed in is heard (speech to
 * text is never told the text) and lined up word by word in the background,
 * and the teacher alone sees it, as a help, never a mark. The fixture's text
 * is made up in the shape of Tanzil's files, never the Quran.
 */

beforeEach(function () {
    Storage::fake('local');
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    config(['queue.connections.assist' => ['driver' => 'sync']]);
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, Program::query()->value('id'));
    // The first lesson's text is surah 1, verses 2 to 3.
    DB::table('curriculum_nodes')->where('id', $this->lessons[0])->update(['text_source' => json_encode(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3])]);

    $session = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    $this->report = SessionReport::query()->firstOrFail();

    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test', 'platform.ai.speech.key' => 'another-key-for-the-test']);
    app(SaveAiSettings::class)->handle(['recitation_check' => true], $this->admin->id);
    $this->ears = recitationEars('كلمه اولي ثالثه جمله قصيره طويله');
    $this->clearTenant();
});

/** A speech to text that hears what it is told and notes what it was given. */
function recitationEars(string $text, ?AiFailed $fails = null): object
{
    $ears = new class($text, $fails) implements SpeechToText
    {
        public array $calls = [];

        public function __construct(public string $text, public ?AiFailed $fails) {}

        public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
        {
            $this->calls[] = ['feature' => $feature, 'language' => $language, 'exists' => is_file($clip->path), 'mime' => $clip->mime];
            if ($this->fails !== null) {
                throw $this->fails;
            }

            return new Transcript($this->text, array_map(fn ($w) => new HeardWord($w, 0, 0), explode(' ', $this->text)), 6.0, 'the-ears');
        }
    };
    app()->instance(SpeechToText::class, $ears);

    return $ears;
}

function recitedApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => $user->email, 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

/** The report handed in with homework to recite, as the teacher's form sends it. */
function reportWithRecitation(array $expected = ['surah' => 1, 'from' => 2, 'to' => 3], string $accepts = 'audio'): TestResponse
{
    return recitedApiAs(test()->teacher)->putJson('/api/v1/teacher/reports/'.test()->report->id, [
        'engagement' => 4, 'overall' => 4,
        'items' => [['node_id' => test()->lessons[0], 'outcome' => 'practiced']],
        'answers' => [],
        'homework' => ['title' => 'سمّع المقطع', 'accepts' => $accepts, 'due_days' => 3, 'expected' => $expected],
    ]);
}

function recording(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('recording.webm', str_repeat("\x1A\x45\xDF\xA3", 64))->mimeType('audio/webm');
}

it('lets the teacher say what to recite, suggesting the lesson\'s own passage', function () {
    $form = recitedApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$this->report->id}")->assertOk()->json('data');
    expect($form['assist']['recitation_check'])->toBeTrue()
        ->and($form['expected_suggestion'])->toMatchArray(['surah' => 1, 'from' => 2, 'to' => 3]);

    reportWithRecitation()->assertOk();
    $this->actAsTenant($this->academy);
    expect(Homework::query()->sole()->expected)->toEqual(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3]);

    // Sent again without it (homework not handed in yet), it asks nothing to recite; and back.
    app(SubmitSessionReport::class)->handle($this->report->fresh(), $this->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced']],
        'homework' => ['title' => 'سمّع المقطع', 'accepts' => 'audio', 'expected' => null],
    ]);
    expect(Homework::query()->sole()->expected)->toBeNull();
    $this->clearTenant();
    reportWithRecitation()->assertOk();

    recitedApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$this->report->id}")->assertOk()
        ->assertJsonPath('data.homework.expected.surah', 1)->assertJsonPath('data.homework.expected.to', 3)
        ->assertJsonPath('data.homework.expected.title', fn ($title) => is_string($title) && $title !== '');
    $surahs = recitedApiAs($this->teacher)->getJson('/api/v1/teacher/texts/quran')->assertOk()->json();
    expect($surahs['data'][0])->toMatchArray(['number' => 1, 'verses' => 3])->and($surahs['attribution'])->toContain('Tanzil');
});

it('refuses what cannot be checked: text homework, verses not there, or the helper off', function () {
    reportWithRecitation(accepts: 'text')->assertUnprocessable()->assertJsonValidationErrors('homework.expected');
    reportWithRecitation(['surah' => 1, 'from' => 2, 'to' => 9])->assertUnprocessable()->assertJsonValidationErrors('homework.expected');
    reportWithRecitation(['surah' => 1, 'from' => 3, 'to' => 2])->assertUnprocessable()->assertJsonValidationErrors('homework.expected.to');

    $this->actAsTenant($this->academy);
    app(SaveAiSettings::class)->handle(['recitation_check' => false], $this->admin->id);
    $this->clearTenant();
    reportWithRecitation()->assertUnprocessable()->assertJsonValidationErrors('homework.expected');
    recitedApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$this->report->id}")->assertJsonPath('data.assist.recitation_check', false);
});

it('checks a recording handed in, word by word, for the teacher alone and never as a mark', function () {
    reportWithRecitation()->assertOk();
    $this->actAsTenant($this->academy);
    $homework = Homework::query()->sole();
    $this->clearTenant();

    recitedApiAs($this->student)->post("/api/v1/learning/homework/{$homework->id}/submissions", ['file' => recording()], ['Accept' => 'application/json'])->assertCreated();

    // Heard once, told only the language, the recording read where it is kept and left there.
    expect($this->ears->calls)->toHaveCount(1)
        ->and($this->ears->calls[0])->toMatchArray(['feature' => AiFeature::RecitationCheck, 'language' => 'ara', 'exists' => true]);
    $this->actAsTenant($this->academy);
    $check = SubmissionCheck::query()->sole();
    $submission = $homework->submissions()->sole();
    expect($check->status)->toBe('ready')
        ->and($check->result['summary'])->toEqual(['words' => 8, 'ok' => 5, 'missing' => 2, 'wrong' => 1, 'extra' => 0])
        ->and(Storage::disk(SubmitHomework::DISK)->exists($submission->file_path))->toBeTrue()
        ->and($homework->fresh())->status->toBe('submitted')->grade->toBeNull();
    $this->clearTenant();

    $marked = recitedApiAs($this->teacher)->getJson('/api/v1/teacher/homework?status=submitted')->assertOk()->json('data.0');
    $verses = $marked['submissions'][0]['check']['verses'];
    expect($marked['expected']['surah'])->toBe(1)
        ->and($marked['submissions'][0]['check'])->toMatchArray(['status' => 'ready', 'heard' => 'كلمه اولي ثالثه جمله قصيره طويله'])
        ->and(array_column($verses[0]['words'], 'state'))->toBe(['ok', 'ok', 'missing', 'ok'])
        ->and(array_column($verses[1]['words'], 'state'))->toBe(['ok', 'missing', 'ok', 'wrong'])
        ->and($verses[1]['words'][3]['heard'])->toBe('طويله')
        ->and($verses[1]['verse'])->toBe(3);

    // The family sees their homework as before: no check, nothing heard, nothing expected.
    $family = recitedApiAs($this->student)->getJson('/api/v1/learning/homework')->assertOk()->json('data');
    expect(json_encode($family, JSON_UNESCAPED_UNICODE))->not->toContain('"check"')->not->toContain('طويله')->not->toContain('"expected"');

    // Marking is the teacher's as ever, and the check stays as it was.
    recitedApiAs($this->teacher)->postJson("/api/v1/teacher/homework/{$homework->id}/review", ['grade' => 4, 'feedback' => 'كويس'])->assertOk()
        ->assertJsonPath('data.submissions.0.check.status', 'ready');
});

it('keeps why a recording could not be checked, and tries again only when busy', function () {
    reportWithRecitation()->assertOk();
    $this->actAsTenant($this->academy);
    $homework = Homework::query()->sole();
    $this->clearTenant();

    recitationEars('', new AiFailed('unheard'));
    recitedApiAs($this->student)->post("/api/v1/learning/homework/{$homework->id}/submissions", ['file' => recording()], ['Accept' => 'application/json'])->assertCreated();
    recitedApiAs($this->teacher)->getJson('/api/v1/teacher/homework?status=submitted')->assertOk()
        ->assertJsonPath('data.0.submissions.0.check.status', 'failed')
        ->assertJsonPath('data.0.submissions.0.check.failure', __('shared::ai.failed.unheard'));

    // Busy: the queue tries again (the check waits), and after its last try the teacher sees it could not be checked.
    Queue::fake();
    recitationEars('', new AiFailed('busy'));
    recitedApiAs($this->student)->post("/api/v1/learning/homework/{$homework->id}/submissions", ['file' => recording()], ['Accept' => 'application/json'])->assertCreated();
    Queue::assertPushed(CheckRecitationJob::class, fn (CheckRecitationJob $job) => $job->connection === 'assist' && $job->queue === 'assist');
    $this->actAsTenant($this->academy);
    $waiting = SubmissionCheck::query()->where('status', 'pending')->sole();
    expect(fn () => app(CheckRecitation::class)->handle($waiting))->toThrow(AiFailed::class)
        ->and($waiting->fresh()->status)->toBe('pending');
    (new CheckRecitationJob($waiting->id))->failed(new AiFailed('busy'));
    expect($waiting->fresh())->status->toBe('failed')->reason->toBe('unavailable');
});

it('checks nothing while the helper is off, nor homework that asks for no recitation', function () {
    reportWithRecitation()->assertOk();
    $this->actAsTenant($this->academy);
    $homework = Homework::query()->sole();
    app(SaveAiSettings::class)->handle(['recitation_check' => false], $this->admin->id);
    $this->clearTenant();

    recitedApiAs($this->student)->post("/api/v1/learning/homework/{$homework->id}/submissions", ['file' => recording()], ['Accept' => 'application/json'])->assertCreated();
    $this->actAsTenant($this->academy);
    expect(SubmissionCheck::query()->count())->toBe(0)->and($this->ears->calls)->toBe([]);

    // The report again with the same passage is taken, the helper off or not (homework handed in stays as it is).
    app(SubmitSessionReport::class)->handle($this->report->fresh(), $this->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => $this->lessons[0], 'outcome' => 'practiced']],
        'homework' => ['title' => 'سمّع المقطع', 'accepts' => 'audio', 'expected' => ['surah' => 1, 'from' => 2, 'to' => 3]],
    ]);
    expect($homework->fresh()->expected)->not->toBeNull();
});

it('asks for the check on the teacher area\'s report, and colours each recording\'s words when marking', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    app()->setLocale('en');

    // The lesson's passage is ready for when the teacher turns it on; text homework does not offer it.
    $page = Livewire::test(WriteReport::class, ['report' => $this->report->id])
        ->assertSee('Check the recitation automatically')
        ->assertSet('data.homework.recite', false)
        ->assertSet('data.homework.expected', ['surah' => 1, 'from' => 2, 'to' => 3])
        ->set('data.engagement', 4)->set('data.overall', 4)
        ->set('data.items', [['node_id' => $this->lessons[0], 'outcome' => 'practiced', 'reason' => null, 'score' => null, 'note' => null]])
        ->set('data.homework.title', 'سمّع المقطع')
        ->set('data.homework.accepts', 'text')->assertDontSee('Check the recitation automatically')
        ->set('data.homework.accepts', 'audio')->set('data.homework.recite', true)
        ->assertSee("From the lesson's text:")
        ->set('data.homework.expected.to', 9)->call('submit')->assertHasErrors(['data.homework.expected.to']);
    expect(Homework::query()->count())->toBe(0);
    $page->set('data.homework.expected.to', 3)->call('submit')->assertHasNoErrors();
    $homework = Homework::query()->sole();
    expect($homework->expected)->toEqual(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3]);
    $this->clearTenant();

    recitedApiAs($this->student)->post("/api/v1/learning/homework/{$homework->id}/submissions", ['file' => recording()], ['Accept' => 'application/json'])->assertCreated();

    $this->actAsTenant($this->academy);
    $this->actingAs($this->teacher);
    Livewire::test(HomeworkToMark::class)->assertCanSeeTableRecords([$homework])
        ->mountTableAction('mark', $homework)
        ->assertMountedActionModalSee(['5 of 8 right · 2 missed · 1 wrong · 0 extra', 'طويله', 'A help while marking, not a mark'])
        ->assertMountedActionModalSeeHtml('class="w-wrong"');

    // Off for the academy, the passage the homework has stays on the form.
    app(SaveAiSettings::class)->handle(['recitation_check' => false], $this->admin->id);
    Livewire::test(WriteReport::class, ['report' => $this->report->id])
        ->assertSee('Check the recitation automatically')->assertSet('data.homework.recite', true);
});

it('puts a check in the learner\'s copy of their data, and erases it with the recording', function () {
    reportWithRecitation()->assertOk();
    $this->actAsTenant($this->academy);
    $homework = Homework::query()->sole();
    $this->clearTenant();
    recitedApiAs($this->student)->post("/api/v1/learning/homework/{$homework->id}/submissions", ['file' => recording()], ['Accept' => 'application/json'])->assertCreated();

    $this->actAsTenant($this->academy);
    $source = app(LearningData::class);
    $copy = $source->export($this->student->id)->data;
    expect($copy['homework'][0]['handed_in'][0]['recitation_check'])->toMatchArray(['heard' => 'كلمه اولي ثالثه جمله قصيره طويله'])
        ->and($copy['homework'][0]['handed_in'][0]['recitation_check']['summary']['missing'])->toBe(2);

    $source->erase($this->student->id);
    expect(SubmissionCheck::query()->count())->toBe(0);
});

it('measures the check on recordings whose mistakes a teacher marked, keeping nothing', function () {
    $dir = storage_path('framework/testing/recitation-trial');
    @mkdir($dir, 0775, true);
    file_put_contents($dir.'/one.webm', str_repeat("\x1A\x45\xDF\xA3", 64));
    file_put_contents($dir.'/cases.json', json_encode([
        // Heard: verse 2's third word left out; verse 3's second left out and its last said wrong.
        ['file' => 'one.webm', 'surah' => 1, 'from' => 2, 'to' => 3, 'mistakes' => [
            ['verse' => 2, 'word' => 3, 'kind' => 'missing'],
            ['verse' => 3, 'word' => 2, 'kind' => 'missing'],
            ['verse' => 3, 'word' => 1, 'kind' => 'wrong'],
        ]],
        ['file' => 'missing.webm', 'surah' => 1, 'from' => 2, 'to' => 3],
    ]));

    $this->artisan('learning:try-recitation', ['folder' => $dir])
        ->expectsOutputToContain('Caught 2 of 3 mistakes (67%), 100% with the same kind; 1 false alarm(s): 67% of what it marked was a mistake.')
        ->assertSuccessful();
    expect($this->ears->calls)->toHaveCount(1)->and($this->ears->calls[0]['language'])->toBe('ara')
        ->and(SubmissionCheck::query()->count())->toBe(0);

    array_map('unlink', glob($dir.'/*'));
    rmdir($dir);
    $this->artisan('learning:try-recitation', ['folder' => $dir])->assertExitCode(2);
});

it('scores a check against the mistakes marked by ear', function () {
    $verses = [
        ['verse' => 1, 'words' => [['state' => 'ok'], ['state' => 'wrong'], ['state' => 'missing']]],
        ['verse' => 2, 'words' => [['state' => 'missing'], ['state' => 'ok']]],
    ];
    expect(RecitationScore::of($verses, [
        ['verse' => 1, 'word' => 2, 'kind' => 'wrong'],
        ['verse' => 1, 'word' => 3, 'kind' => 'wrong'],
        ['verse' => 2, 'word' => 2, 'kind' => 'missing'],
    ]))->toBe(['caught' => 2, 'same_kind' => 1, 'missed' => ['2:2'], 'false_alarms' => ['2:1']]);
});
