<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Models\Program;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Contracts\JourneyQuran;
use Modules\Learning\Contracts\LearningQuran;
use Modules\Learning\Filament\Teacher\StudentProgress;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Actions\Hifz\MarkMemorized;
use Modules\Quran\Actions\Passages\SetLessonPassage;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Filament\Family\FamilyHifz;
use Modules\Quran\Filament\Pages\HifzOverview;
use Modules\Quran\Filament\Pages\QuranSettingsPage;
use Modules\Quran\Filament\Teacher\StudentHifz;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Queries\Hifz\HifzMap;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Quran\LessonQuran;
use Modules\SharedKernel\Tenancy\TenantContext;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranLessons;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class, QuranLessons::class);

/*
 * The Quran is an area of the academy's SaaS plan (Feature::Quran,
 * docs/architecture/02 section 26). An academy on no plan has it. A plan
 * that leaves it out closes its screens and routes (403
 * feature-not-in-plan), the contracts it binds answer as if it were not
 * there, and nothing new is written; everything it holds stays, and comes
 * back as it was with a plan that has it. A subject's kind (general or the
 * Quran) is what the hifz map follows.
 */

beforeEach(function () {
    Mail::fake();
    Http::fake(['*/twirp/livekit.RoomService/CreateRoom' => Http::response(['name' => 'r'])]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, Program::query()->value('id'));
    $this->makeQuranSubject($this->quran);
    $this->lessonReads($this->lessons[0], 1, 2, 3);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    app(MarkMemorized::class)->handle($this->student->id, [1, 2], 'good');
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    CarbonImmutable::setTestNow(CarbonImmutable::parse($this->lesson->starts_at)->subMinutes(5));
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The academy on a plan with the Quran's area, or without it. */
function quranInPlan(TestCase $test, bool $with): void
{
    app(TenantContext::class)->clear();
    $plan = SaasPlan::query()->firstOrCreate(['code' => $with ? 'with-quran' : 'no-quran'], ['name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => $with ? ['quran', 'groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site'] : ['groups', 'curriculum', 'games', 'messaging', 'announcements', 'support', 'evaluation', 'site']]);
    app(ChangeAcademyPlan::class)->handle($test->academy->id, $plan->id, 'For this test');
}

function quranApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

function quranIn(TestCase $test, callable $read): mixed
{
    $tenants = app(TenantContext::class);
    $tenants->set($test->academy);
    $value = $read();
    $tenants->clear();

    return $value;
}

/** What each surface shows of the Quran now, for each side. */
function quranSurfaces(TestCase $test): array
{
    $enrollment = $test->booked->enrollmentId;
    $status = fn (User $user, string $method, string $path, array $body = []) => quranApiAs($user)->json($method, $path, $body)->status();

    return [
        'family_map' => $status($test->student, 'GET', '/api/v1/learning/hifz'),
        'family_book' => $status($test->guardian, 'GET', '/api/v1/learning/mistakes?learner_id='.$test->student->id),
        'mushaf' => $status($test->student, 'GET', "/api/v1/learning/lessons/{$test->lesson->id}/mushaf"),
        'teacher_map' => $status($test->teacher, 'GET', "/api/v1/teacher/students/{$enrollment}/hifz"),
        'teacher_mark' => $status($test->teacher, 'POST', "/api/v1/teacher/students/{$enrollment}/hifz/memorized", ['pages' => [3], 'strength' => 'good']),
        'surahs' => $status($test->teacher, 'GET', '/api/v1/teacher/texts/quran'),
        'journey' => quranApiAs($test->student)->getJson('/api/v1/learning/journey/map')->assertOk()->json('data.hifz'),
        'report_check' => quranApiAs($test->teacher)->getJson('/api/v1/teacher/students')->status(),
    ];
}

it('closes the Quran\'s routes and answers with nothing without the plan\'s area, keeps its data, and opens again as it was', function () {
    $open = quranSurfaces($this);
    expect($open)->toMatchArray(['family_map' => 200, 'family_book' => 200, 'mushaf' => 200, 'teacher_map' => 200, 'teacher_mark' => 200, 'surahs' => 200])
        ->and($open['journey'])->toMatchArray(['daily' => 3]);
    $kept = quranIn($this, fn () => HifzPage::query()->orderBy('page')->pluck('state', 'page')->all());
    expect($kept)->toBe([1 => 'memorized', 2 => 'memorized', 3 => 'memorized']);

    quranInPlan($this, false);
    $closed = quranSurfaces($this);
    expect($closed)->toMatchArray(['family_map' => 403, 'family_book' => 403, 'mushaf' => 403, 'teacher_map' => 403, 'teacher_mark' => 403, 'surahs' => 403, 'journey' => null])
        ->and(quranApiAs($this->student)->getJson('/api/v1/learning/hifz')->json())->toMatchArray(['type' => 'feature-not-in-plan', 'feature' => 'quran']);

    // Nothing of it in the contracts the modules below ask, and nothing written.
    quranIn($this, function () {
        $course = app(EnrollmentApi::class)->find($this->booked->enrollmentId);
        expect(app(HifzMap::class)->follows($course))->toBeFalse()
            ->and(app(JourneyQuran::class)->today($this->student->id))->toBeNull()
            ->and(app(LearningQuran::class)->recitationOffered())->toBeFalse()
            ->and(app(LearningQuran::class)->targetFromLessons([$this->lessons[0]]))->toBeNull()
            // What a homework already asks to recite is neither shown nor touched by a save.
            ->and(app(LearningQuran::class)->presentTarget(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3]))->toBeNull()
            ->and(app(LearningQuran::class)->recitationTarget(null, 'audio', ['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3]))->toBe(['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 3])
            ->and(app(LearningQuran::class)->reportFields(null, null))->toBe([])
            ->and(app(LearningQuran::class)->studentActions($this->booked->enrollmentId))->toBe([])
            ->and(app(LessonQuran::class)->classPanel($this->lesson->id, $this->teacher->id, ['channel' => 'lesson.x', 'auth' => '/x', 'echo' => null]))->toBeNull();
        expect(fn () => app(SetLessonPassage::class)->handle($this->lessons[1], VerseRange::of(1, 1, 1)))->toThrow(FeatureNotInPlan::class);
        $other = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
        expect(fn () => app(SaveSubject::class)->handle($other, 'تجويد', 'Tajweed', [], [], 'quran', 'tilawa'))->toThrow(ValidationException::class);
        // The Quran subject it has keeps its kind (saved again as it is), and the hifz pages stay.
        expect(app(SaveSubject::class)->handle($this->quran, 'قرآن', 'Quran')->kind)->toBe('quran')
            ->and(HifzPage::query()->orderBy('page')->pluck('state', 'page')->all())->toBe([1 => 'memorized', 2 => 'memorized', 3 => 'memorized']);
    });

    // The screens are gone from every panel.
    $this->actAsTenant($this->academy);
    $this->actingAs($this->admin);
    expect(HifzOverview::canAccess())->toBeFalse()->and(QuranSettingsPage::canAccess())->toBeFalse();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    expect(StudentHifz::canAccess())->toBeFalse();
    Livewire::test(StudentProgress::class, ['enrollment' => $this->booked->enrollmentId])->assertActionDoesNotExist('quranHifz');
    $this->actingAs($this->student);
    Filament::setCurrentPanel('family');
    expect(FamilyHifz::canAccess())->toBeFalse()->and(FamilyHifz::shouldRegisterNavigation())->toBeFalse();
    $this->clearTenant();

    // The class page is a room like any other; the mushaf's own door is shut.
    app('auth')->forgetGuards();
    $this->actingAs($this->teacher);
    $page = $this->get("http://noor.lms.test/class/{$this->lesson->id}")->assertOk()->assertDontSee('id="mushaf"', false);
    expect($page->viewData('side'))->toBeNull();
    $this->getJson("http://noor.lms.test/class/{$this->lesson->id}/mushaf")->assertForbidden();

    // With the area again, everything is where it was.
    quranInPlan($this, true);
    $again = quranSurfaces($this);
    expect($again)->toMatchArray(['family_map' => 200, 'mushaf' => 200, 'teacher_map' => 200, 'surahs' => 200])
        ->and($again['journey'])->toMatchArray(['daily' => 3])
        ->and(quranIn($this, fn () => HifzPage::query()->orderBy('page')->pluck('state', 'page')->all()))->toBe($kept);
    app('auth')->forgetGuards();
    $this->actingAs($this->teacher);
    $this->get("http://noor.lms.test/class/{$this->lesson->id}")->assertOk()->assertSee('id="mushaf"', false);
});

it('follows a course by its subject\'s kind: a Quran subject\'s, or a learner who already has pages', function () {
    quranIn($this, function () {
        $map = app(HifzMap::class);
        $course = fn () => app(EnrollmentApi::class)->find($this->booked->enrollmentId);
        expect($map->follows($course()))->toBeTrue();

        // A general subject: followed only while the learner has pages on their map.
        app(SaveSubject::class)->handle($this->quran, 'قرآن', 'Quran', [], [], 'general');
        expect(app(CatalogApi::class)->subjectKind($this->quran))->toBe(['kind' => 'general', 'quran_track' => null])
            ->and($map->follows($course()))->toBeTrue();
        DB::table('hifz_pages')->delete();
        expect($map->follows($course()))->toBeFalse();

        // Made a Quran subject again, with its track; one not known is refused.
        app(SaveSubject::class)->handle($this->quran, 'قرآن', 'Quran', [], [], 'quran', 'talqin');
        expect(app(CatalogApi::class)->subjectKind($this->quran))->toBe(['kind' => 'quran', 'quran_track' => 'talqin'])
            ->and(app(CatalogApi::class)->subjectsOfKind('quran'))->toBe([$this->quran])
            ->and($map->follows($course()))->toBeTrue();
        expect(fn () => app(SaveSubject::class)->handle($this->quran, 'قرآن', 'Quran', [], [], 'quran', 'poetry'))->toThrow(ValidationException::class)
            ->and(fn () => app(SaveSubject::class)->handle($this->quran, 'قرآن', 'Quran', [], [], 'music'))->toThrow(ValidationException::class);
        // The database holds the shape too.
        expect(fn () => DB::table('subjects')->where('id', $this->quran)->update(['kind' => 'general', 'quran_track' => 'hifz']))->toThrow(QueryException::class);
    });
});

it('sets a lesson\'s passage only from the platform\'s copy, on a lesson', function () {
    quranIn($this, function () {
        $set = app(SetLessonPassage::class);
        expect($set->handle($this->lessons[1], VerseRange::of(1, 1, 2))->range()->toArray())->toBe(['kind' => 'quran', 'surah' => 1, 'from' => 1, 'to' => 2]);
        expect(fn () => $set->handle($this->lessons[1], VerseRange::of(1, 2, 9)))->toThrow(ValidationException::class)
            ->and(fn () => $set->handle($this->lessons[1], VerseRange::of(9, 1, 1)))->toThrow(ValidationException::class);
        $unit = DB::table('curriculum_nodes')->where('kind', 'unit')->value('id');
        expect(fn () => $set->handle((string) $unit, VerseRange::of(1, 1, 1)))->toThrow(ValidationException::class);
        expect($set->handle($this->lessons[1], null))->toBeNull()
            ->and(DB::table('quran_lesson_passages')->where('node_id', $this->lessons[1])->exists())->toBeFalse();
    });
});
