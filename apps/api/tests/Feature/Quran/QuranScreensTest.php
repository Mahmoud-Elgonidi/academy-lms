<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Filament\Console\Pages\QuranConsole;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Actions\Exams\BookQuranExam;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Actions\Plans\StartQuranPlan;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Domain\Programs\Pace;
use Modules\Quran\Filament\Family\FamilyHifz;
use Modules\Quran\Filament\Pages\HifzStudent;
use Modules\Quran\Filament\Pages\QuranCertificates;
use Modules\Quran\Filament\Pages\QuranExamResult;
use Modules\Quran\Filament\Pages\QuranExams;
use Modules\Quran\Filament\Pages\QuranHomePage;
use Modules\Quran\Filament\Pages\QuranProgramBuilder;
use Modules\Quran\Filament\Pages\QuranPrograms;
use Modules\Quran\Filament\Pages\QuranSettingsPage;
use Modules\Quran\Filament\Pages\QuranStudents;
use Modules\Quran\Filament\Teacher\QuranLesson;
use Modules\Quran\Filament\Teacher\QuranLessons;
use Modules\Quran\Filament\Teacher\StudentHifz;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Setup\QuranStep;
use Modules\Quran\Support\Amounts;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Setup\SetupStep;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranLessons as QuranFooting;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranFooting::class);

/*
 * The Quran's screens (docs/architecture/02, section 26): the back office's
 * home, students, programs and their builder, exams and results,
 * certificates and settings; the teacher's Quran lessons and «حصة قرآن»;
 * the family's Quran page (the kids' look with stars and no counts). Each
 * keeps to its ability, leaves with the plan's Quran, and writes only
 * through the module's Actions. The fixture's text is made up in Tanzil's
 * shape, never the Quran: surah 1 (3 verses, pages 1–2, part 1), surah 2
 * (2 verses, page 3, part 2).
 */

beforeEach(function () {
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    $this->enrolStudentOnPlan();
    $this->makeQuranSubject($this->quran);
    $this->guardian = User::query()->findOrFail(app(PeopleApi::class)->guardiansOf($this->student->id)[0]);
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create(['timezone' => 'Africa/Cairo']);
    $this->sessions = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->pluck('id')->all();
    $this->program = app(SaveQuranProgram::class)->handle(null, [
        'name' => 'Fixture hifz', 'track' => 'hifz', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => 2, 'scope_to_verse' => 2,
        'direction' => 'forward', 'unit' => 'verses', 'new_amount' => 2, 'near_portions' => 1, 'far_pages' => 0, 'pass_grade' => 'good', 'exam_stops' => 'surah',
        'counts_for_certificate' => true,
    ], $this->admin->id);
    app(SaveQuranSettings::class)->handle(['exams_enabled' => true, 'certificates_enabled' => true]);
    Filament::setCurrentPanel('academy');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function screensPlan(TestCase $test): QuranPlan
{
    return app(StartQuranPlan::class)->handle($test->booked->enrollmentId, $test->program->id, [], $test->admin->id);
}

/** The first lesson (1:1–2) recorded, its new part passing; then 1:3 completes surah 1 at the second. */
function screensRecord(TestCase $test, int $lesson = 0): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse(Session::query()->findOrFail($test->sessions[$lesson])->starts_at)->addMinutes(20));
    app(RecordQuranLesson::class)->handle($test->sessions[$lesson], $test->teacher->id, ['parts' => ['new' => []]]);
}

