<?php

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Curriculum\Models\Program;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\Assist\DraftReportFromVoice;
use Modules\Learning\Actions\SaveReportTemplate;
use Modules\Learning\Filament\Teacher\WriteReport;
use Modules\Learning\Models\SessionReport;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * A lesson report's draft from the teacher's voice note (docs/architecture/02,
 * section 22): heard and deleted at once, the student named {child} before the
 * words go to the language model, and only the fields the teacher's open form
 * leaves empty filled; nothing is saved or handed in.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    $this->enrollmentId = $this->booked->enrollmentId;
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    DB::table('users')->where('id', $this->student->id)->update(['name' => 'يوسف أحمد']);
    app(SaveReportTemplate::class)->handle($this->quran, [
        ['label_ar' => 'الصفحات اللي اتحفظت', 'label_en' => 'Pages memorised', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 20],
        ['label_ar' => 'ركّز؟', 'label_en' => 'Focused?', 'type' => 'yes_no'],
    ], $this->admin->id);

    $session = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    $this->report = SessionReport::query()->firstOrFail();

    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test', 'platform.ai.speech.key' => 'another-key-for-the-test']);
    app(SaveAiSettings::class)->handle(['report_draft' => true], $this->admin->id);
    RateLimiter::clear('ai:report-draft:'.$this->academy->id.':'.$this->teacher->id);
    $this->heard = fakeEars('يوسف قرأ الدرس الأول كويس جدًا ومحتاج يراجع الدرس التاني، وحفظ صفحتين، واديته واجب يسمّع السورة');
    $this->model = fakeWriter([
        'lessons' => [['key' => 'L1', 'outcome' => 'mastered', 'score' => 5], ['key' => 'L2', 'outcome' => 'needs_review', 'note' => 'يراجعه']],
        'engagement' => 5,
        'overall' => 4,
        'notes_for_student' => 'برافو يا {child}! قريت كويس جدًا.',
        'notes_internal' => 'لا شيء',
        'answers' => [['key' => 'f1', 'value' => '2'], ['key' => 'f2', 'value' => 'true']],
        'homework' => ['title' => 'سمّع السورة', 'accepts' => 'audio', 'due_days' => 40],
        'unsure' => ['overall', 'L2', 'f1'],
    ]);
    $this->clearTenant();
});

/** A speech to text that hears what it is told, keeping the clip it was sent. */
function fakeEars(string $text): object
{
    $ears = new class($text) implements SpeechToText
    {
        public ?AudioClip $clip = null;

        public bool $existed = false;

        public function __construct(public string $text) {}

        public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
        {
            $this->clip = $clip;
            $this->existed = is_file($clip->path);

            return new Transcript($this->text, [], 14.0, 'the-ears');
        }
    };
    app()->instance(SpeechToText::class, $ears);

    return $ears;
}

/** A language model that answers what it is given and keeps the prompt it was sent. */
function fakeWriter(array $answer): object
{
    $model = new class($answer) implements LanguageModel
    {
        public ?AiPrompt $prompt = null;

        public function __construct(private array $answer) {}

        public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
        {
            $this->prompt = $prompt;

            return new AiAnswer($this->answer, 'the-writer', ['input' => 10, 'output' => 10]);
        }
    };
    app()->instance(LanguageModel::class, $model);

    return $model;
}

function draftApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => $user->email, 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

function voiceNote(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('note.webm', str_repeat("\x1A\x45\xDF\xA3", 64))->mimeType('audio/webm');
}

