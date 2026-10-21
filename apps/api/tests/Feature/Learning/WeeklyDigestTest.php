<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\Digest\SaveWeeklyDigest;
use Modules\Learning\Actions\Digest\SendWeeklyDigest;
use Modules\Learning\Actions\ReviewHomework;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Digest\WeekFacts;
use Modules\Learning\Digest\WordingCheck;
use Modules\Learning\Filament\Pages\WeeklyNoteSettings;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Models\WeeklyDigest;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The weekly note to guardians (docs/architecture/02, section 22): on Friday
 * evening on the learner's clock, once a week, to the guardians who receive
 * reports; only what the family may read already, the child as {child} in
 * what is kept, worded by the helper only within the facts.
 */

beforeEach(function () {
    Storage::fake('local');
    config(['queue.connections.assist' => ['driver' => 'sync']]);
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->student->forceFill(['name' => 'Yousef Khaled', 'timezone' => 'Asia/Riyadh'])->save();
    $this->first = strtok($this->student->name, ' ');

    // The week: the first lesson taken, its report sent (words for the family that name the child, and the
    // academy's own), and a round of practice; then Friday 7:30 pm on the learner's clock, within the week.
    $lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $this->friday = CarbonImmutable::parse($lesson->starts_at)->setTimezone('Asia/Riyadh')->next(CarbonImmutable::FRIDAY)->setTime(19, 30)->utc();
    $this->travelTo($this->friday->subHours(2));
    $lesson->update(['status' => 'completed']);
    event(new SessionFinalized($lesson->academy_id, $lesson->id, 'completed', []));
    app(SubmitSessionReport::class)->handle(SessionReport::query()->firstOrFail(), $this->teacher->id, [
        'engagement' => 5, 'overall' => 4, 'items' => [],
        'notes_for_student' => "{$this->first} read very well today",
        'notes_internal' => 'ONLY-FOR-THE-ACADEMY',
        'homework' => ['title' => 'Recite the surah', 'accepts' => 'audio', 'due_days' => 2],
    ]);
    SessionReport::query()->update(['review_note' => 'THE-ACADEMY-REVIEW']);
    DB::table('practice_rounds')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'student_id' => $this->student->id, 'game' => 'shell-sort',
        'focus' => 'free', 'correct' => 3, 'total' => 4, 'stars' => 2, 'seconds' => 30, 'played_by' => $this->student->id, 'played_at' => $this->friday->subDay()]);
    app(SaveWeeklyDigest::class)->handle(true);
    $this->clearTenant();
});

function notesTo(User $user): Collection
{
    return DB::table('notifications')->where('user_id', $user->id)->where('template_key', 'like', 'learning.weekly_digest%')->get();
}

/** A writer that answers what it is told, and notes what it was sent. */
function digestWriter(array $answer): object
{
    $writer = new class($answer) implements LanguageModel
    {
        public array $prompts = [];

        public function __construct(public array $answer) {}

        public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
        {
            $this->prompts[] = $prompt;

            return new AiAnswer($this->answer, 'the-writer');
        }
    };
    app()->instance(LanguageModel::class, $writer);

    return $writer;
}

it('sends the week on Friday evening on the learner\'s clock, to the guardians who receive reports, once', function () {
    // 6:30 pm in Riyadh: not yet.
    $this->travelTo($this->friday->subHour());
    $this->artisan('learning:digests')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(WeeklyDigest::query()->count())->toBe(0);

    $this->travelTo($this->friday);
    $this->artisan('learning:digests')->assertSuccessful();
    $this->artisan('learning:digests')->assertSuccessful();
    $this->actAsTenant($this->academy);
    $digest = WeeklyDigest::query()->sole();
    $sent = notesTo($this->guardian)->sole();
    expect($digest)->status->toBe('sent')->source->toBe('facts')->sent_to->toBe(1)
        ->and($digest->week_start->toDateString())->toBe($this->friday->setTimezone('Asia/Riyadh')->subDays(6)->toDateString())
        ->and($sent->dedupe_key)->toBe("digest:{$this->student->id}:{$digest->week_start->toDateString()}")
        ->and(json_decode($sent->action, true))->toMatchArray(['screen' => 'journey', 'learner_id' => $this->student->id])
        // The guardian reads the child's name; what is kept has {child}.
        ->and($sent->body)->toContain($this->first)->toContain('read very well today')
        ->and($digest->text_ar.$digest->text_en.json_encode($digest->facts))->not->toContain($this->first)->toContain('{child}')
        // The student is not sent their own week.
        ->and(notesTo($this->student))->toHaveCount(0);

    // A guardian who does not receive reports hears nothing next week either.
    DB::table('guardian_student')->update(['receives_reports' => false]);
    $this->clearTenant();
    $this->travelTo($this->friday->addWeek());
    $this->artisan('learning:digests')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(WeeklyDigest::query()->where('status', 'skipped')->sole()->reason)->toBe('no_guardians')
        ->and(notesTo($this->guardian))->toHaveCount(1);
});