it('keeps each back-office screen to its ability, and closes them all when the plan leaves the Quran out', function () {
    $pages = [QuranHomePage::class, QuranStudents::class, QuranPrograms::class, QuranProgramBuilder::class, QuranExams::class, QuranCertificates::class, QuranSettingsPage::class];
    $this->actingAs($this->admin);
    foreach ($pages as $page) {
        expect($page::canAccess())->toBeTrue($page);
    }
    // A supervisor follows and examines; the programs and settings are the office's.
    $this->actingAs($this->supervisor);
    expect(QuranHomePage::canAccess())->toBeTrue()->and(QuranStudents::canAccess())->toBeTrue()->and(QuranExams::canAccess())->toBeTrue()
        ->and(QuranCertificates::canAccess())->toBeTrue()->and(QuranPrograms::canAccess())->toBeFalse()->and(QuranSettingsPage::canAccess())->toBeFalse();
    $this->actingAs($this->teacher);
    foreach ($pages as $page) {
        expect($page::canAccess())->toBeFalse($page);
    }

    // A plan without the Quran: every screen leaves, the teacher's and the family's too, and the data stays.
    $this->clearTenant();
    $plan = SaasPlan::query()->create(['code' => 'no-quran', 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    $this->actAsTenant($this->academy);
    $this->actingAs($this->admin);
    foreach ($pages as $page) {
        expect($page::canAccess())->toBeFalse($page);
    }
    $this->actingAs($this->teacher);
    expect(QuranLessons::canAccess())->toBeFalse()->and(QuranLesson::canAccess())->toBeFalse();
    $this->actingAs($this->student);
    expect(FamilyHifz::canAccess())->toBeFalse();
    expect(QuranProgram::query()->count())->toBe(1);
    $this->clearTenant();
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/quran/programs')->assertForbidden();
});

it('shows the home in numbers: who keeps the pace, exams waiting, latest certificates', function () {
    screensPlan($this);
    screensRecord($this);
    $this->actingAs($this->supervisor);
    $page = Livewire::test(QuranHomePage::class)->assertOk()->assertSee(__('quran::screens.home.stats.on_track'))->assertSee('data-count', false);
    expect($page->instance()->home['pace']['on_track'])->toBe(1)
        ->and($page->instance()->home['month']['lessons'])->toBe(1);

    expect(Pace::status(3, 2))->toBe('on_track')->and(Pace::status(3, 1))->toBe('behind')->and(Pace::status(0, 0))->toBe('new')
        ->and(Pace::status(2, 2, frozen: true))->toBe('frozen')->and(Pace::status(2, 0, finished: true))->toBe('finished');
});

it('starts a course\'s plan from the students\' screen and adjusts its pace, through the Actions', function () {
    $this->actingAs($this->admin);
    $page = Livewire::test(QuranStudents::class)->assertOk()->assertSee($this->student->name)->assertSee(__('quran::screens.pace_status.no_plan'));
    $page->callAction('startPlan', ['program_id' => $this->program->id, 'juz' => [], 'surahs' => [], 'strength' => 'good'], ['enrollment' => $this->booked->enrollmentId])
        ->assertHasNoActionErrors()->assertNotified(__('quran::screens.plan.started'));
    $plan = QuranPlan::query()->sole();
    expect($plan->enrollment_id)->toBe($this->booked->enrollmentId)->and($plan->position()?->toArray())->toBe(['surah' => 1, 'verse' => 1]);

    // Its own pace, and a refusal under the field it is about.
    $page = Livewire::test(QuranStudents::class);
    $page->callAction('adjustPlan', ['new_amount' => 99], ['enrollment' => $this->booked->enrollmentId])->assertHasActionErrors(['new_amount']);
    $page = Livewire::test(QuranStudents::class);
    $page->callAction('adjustPlan', ['new_amount' => 3, 'near_portions' => '', 'far_pages' => ''], ['enrollment' => $this->booked->enrollmentId])
        ->assertHasNoActionErrors()->assertNotified(__('quran::screens.plan.saved'));
    expect($plan->refresh()->new_amount)->toBe(3.0)
        ->and(DB::table('audit_logs')->where('action', 'quran.plan_adjusted')->exists())->toBeTrue();
    expect($page->instance()->board['rows'][0]['plan']['pace'])->toContain(Amounts::of(3, 'verses'));

    // A supervisor reads the screen, and may not start or adjust.
    $this->actingAs($this->supervisor);
    expect(Livewire::test(QuranStudents::class)->instance()->board['manage'])->toBeFalse();
});

it('builds a program from a ready track with its week worked out, and keeps a followed program\'s shape', function () {
    $this->actingAs($this->admin);
    Livewire::test(QuranPrograms::class)->assertOk()->assertSee('Fixture hifz')->assertSee(__('quran::programs.presets.noorani.name'));

    // The Noorani Qaida needs no text: its week is its lessons in order.
    $builder = Livewire::test(QuranProgramBuilder::class, ['preset' => 'noorani'])->assertOk()
        ->assertSet('data.track', 'noorani')->set('perWeek', 3);
    expect(array_column(array_column($builder->instance()->preview['lessons'], 'lesson'), 'number'))->toBe([1, 2, 3]);
    $builder->set('data.name', '')->call('save')->assertHasErrors('data.name')
        ->set('data.name', 'القاعدة للصغار')->call('save')->assertHasNoErrors();
    $made = QuranProgram::query()->where('name', 'القاعدة للصغار')->sole();
    expect($made->preset)->toBe('noorani')->and($made->track)->toBe('noorani');

    // A verse program: the week from the made-up copy, the scope by parts, the shape locked once followed.
    $builder = Livewire::test(QuranProgramBuilder::class, ['program' => $this->program->id])->assertOk();
    expect($builder->instance()->preview['lessons'][0]['new']['ranges'][0])->toMatchArray(['surah' => 1, 'from' => 1, 'to' => 2])
        ->and($builder->instance()->strip)->toMatchArray(['from' => 1, 'to' => 2]);
    $builder->call('scopeParts', 2, 2)->assertSet('data.scope_from_surah', 2)->assertSet('data.scope_to_verse', 2)
        ->call('scopeParts', 1, 2);
    screensPlan($this);
    $builder = Livewire::test(QuranProgramBuilder::class, ['program' => $this->program->id])->assertSee(trans_choice('quran::screens.builder.locked', 1, ['n' => 1]));
    $builder->call('setTrack', 'tilawa')->assertSet('data.track', 'hifz')
        ->set('data.near_portions', 0)->call('save')->assertHasNoErrors();
    expect($this->program->refresh()->near_portions)->toBe(0);
    $builder->set('data.direction', 'backward')->call('save')->assertHasErrors('data.direction');

    // Archived, never deleted.
    $builder->callAction('archive');
    expect($this->program->refresh()->archived_at)->not->toBeNull();
});

it('books an exam with an allowed examiner on the viewer\'s clock, records it on the examiner\'s page, and revokes the certificate', function () {
    screensPlan($this);
    screensRecord($this);
    screensRecord($this, 1);
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    $exam = QuranExam::query()->where('stop_kind', 'surah')->where('stop_number', 1)->sole();
    $this->actingAs($this->admin);

    $page = Livewire::test(QuranExams::class)->assertOk()->assertSee($this->student->name);
    // The course's own teacher is not offered; the supervisor and the other teacher are.
    expect(array_keys($page->instance()->examiners(['exam' => $exam->id])))->toContain($this->supervisor->id)->toContain($this->other->id)
        ->not->toContain($this->teacher->id);
    $page->callAction('book', ['examiner_id' => $this->supervisor->id, 'date' => '2026-10-08', 'time' => '16:00', 'duration_min' => 30], ['exam' => $exam->id])
        ->assertHasNoActionErrors()->assertNotified(__('quran::screens.exams.booked'));
    $exam->refresh();
    expect($exam->status)->toBe('booked')
        ->and(CarbonImmutable::parse(Session::query()->findOrFail($exam->session_id)->starts_at)->equalTo(CarbonImmutable::parse('2026-10-08 16:00', 'Africa/Cairo')))->toBeTrue();

    // Only the examiner opens the result page; the mistakes counted give the grade as they go.
    $this->actingAs($this->admin);
    $this->clearTenant();
    $this->actingAs($this->admin)->get("http://noor.lms.test/admin/quran/exams/{$exam->id}/result")->assertNotFound();
    $this->actAsTenant($this->academy);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 16:05', 'Africa/Cairo'));
    $this->actingAs($this->supervisor);
    $result = Livewire::test(QuranExamResult::class, ['exam' => $exam->id])->assertOk()->assertSee(__('quran::screens.result.will_pass'))
        ->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)
        ->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)->call('bump', 'wrong', 1)
        ->assertSee(__('quran::screens.result.will_fail'))
        ->call('record')->assertHasErrors(['note', 'retakeOn']);
    $result->set('counts.wrong', 0)->call('record')->assertHasNoErrors()->assertNotified(__('quran::screens.result.passed'));
    expect($exam->refresh()->status)->toBe('passed');
    $certificate = QuranCertificate::query()->sole();

    // The certificates: listed, opened signed, revoked with a reason by the office alone.
    Livewire::test(QuranCertificates::class)->assertSee($certificate->number);
    expect(Gate::allows('quran.manage'))->toBeFalse();
    $this->actingAs($this->admin);
    Livewire::test(QuranCertificates::class)
        ->callAction('revoke', ['reason' => 'x'], ['certificate' => $certificate->id])->assertHasActionErrors(['reason']);
    Livewire::test(QuranCertificates::class)
        ->callAction('revoke', ['reason' => 'Issued by mistake'], ['certificate' => $certificate->id])->assertHasNoActionErrors();
    expect($certificate->refresh()->isRevoked())->toBeTrue();

    // A suggested exam is cancelled with a reason.
    $open = QuranExam::query()->where('status', 'suggested')->first();
    if ($open !== null) {
        Livewire::test(QuranExams::class)->callAction('cancel', ['reason' => 'Travelling'], ['exam' => $open->id])->assertHasNoActionErrors();
        expect($open->refresh()->status)->toBe('cancelled');
    }
});