it('drafts the open form from the teacher\'s note, filling only what is empty, and saves nothing', function () {
    $form = draftApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$this->report->id}")->assertOk()
        ->assertJsonPath('data.assist.voice_draft', true)->json('data');
    // The teacher already wrote a word for the family and set the second lesson themselves.
    $current = [
        'notes_for_student' => 'كلمتي أنا',
        'items' => [
            ['node_id' => $form['items'][0]['node_id'], 'outcome' => $form['items'][0]['outcome']],     // still the plan's guess
            ['node_id' => $this->lessons[1], 'outcome' => 'practiced'],                                  // the teacher's own choice
        ],
    ];

    $draft = draftApiAs($this->teacher)->post("/api/v1/teacher/reports/{$this->report->id}/draft", [
        'audio' => voiceNote(), 'current' => json_encode($current),
    ], ['Accept' => 'application/json'])->assertOk()->json('data');

    expect($draft['heard'])->toStartWith('يوسف قرأ')
        ->and($draft['report']['notes_for_student'])->toBe('كلمتي أنا')               // kept
        ->and($draft['report']['engagement'])->toBe(5)
        ->and($draft['report']['notes_internal'])->toBe('لا شيء')
        ->and($draft['report']['items'][0])->toMatchArray(['node_id' => $this->lessons[0], 'outcome' => 'mastered', 'score' => 5])
        ->and($draft['report']['items'][1])->toMatchArray(['node_id' => $this->lessons[1], 'outcome' => 'practiced', 'note' => 'يراجعه'])
        ->and($draft['report']['answers'])->toBe(['f1' => 2, 'f2' => true])
        ->and($draft['report']['homework'])->toBe(['title' => 'سمّع السورة', 'instructions' => null, 'accepts' => 'audio', 'due_days' => 30, 'practice' => false])
        ->and($draft['filled'])->not->toContain('notes_for_student')->toContain('items.'.$this->lessons[0].'.outcome', 'answers.f1', 'homework')
        ->and($draft['unsure'])->toContain('overall', 'answers.f1', 'items.'.$this->lessons[1].'.note')
        ->and($draft['left'])->toBe([]);

    // The model never saw the student's name, nor a lesson's id; the recording is gone.
    $sent = $this->model->prompt->user;
    expect($sent)->toContain('{child} قرأ')->not->toContain('يوسف')->not->toContain($this->lessons[0])->toContain('L1: ')
        ->and($this->heard->existed)->toBeTrue()
        ->and(is_file($this->heard->clip->path))->toBeFalse();

    // Nothing saved: the report waits as it was.
    $this->actAsTenant($this->academy);
    expect($this->report->fresh())->status->toBe('due')->engagement->toBeNull()
        ->and($this->report->items()->count())->toBe(0);
});

it('drafts an absence report\'s note to the family', function () {
    $this->actAsTenant($this->academy);
    $session = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->skip(1)->firstOrFail();
    $session->update(['status' => 'student_absent']);
    event(new SessionFinalized($session->academy_id, $session->id, 'student_absent', []));
    $absence = SessionReport::query()->where('session_id', $session->id)->firstOrFail();
    $this->clearTenant();
    fakeWriter(['absence_note' => '{child} ماحضرش النهارده.', 'engagement' => 5]);

    $draft = draftApiAs($this->teacher)->post("/api/v1/teacher/reports/{$absence->id}/draft", ['audio' => voiceNote()], ['Accept' => 'application/json'])
        ->assertOk()->json('data');

    expect($draft['report'])->toBe(['notes_internal' => null, 'absence_note' => 'يوسف ماحضرش النهارده.'])
        ->and($draft['filled'])->toBe(['absence_note']);
});