it('waits for each learner\'s own Friday evening', function () {
    $this->actAsTenant($this->academy);
    $this->student->forceFill(['timezone' => 'Africa/Lagos'])->save();
    $this->clearTenant();
    // 7:30 pm in Riyadh is 5:30 pm in Lagos (neither moves its clocks).
    $this->travelTo($this->friday);
    $this->artisan('learning:digests')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(WeeklyDigest::query()->count())->toBe(0);
    $this->clearTenant();

    $this->travelTo($this->friday->addHours(2));
    $this->artisan('learning:digests')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(WeeklyDigest::query()->sole()->status)->toBe('sent');
});

it('tells only what the family may read already', function () {
    $this->actAsTenant($this->academy);
    $facts = app(WeekFacts::class)->for($this->student->id, $this->student->name, $this->friday->subDays(7), $this->friday);
    $all = json_encode($facts, JSON_UNESCAPED_UNICODE);

    expect($facts['lessons'])->toBe(['done' => 1, 'missed' => 0])
        ->and($facts['notes'])->toBe(['{child} read very well today'])
        ->and($facts['homework'])->toMatchArray(['given' => 1])
        ->and($facts['practice'])->toBe(['rounds' => 1, 'stars' => 2])
        ->and($facts['stars'])->toBeInt()->toBeGreaterThanOrEqual(2)
        ->and($all)->not->toContain('ONLY-FOR-THE-ACADEMY')->not->toContain('THE-ACADEMY-REVIEW')->not->toContain($this->first);

    // The plain journey has no stars, so the note has none either.
    DB::table('learning_settings')->update(['journey_style' => 'plain']);
    expect(app(WeekFacts::class)->for($this->student->id, $this->student->name, $this->friday->subDays(7), $this->friday)['stars'])->toBeNull();
});

it('lets the helper word it within the facts, and sends the facts when it says more', function () {
    $this->actAsTenant($this->academy);
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test']);
    app(SaveAiSettings::class)->handle(['digest_wording' => true], $this->admin->id);
    $writer = digestWriter(['ar' => 'أسبوع جميل لـ{child}: حضر حصة واحدة (1) وجمع نجمتين في التدريب.', 'en' => '{child} had a good week: 1 lesson and 2 practice stars.']);
    $send = fn () => app(SendWeeklyDigest::class)->handle(WeeklyDigest::query()->create([
        'student_id' => $this->student->id, 'week_start' => CarbonImmutable::now()->addDays(WeeklyDigest::query()->count() * 7)->toDateString(),
    ]), $this->friday);

    expect($send())->source->toBe('ai')->text_en->toBe('{child} had a good week: 1 lesson and 2 practice stars.');
    // Sent the week alone: the child as {child}, no name, no internal words.
    $sentToWriter = $writer->prompts[0]->user;
    expect($sentToWriter)->toContain('{child} read very well today')->not->toContain($this->first)->not->toContain('ONLY-FOR-THE-ACADEMY')
        ->and($sentToWriter)->not->toContain($this->student->id);

    // A number the week does not hold, a link, or a phone number: the facts go out instead.
    foreach (['{child} came to 9 lessons this week, a lovely week.', 'A good week for {child}, see https://example.com for more.', 'Call us on 0501234567 about {child}, a good week.'] as $en) {
        digestWriter(['ar' => 'أسبوع جميل لـ{child} في الأكاديمية.', 'en' => $en]);
        expect($send())->source->toBe('facts');
    }
});

it('checks a note is one plain paragraph of the week\'s own numbers', function () {
    expect(WordingCheck::clean("أسبوع حلو لـ{child}:\n٢ حصة و٣ نجوم.", [2, 3]))->toBe('أسبوع حلو لـ{child}: ٢ حصة و٣ نجوم.')
        ->and(WordingCheck::clean('أسبوع حلو لـ{child}: ٤ حصص.', [2, 3]))->toBeNull()
        ->and(WordingCheck::clean('A good week for {student}, really lovely.', []))->toBeNull()
        ->and(WordingCheck::clean('Write to hello@academy.example any time.', []))->toBeNull()
        ->and(WordingCheck::clean('Short.', []))->toBeNull()
        ->and(WordingCheck::clean(str_repeat('كلام ', 150), []))->toBeNull()
        ->and(WordingCheck::clean(['not text'], []))->toBeNull();
});