it('saves every Quran setting from its cards, a new mistake type taking a key once', function () {
    $this->actingAs($this->admin);
    $page = Livewire::test(QuranSettingsPage::class)->assertOk()->assertSee(__('quran::screens.settings.mistakes.title'));
    $types = $page->get('data.mistake_types');
    $types[(string) str()->uuid()] = ['key' => null, 'name_ar' => 'نسيان', 'name_en' => 'Forgotten word', 'weight' => 1.5];
    $page->set('data.mistake_types', $types)->set('data.grade_scale.good', 2.5)->set('data.examiner', 'any_teacher')
        ->set('data.certificate_title', 'شهادة إتمام')->call('save')->assertHasNoErrors()->assertNotified(__('quran::screens.settings.saved'));
    $s = QuranSettings::current();
    expect(array_column($s->mistakeTypes(), 'key'))->toBe(['wrong', 'hesitation', 'tajweed', 'forgotten_word'])
        ->and($s->gradeScale()['good'])->toBe(2.5)->and($s->examiner)->toBe('any_teacher')->and($s->certificate_title)->toBe('شهادة إتمام');
    // The same name again keeps its key apart.
    expect(array_column(QuranSettingsPage::keyed([['key' => 'forgotten_word'], ['key' => null, 'name_en' => 'Forgotten word']]), 'key'))->toBe(['forgotten_word', 'forgotten_word_2']);
    // A scale that goes down is refused under it.
    Livewire::test(QuranSettingsPage::class)->set('data.grade_scale.excellent', 9)->call('save')->assertHasErrors('data.grade_scale.acceptable');
});