it('says when the note was not heard, the helper is off, or the report is not theirs', function () {
    fakeEars('آه طيب');
    draftApiAs($this->teacher)->post("/api/v1/teacher/reports/{$this->report->id}/draft", ['audio' => voiceNote()], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('type', fn ($type) => str_contains($type, 'ai-unheard'));
    expect($this->model->prompt)->toBeNull();

    draftApiAs($this->other)->post("/api/v1/teacher/reports/{$this->report->id}/draft", ['audio' => voiceNote()], ['Accept' => 'application/json'])->assertForbidden();
    draftApiAs($this->teacher)->post("/api/v1/teacher/reports/{$this->report->id}/draft", ['audio' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('audio');

    $this->actAsTenant($this->academy);
    app(SaveAiSettings::class)->handle(['report_draft' => false], $this->admin->id);
    $this->clearTenant();
    draftApiAs($this->teacher)->getJson("/api/v1/teacher/reports/{$this->report->id}")->assertJsonPath('data.assist.voice_draft', false);
    draftApiAs($this->teacher)->post("/api/v1/teacher/reports/{$this->report->id}/draft", ['audio' => voiceNote()], ['Accept' => 'application/json'])
        ->assertForbidden()->assertJsonPath('type', fn ($type) => str_contains($type, 'ai-off'));
});

it('holds a teacher to twenty drafts an hour, and the recording still leaves', function () {
    $this->actAsTenant($this->academy);
    $key = 'ai:report-draft:'.$this->academy->id.':'.$this->teacher->id;
    foreach (range(1, DraftReportFromVoice::PER_HOUR) as $i) {
        RateLimiter::hit($key, 3600);
    }
    $path = tempnam(sys_get_temp_dir(), 'note');
    file_put_contents($path, 'audio');

    expect(fn () => app(DraftReportFromVoice::class)->handle($this->report, $this->teacher->id, new AudioClip($path, 'audio/webm'), []))
        ->toThrow(fn (AiFailed $e) => expect($e->reason)->toBe('limit'));
    expect(is_file($path))->toBeFalse();
});

it('puts the student\'s name as {child} however the teacher joins it', function () {
    expect(DraftReportFromVoice::withoutName('يوسف قرأ، وقلت ليوسف يراجع، ويوسف شاطر. يوسفين لأ', 'يوسف أحمد'))
        ->toBe('{child} قرأ، وقلت ل{child} يراجع، و{child} شاطر. يوسفين لأ')
        ->and(DraftReportFromVoice::withoutName('Yousef read well', 'yousef Ali'))->toBe('{child} read well');
});

it('drafts the teacher area\'s form from a note recorded on the page', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    app()->setLocale('en');

    $page = Livewire::test(WriteReport::class, ['report' => $this->report->id])
        ->assertSee('Draft from a voice note')
        ->set('data.notes_for_student', 'My own words')
        ->set('voiceNote', voiceNote())
        ->call('draftFromVoice')
        ->assertHasNoErrors()
        ->assertNotified()
        ->assertSet('data.engagement', 5)
        ->assertSet('data.notes_for_student', 'My own words')
        ->assertSet('data.homework.title', 'سمّع السورة')
        ->assertSet('voiceNote', null)
        ->assertSee('Check these: the draft was not sure');
    $items = array_values($page->get('data.items'));
    expect($items[0])->toMatchArray(['node_id' => $this->lessons[0], 'outcome' => 'mastered', 'reason' => 'next'])
        ->and($page->get('voiceDraft.unsure'))->toContain('Overall', 'Pages memorised')
        ->and(is_file($this->heard->clip->path))->toBeFalse();

    // Nothing saved until the teacher sends it.
    expect($this->report->fresh()->status)->toBe('due');

    // A lesson row the note did not speak of stays on the form as the teacher left it.
    fakeWriter(['lessons' => [['key' => 'L1', 'outcome' => 'practiced']], 'unsure' => []]);
    $rows = $page->get('data.items');
    $rows['added'] = ['node_id' => $this->lessons[4], 'reason' => null, 'outcome' => null, 'score' => null, 'note' => null];
    $page->set('data.items', $rows)->set('voiceNote', voiceNote())->call('draftFromVoice')->assertHasNoErrors();
    $after = collect($page->get('data.items'))->keyBy('node_id');
    expect($after)->toHaveCount(count($rows))
        ->and($after[$this->lessons[0]]['outcome'])->toBe('mastered')
        ->and($after[$this->lessons[4]]['outcome'])->toBeNull();

    // Off for the academy: no recorder, and the call is refused.
    app(SaveAiSettings::class)->handle(['report_draft' => false], $this->admin->id);
    Livewire::test(WriteReport::class, ['report' => $this->report->id])->assertDontSee('Draft from a voice note')
        ->set('voiceNote', voiceNote())->call('draftFromVoice')->assertForbidden();
});
