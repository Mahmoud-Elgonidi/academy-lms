<?php

use App\Filament\Family\FamilyHome;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Billing\Filament\Family\BalanceWidget;
use Modules\Billing\Filament\Family\FamilyBilling;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Filament\Family\FamilyHomework;
use Modules\Learning\Filament\Family\FamilyProgress;
use Modules\Learning\Filament\Family\FamilyReports;
use Modules\Learning\Filament\Family\LearningHomeWidget;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Filament\Family\LearnerSwitcher;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Filament\Family\FamilyLessons;
use Modules\Scheduling\Filament\Family\NextLessonsWidget;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * The family area (/my): a guardian follows each child, a student sees
 * their own; lessons, progress, reports, homework and money, all limited
 * to the learner on screen.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();                     // Mon 5 Oct 2026, 07:00 UTC; lessons Tue and Sat 15:00 UTC
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function asFamily(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('family');
}

it('lets students and guardians in, and nobody else', function () {
    $this->clearTenant();
    $as = function (User $user) {
        app('auth')->forgetGuards();
        $this->flushSession();

        return $this->actingAs($user);
    };
    foreach ([$this->guardian, $this->student] as $user) {
        $as($user)->get('http://noor.lms.test/my')->assertOk()->assertSee($this->student->name);
    }
    $as($this->teacher)->get('http://noor.lms.test/my')->assertForbidden();
    $as($this->admin)->get('http://noor.lms.test/my')->assertForbidden();
    $as($this->guardian)->get('http://noor.lms.test/admin')->assertForbidden();
    $as($this->guardian)->get('http://noor.lms.test/teach')->assertForbidden();
});

it('lets a guardian switch between their children, and only theirs', function () {
    $sister = User::factory()->withRole(Role::Student)->create(['name' => 'Huda']);
    app(PeopleApi::class)->linkGuardian($this->guardian->id, $sister->id, isPrimary: false);
    $stranger = User::factory()->withRole(Role::Student)->create();
    asFamily($this->guardian);

    expect(array_map(fn ($l) => $l->name, app(FamilyView::class)->learners($this->guardian->id)))->toBe([$this->student->name, 'Huda']);
    Livewire::test(LearnerSwitcher::class)->assertSee('Huda')->call('choose', $sister->id);
    expect(app(FamilyView::class)->current($this->guardian->id)->id)->toBe($sister->id);

    app(FamilyView::class)->choose($this->guardian->id, $stranger->id);
    expect(app(FamilyView::class)->current($this->guardian->id)->id)->toBe($sister->id);
    Livewire::test(FamilyLessons::class)->assertDontSee('15:00');   // Huda has no lessons
});

it('shows the learner\'s lessons on the viewer\'s clock, and cancels one under the policy, saying what it costs', function () {
    $this->guardian->update(['timezone' => 'Asia/Riyadh']);
    asFamily($this->guardian);

    Livewire::test(FamilyLessons::class)
        ->assertSee('18:00 – 18:30')                                              // 15:00 UTC in Riyadh
        ->assertActionExists('cancel')
        ->mountAction('cancel', ['session' => $this->lesson->id])
        ->assertMountedActionModalSee(__('scheduling::family.cancel.outcomes.refund', ['hours' => 12]))   // more than 12 hours ahead: back to the balance
        ->setActionData(['reason' => 'travel'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($this->lesson->decision)->toMatchArray(['by' => 'student', 'reason' => 'travel', 'credit' => 'refund']);
});

it('warns that a late cancellation counts, and refuses someone else\'s lesson', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 08:00', 'UTC'));   // 7 hours before
    asFamily($this->student);

    Livewire::test(FamilyLessons::class)->mountAction('cancel', ['session' => $this->lesson->id])
        ->assertMountedActionModalSee(__('scheduling::family.cancel.outcomes.late', ['hours' => 12]));

    $other = User::factory()->withRole(Role::Guardian)->create();
    asFamily($other);
    Livewire::test(FamilyLessons::class)->mountAction('cancel', ['session' => $this->lesson->id])->assertNotFound();
    expect($this->lesson->refresh()->status)->toBe(SessionStatus::Scheduled);
});

it('shows the roadmap, and the teacher\'s reports without their internal notes', function () {
    $program = $this->publishQuranProgram($this->quran);
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $program->id);
    SessionReport::query()->create([
        'session_id' => $this->lesson->id, 'enrollment_id' => $this->booked->enrollmentId, 'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id,
        'kind' => 'lesson', 'status' => 'submitted', 'session_starts_at' => $this->lesson->starts_at, 'due_at' => $this->lesson->ends_at->addHours(12),
        'submitted_at' => $this->lesson->ends_at, 'overall' => 4, 'engagement' => 5,
        'notes_for_student' => 'Great start with Al-Fatiha.', 'notes_internal' => 'Parents asked for a discount.',
    ]);
    asFamily($this->guardian);

    Livewire::test(FamilyProgress::class)->assertSee('Quran')->assertSee('0%')->assertSee('Lesson 1');
    Livewire::test(FamilyReports::class)->assertSee('Great start with Al-Fatiha.')->assertSee('★★★★')->assertDontSee('discount');
});

it('takes homework handed in as text or as a recording, for the learner\'s own homework only', function () {
    $homework = Homework::query()->create([
        'enrollment_id' => $this->booked->enrollmentId, 'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id,
        'title' => 'Recite Al-Fatiha', 'accepts' => 'any', 'due_at' => now()->addDays(2), 'status' => 'assigned',
    ]);
    asFamily($this->student);

    Livewire::test(FamilyHomework::class)
        ->assertSee('Recite Al-Fatiha')
        ->call('handIn', $homework->id)->assertHasErrors(['answers.'.$homework->id])   // nothing written, nothing recorded
        ->set('files.'.$homework->id, UploadedFile::fake()->create('recording.webm', 40, 'audio/webm'))
        ->call('handIn', $homework->id)->assertHasNoErrors()
        ->assertSee(__('learning::homework.statuses.submitted'));

    expect($homework->refresh()->status)->toBe('submitted')
        ->and($homework->submissions()->sole()->file_path)->not->toBeNull();

    $stranger = User::factory()->withRole(Role::Student)->create();
    asFamily($stranger);
    Livewire::test(FamilyHomework::class)->call('handIn', $homework->id)->assertNotFound();
});

it('shows the payer their invoices and what is owed, and the student only the lessons left', function () {
    asFamily($this->guardian);
    Livewire::test(FamilyBilling::class)
        ->assertSee(trans_choice('billing::family.sessions_left', 0, ['n' => 0]))    // nothing paid yet
        ->assertSee(__('billing::billing.statuses.issued'))
        ->assertSee(__('billing::family.owed'));

    asFamily($this->student);
    Livewire::test(FamilyBilling::class)->assertSee(__('billing::family.no_invoices'))->assertDontSee(__('billing::family.owed'));
});

it('puts the next lessons, the progress and the balance on the home page', function () {
    asFamily($this->guardian);

    Livewire::test(FamilyHome::class)->assertOk()
        ->assertSeeLivewire(NextLessonsWidget::class)
        ->assertSeeLivewire(LearningHomeWidget::class)
        ->assertSeeLivewire(BalanceWidget::class);
    Livewire::test(NextLessonsWidget::class)->assertSee('Quran')->assertSee(__('scheduling::family.tomorrow'));
});