it('gives the teacher their Quran lessons and «حصة قرآن»: the portion, quick counters, a grade, recorded once', function () {
    screensPlan($this);
    Filament::setCurrentPanel('teach');
    $this->actingAs($this->teacher);
    CarbonImmutable::setTestNow(CarbonImmutable::parse(Session::query()->findOrFail($this->sessions[0])->starts_at)->subHours(1));
    $lessons = Livewire::test(QuranLessons::class)->assertOk();
    expect(collect([...$lessons->instance()->lessons['today'], ...$lessons->instance()->lessons['later']])->pluck('id'))->toContain($this->sessions[0]);

    // Another teacher's lesson is not theirs.
    $this->actingAs($this->other);
    Livewire::test(QuranLesson::class, ['session' => $this->sessions[0]])->assertNotFound();

    $this->actingAs($this->teacher);
    $page = Livewire::test(QuranLesson::class, ['session' => $this->sessions[0]])->assertOk()
        ->assertSee(__('quran::programs.parts.new'))->assertSee(__('quran::screens.lesson.opens', ['at' => '']), false);
    expect($page->get('sheet')[$this->student->id])->toHaveKey('new');
    CarbonImmutable::setTestNow(CarbonImmutable::parse(Session::query()->findOrFail($this->sessions[0])->starts_at)->addMinutes(15));
    $page = Livewire::test(QuranLesson::class, ['session' => $this->sessions[0]])
        ->call('bump', 'new', 'hesitation', 1)->assertSee(__('quran::screens.lesson.outcome.advance'))
        ->set("sheet.{$this->student->id}.new.grade", 'repeat')->call('record')->assertHasErrors("sheet.{$this->student->id}.new.note")
        ->set("sheet.{$this->student->id}.new.grade", '')->call('record')->assertHasNoErrors()->assertNotified(__('quran::screens.lesson.recorded'))
        ->assertSee(__('quran::screens.lesson.recorded_advanced'));
    $result = QuranLessonResult::query()->sole();
    expect($result->parts['new']['mistakes'])->toBe(['hesitation' => 1])->and($result->advanced)->toBeTrue();

    // The student's plan page: the plan card and their own pace.
    Livewire::test(StudentHifz::class, ['enrollment' => $this->booked->enrollmentId])->assertOk()->assertSee('Fixture hifz')
        ->callAction('adjustPlan', ['new_amount' => 1], ['enrollment' => $this->booked->enrollmentId])->assertHasNoActionErrors();
    expect(QuranPlan::query()->sole()->new_amount)->toBe(1.0);
    // The office's view of the same student.
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);
    Livewire::test(HifzStudent::class, ['enrollment' => $this->booked->enrollmentId])->assertOk()->assertSee('Fixture hifz')->assertActionVisible('adjustPlan');
});