it('sends nothing for a quiet week, or while the note is off', function () {
    $this->actAsTenant($this->academy);
    $make = fn (string $week) => WeeklyDigest::query()->create(['student_id' => $this->student->id, 'week_start' => $week]);
    // Three weeks on, nothing happened that week.
    expect(app(SendWeeklyDigest::class)->handle($make('2099-01-03'), $this->friday->addWeeks(3)))->status->toBe('skipped')->reason->toBe('quiet');

    app(SaveWeeklyDigest::class)->handle(false);
    expect(app(SendWeeklyDigest::class)->handle($make('2099-01-10'), $this->friday))->status->toBe('skipped')->reason->toBe('off')
        ->and(notesTo($this->guardian))->toHaveCount(0);
    $this->clearTenant();
    $this->travelTo($this->friday);
    $this->artisan('learning:digests')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(WeeklyDigest::query()->count())->toBe(2);
});

it('reads to each guardian in their language', function () {
    $this->actAsTenant($this->academy);
    $this->guardian->forceFill(['locale' => 'en'])->save();
    app(SendWeeklyDigest::class)->handle(WeeklyDigest::query()->create(['student_id' => $this->student->id, 'week_start' => '2099-01-03']), $this->friday);

    $sent = notesTo($this->guardian)->sole();
    expect($sent->title)->toBe("{$this->first}'s week")->and($sent->body)->toContain("{$this->first}'s week at the academy")->toContain('Lessons: 1 attended');
});

it('lets the guardian hear the recording of the week from their own link, and no one else', function () {
    $this->actAsTenant($this->academy);
    $homework = Homework::query()->sole();
    // As a browser records it: a webm container (stored as video/webm).
    $recording = UploadedFile::fake()->createWithContent('recording.webm', str_repeat("\x1A\x45\xDF\xA3", 64));
    $submission = app(SubmitHomework::class)->handle($homework, $this->student->id, null, $recording);
    app(ReviewHomework::class)->handle($homework->fresh(), $this->teacher->id, 5, 'Beautiful');
    app(SendWeeklyDigest::class)->handle(WeeklyDigest::query()->create(['student_id' => $this->student->id, 'week_start' => '2099-01-03']), $this->friday);

    $sent = notesTo($this->guardian)->sole();
    expect($sent->template_key)->toBe('learning.weekly_digest_voice')
        ->and(WeeklyDigest::query()->sole()->voice_submission_id)->toBe($submission->id);
    preg_match('~https?://\S+/voice/\S+~', $sent->body, $m);
    $link = parse_url($m[0]);
    $page = $link['path'].'?'.$link['query'];
    $this->clearTenant();

    $html = $this->get('http://noor.lms.test'.$page)->assertOk()->assertSee($this->first)->assertSee('Recite the surah')->getContent();
    preg_match('~src="([^"]*/voice/[^"]+/file[^"]*)"~', $html, $src);
    $this->get('http://noor.lms.test'.html_entity_decode($src[1]))->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=recording');

    // Not signed, or signed for someone else: no.
    $this->get('http://noor.lms.test'.$link['path'].'?u='.$this->guardian->id)->assertForbidden();
    $this->get('http://noor.lms.test'.str_replace($this->guardian->id, $this->student->id, $page))->assertForbidden();
    // A guardian who no longer receives the child's reports: no.
    $this->actAsTenant($this->academy);
    DB::table('guardian_student')->update(['receives_reports' => false]);
    $this->clearTenant();
    $this->get('http://noor.lms.test'.$page)->assertForbidden();
});

it('lets the owner and admins turn the note on and off, audited', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    app()->setLocale('en');

    Livewire::test(WeeklyNoteSettings::class)->assertSet('data.on', true)->assertSee('Friday after 7 pm')
        ->set('data.on', false)->call('save')->assertHasNoErrors();
    expect(DB::table('learning_settings')->value('weekly_digest'))->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'learning.weekly_digest_saved')->latest('created_at')->value('data'))->toContain('off');

    $this->actingAs(User::factory()->withRole(Role::Supervisor)->create());
    Livewire::test(WeeklyNoteSettings::class)->assertForbidden();
});