it('shows the family their Quran: the plan, the next portion, exams and certificates; the kids\' look in stars without counts', function () {
    screensPlan($this);
    screensRecord($this);
    Filament::setCurrentPanel('family');
    $this->actingAs($this->guardian);
    expect(FamilyHifz::canAccess())->toBeTrue()->and(FamilyHifz::shouldRegisterNavigation())->toBeTrue()
        ->and(FamilyHifz::getNavigationLabel())->toBe(__('quran::screens.family.nav.classic'));
    Livewire::test(FamilyHifz::class)->assertOk()->assertSee('Fixture hifz')->assertSee(__('quran::screens.family.next'))
        ->assertSee(__('quran::screens.family.progress', ['n' => 40]))->assertSee(__('quran::programs.grades.excellent'));

    // A child: the journey, stars and simple words, no percentages.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::now()->subYears(8), null);
    $this->actingAs($this->student);
    expect(FamilyHifz::getNavigationLabel())->toBe(__('quran::screens.family.nav.kids'));
    Livewire::test(FamilyHifz::class)->assertOk()->assertSee(__('quran::screens.family.kids.journey'))->assertSee('★')
        ->assertDontSee(__('quran::screens.family.progress', ['n' => 40]));
});

it('puts the Quran on "Start here" only while the plan has it, done with a Quran subject and a program', function () {
    $this->actingAs($this->admin);
    $keys = fn () => array_map(fn (SetupStep $s) => $s->key(), app(SetupChecklist::class)->steps());
    expect($keys())->toContain('quran.programs');
    $step = app(QuranStep::class);
    expect($step->required())->toBeTrue()->and($step->done())->toBeTrue()->and($step->url())->toContain('/admin/quran/programs');
    app(SaveQuranProgram::class)->archive($this->program);
    expect(app(QuranStep::class)->done())->toBeFalse();

    $this->clearTenant();
    $plan = SaasPlan::query()->create(['code' => 'no-quran', 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    $this->actAsTenant($this->academy);
    expect($keys())->not->toContain('quran.programs');
});

it('opens the Quran\'s messages on their screens', function () {
    screensPlan($this);
    screensRecord($this);
    screensRecord($this, 1);
    $exam = QuranExam::query()->where('stop_kind', 'surah')->where('stop_number', 1)->sole();
    $links = app(NotificationLinks::class);
    $this->actingAs($this->admin);
    expect($links->resolve(['screen' => 'quran_exam', 'id' => $exam->id], 'academy'))->toContain('/admin/quran/exams');
    CarbonImmutable::setTestNow('2026-10-07 08:00 UTC');
    app(BookQuranExam::class)->handle($exam, $this->supervisor->id, CarbonImmutable::parse('2026-10-08 16:00', 'Africa/Cairo'), 30, $this->admin->id);
    $this->actingAs($this->supervisor);
    expect($links->resolve(['screen' => 'quran_exam', 'id' => $exam->id], 'academy'))->toContain("/admin/quran/exams/{$exam->id}/result");
    $this->actingAs($this->guardian);
    expect($links->resolve(['screen' => 'quran_exam', 'id' => $exam->id], 'family'))->toContain('/my/hifz');
});

it('lists in the console the academies whose plan has the Quran, and leaves the fetch to owners', function () {
    $this->clearTenant();
    $admin = PlatformAdmin::query()->create(['name' => 'Huda', 'email' => 'huda@company.example', 'password' => 'a-long-password']);
    app()->setLocale('en');
    Filament::setCurrentPanel('console');
    $this->actingAs($admin, 'platform');
    $page = Livewire::test(QuranConsole::class)->assertOk()->assertSee('Academies with the Quran')->assertSee(__('platform::console.quran.owners'));
    expect(collect($page->instance()->academies['on'])->firstWhere('id', $this->academy->id))->toMatchArray(['how' => 'no_plan']);

    $plan = SaasPlan::query()->create(['code' => 'no-quran', 'name_ar' => 'باقة', 'name_en' => 'Basic', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    $page = Livewire::test(QuranConsole::class);
    expect(collect($page->instance()->academies['on'])->pluck('id'))->not->toContain($this->academy->id)
        ->and($page->instance()->academies['off'])->toBeGreaterThanOrEqual(1);
});
