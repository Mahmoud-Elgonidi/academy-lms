<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Classroom\Filament\Pages\ClassroomSettingsPage;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Checks\AddTeacherCheck;
use Modules\People\Actions\Checks\DecideTeacherCheck;
use Modules\People\Actions\Checks\RemindTeacherChecks;
use Modules\People\Actions\Checks\SaveMinorCheckRule;
use Modules\People\Contracts\DTOs\TeacherClearance;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Filament\Pages\TeacherChecks;
use Modules\People\Filament\Teacher\MyDocuments;
use Modules\People\Models\TeacherCheck;
use Modules\People\Privacy\ProfileData;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\SubstituteTeacher;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\SubstituteCandidates;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Teachers' vetting (docs/architecture/02, section 15). The fixture: an adult
 * London student taught by a Cairo teacher on Tuesdays and Saturdays; a second
 * (Riyadh) teacher of the same subject; an admin, who is a safeguarding lead.
 * Here a ten-year-old with a guardian joins them.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    Storage::fake('local');
    $this->child = User::factory()->withRole(Role::Student)->create(['name' => 'Yousef', 'timezone' => 'Africa/Cairo']);
    $parent = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->saveStudentProfile($this->child->id, CarbonImmutable::parse('2016-03-01'), null);
    app(PeopleApi::class)->linkGuardian($parent->id, $this->child->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function scan(): UploadedFile
{
    return UploadedFile::fake()->create('fish.pdf', 120, 'application/pdf');
}

function sendCheck(string $teacherId, string $kind = 'criminal_record', ?string $expires = null): TeacherCheck
{
    return app(AddTeacherCheck::class)->handle($teacherId, $kind, 'EG-2026-0042', CarbonImmutable::parse('2026-09-20'), $expires ? CarbonImmutable::parse($expires) : null, scan(), $teacherId);
}

function clearance(string $teacherId): TeacherClearance
{
    return app(PeopleApi::class)->clearances([$teacherId])[$teacherId];
}

it('takes a teacher\'s check on the private disk, tells the leads, and keeps one waiting of a kind', function () {
    $check = sendCheck($this->teacher->id);

    expect($check->status)->toBe('pending')
        ->and($check->file_path)->toStartWith("academies/{$this->academy->id}/teacher-checks/{$this->teacher->id}/")
        ->and(Storage::disk('local')->exists($check->file_path))->toBeTrue()
        ->and(Notification::query()->where('user_id', $this->admin->id)->where('template_key', 'people.check_submitted')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'people.check_added')->count())->toBe(1);

    expect(fn () => sendCheck($this->teacher->id))->toThrow(ValidationException::class, __('people::checks.errors.one_waiting'));
    // A file that is not a scan or a PDF is refused.
    expect(fn () => app(AddTeacherCheck::class)->handle($this->teacher->id, 'identity', null, null, null, UploadedFile::fake()->create('x.html', 2, 'text/html'), $this->teacher->id))
        ->toThrow(ValidationException::class);
});

it('verifies a check until a day the academy sets, or turns it down with a reason the teacher reads', function () {
    $check = sendCheck($this->teacher->id);
    expect(fn () => app(DecideTeacherCheck::class)->handle($check->id, false, null, 'no', $this->admin->id))->toThrow(ValidationException::class);

    app(DecideTeacherCheck::class)->handle($check->id, true, CarbonImmutable::parse('2027-03-31'), null, $this->admin->id);
    expect($check->fresh())->status->toBe('verified')->expires_on->toDateString()->toBe('2027-03-31')
        ->and(Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'people.check_verified')->count())->toBe(1);

    // Found wrong later: turned down, with the reason.
    app(DecideTeacherCheck::class)->handle($check->id, false, null, 'The number does not match the authority\'s record.', $this->admin->id);
    expect($check->fresh()->status)->toBe('rejected')
        ->and(Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'people.check_rejected')->count())->toBe(1)
        ->and(fn () => app(DecideTeacherCheck::class)->handle($check->id, true, null, null, $this->admin->id))->toThrow(ValidationException::class);
    // The record of the check stays: the app cannot delete it.
    expect(fn () => DB::table('teacher_checks')->where('id', $check->id)->delete())->toThrow(QueryException::class);
});

it('clears a teacher for minors by the academy\'s rule, a valid check, and no pause', function () {
    expect(clearance($this->other->id))->cleared->toBeTrue()->state->toBe(TeacherClearance::NOT_REQUIRED);

    app(SaveMinorCheckRule::class)->handle(true);
    expect(clearance($this->other->id))->cleared->toBeFalse()->state->toBe(TeacherClearance::MISSING);

    $check = sendCheck($this->other->id);
    expect(clearance($this->other->id)->state)->toBe(TeacherClearance::PENDING);
    app(DecideTeacherCheck::class)->handle($check->id, true, CarbonImmutable::parse('2026-12-31'), null, $this->admin->id);
    expect(clearance($this->other->id))->cleared->toBeTrue()->state->toBe(TeacherClearance::VERIFIED)->expiresOn->toDateString()->toBe('2026-12-31');

    // Just after midnight on the academy's calendar (Cairo; still 31 December in UTC), it has run out.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-01 00:30', 'Africa/Cairo'));
    expect(clearance($this->other->id))->cleared->toBeFalse()->state->toBe(TeacherClearance::EXPIRED);
    CarbonImmutable::setTestNow('2026-10-05 07:00 UTC');

    // A pause for a concern keeps them from minors even when no check is asked for.
    app(SaveMinorCheckRule::class)->handle(false);
    app(PeopleApi::class)->pauseForMinors($this->other->id, 'Concern #3 is being looked into', $this->admin->id);
    expect(clearance($this->other->id))->cleared->toBeFalse()->state->toBe(TeacherClearance::PAUSED)
        ->and(DB::table('audit_logs')->where('action', 'people.minors_paused')->count())->toBe(1);
    app(PeopleApi::class)->resumeForMinors($this->other->id, 'Looked into: nothing found', $this->admin->id);
    expect(clearance($this->other->id)->cleared)->toBeTrue();
});

it('books, covers and moves lessons with a minor only to a cleared teacher, and adults as before', function () {
    app(SaveMinorCheckRule::class)->handle(true);
    $times = [CarbonImmutable::parse('2026-10-13 19:00', 'Asia/Riyadh')->getTimestamp()];

    expect(fn () => app(PlaceBookingHold::class)->handle($this->other->id, $this->child->id, $times, 30, $this->admin->id))
        ->toThrow(ValidationException::class, __('scheduling::policy.errors.not_cleared', ['state' => __('people::checks.clearance.missing')]));
    // An adult student: no check needed.
    expect(app(PlaceBookingHold::class)->handle($this->other->id, $this->student->id, $times, 30, $this->admin->id))->toBeString();

    // A lesson with a child: the uncleared teacher is not offered to cover it, and cannot be put on it.
    $lesson = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    DB::table('session_participants')->where('session_id', $lesson->id)->where('role', 'student')->update(['user_id' => $this->child->id]);
    expect(collect(app(SubstituteCandidates::class)->forSessions([$lesson])[$lesson->id])->pluck('id'))->not->toContain($this->other->id)
        ->and(fn () => app(SubstituteTeacher::class)->handle($lesson, $this->other->id, $this->admin->id))->toThrow(ValidationException::class);

    $check = sendCheck($this->other->id);
    app(DecideTeacherCheck::class)->handle($check->id, true, null, null, $this->admin->id);
    expect(collect(app(SubstituteCandidates::class)->forSessions([$lesson])[$lesson->id])->pluck('id'))->toContain($this->other->id);
});

it('reminds the teacher and the leads a month before a check runs out and after, once each, unless a newer one covers it', function () {
    CarbonImmutable::setTestNow('2026-10-05 07:00 UTC');
    $check = sendCheck($this->teacher->id);
    app(DecideTeacherCheck::class)->handle($check->id, true, CarbonImmutable::parse('2026-10-30'), null, $this->admin->id);
    $soon = fn () => Notification::query()->where('template_key', 'people.check_expiring')->count();
    $gone = fn () => Notification::query()->where('template_key', 'people.check_expired')->count();

    expect(app(RemindTeacherChecks::class)->handle())->toBe(1)
        ->and($soon())->toBe(2)   // the teacher and the admin
        ->and(app(RemindTeacherChecks::class)->handle())->toBe(0);

    CarbonImmutable::setTestNow('2026-11-01 07:00 UTC');
    expect(app(RemindTeacherChecks::class)->handle())->toBe(1)->and($gone())->toBe(2);

    // A newer check of the same kind that lasts longer: nothing to say about the old one.
    $old = sendCheck($this->other->id);
    app(DecideTeacherCheck::class)->handle($old->id, true, CarbonImmutable::parse('2026-11-20'), null, $this->admin->id);
    $new = sendCheck($this->other->id);
    app(DecideTeacherCheck::class)->handle($new->id, true, CarbonImmutable::parse('2027-11-20'), null, $this->admin->id);
    expect(app(RemindTeacherChecks::class)->handle())->toBe(0);
});

it('puts a teacher\'s documents in their copy, and erases the scans but keeps the record', function () {
    $check = sendCheck($this->teacher->id);
    $part = app(ProfileData::class)->export($this->teacher->id);
    expect($part->data['teacher_checks'][0])->kind->toBe('criminal_record')->status->toBe('pending')
        ->and($part->files)->toHaveKey($check->file_path);

    DB::transaction(fn () => app(ProfileData::class)->erase($this->teacher->id));
    expect($check->fresh())->file_path->toBeNull()->reference->toBeNull()->status->toBe('pending')
        ->and(Storage::disk('local')->exists((string) $check->getOriginal('file_path')))->toBeFalse();
});

it('shows the leads every teacher\'s standing and lets them verify, turn down, set the rule and pause from the screen; a teacher sees only their own', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $check = sendCheck($this->teacher->id);
    $other = $this->other;
    $theirs = sendCheck($other->id, 'identity');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(TeacherChecks::class)
        ->assertSee($this->teacher->name)->assertSee($other->name)->assertSee(__('people::checks.kinds.criminal_record'))
        ->callAction('rule', ['required' => true])
        ->callAction(TestAction::make('verify')->arguments(['id' => $check->id]), ['expires_on' => '2027-09-20'])
        ->callAction(TestAction::make('reject')->arguments(['id' => $theirs->id]), ['note' => 'The scan is not readable.'])
        ->callAction(TestAction::make('pause')->arguments(['id' => $other->id]), ['reason' => 'While a concern is looked into'])
        ->assertHasNoActionErrors();
    expect($check->fresh())->status->toBe('verified')->expires_on->toDateString()->toBe('2027-09-20')
        ->and($theirs->fresh())->status->toBe('rejected')
        ->and(clearance($this->teacher->id)->state)->toBe(TeacherClearance::VERIFIED)
        ->and(clearance($other->id)->state)->toBe(TeacherClearance::PAUSED);

    // The teacher reads their own, with the reason; never the pause, never another teacher's.
    $this->actingAs($other);
    Filament::setCurrentPanel('teach');
    Livewire::test(MyDocuments::class)->assertSee('The scan is not readable.')->assertDontSee('While a concern is looked into')->assertDontSee($this->teacher->name);
    expect(TeacherChecks::canAccess())->toBeFalse();

    // The scan itself: its teacher and the leads, inline and never sniffed; nobody else.
    $this->get('http://noor.lms.test/people/checks/'.$theirs->id.'/file')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('http://noor.lms.test/people/checks/'.$check->id.'/file')->assertNotFound();
    $this->actingAs($this->admin)->get('http://noor.lms.test/people/checks/'.$check->id.'/file')->assertOk();
});

it('lets the academy have students come in with the camera off, for under-18s or everyone', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(ClassroomSettingsPage::class)->assertFormSet(['student_camera' => 'on'])
        ->fillForm(['student_camera' => 'off_for_minors'])->call('save')->assertHasNoFormErrors();
    expect(ClassroomSettings::current()->student_camera)->toBe('off_for_minors');
});

it('lets a teacher send and follow their documents from the apps, and open only their own', function () {
    app(SaveMinorCheckRule::class)->handle(true);
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en']);
    $api($this->teacher)->getJson('/api/v1/teacher/checks')->assertOk()
        ->assertJsonPath('data.required', true)->assertJsonPath('data.state', 'missing')->assertJsonPath('data.checks', []);

    $made = $api($this->teacher)->withHeaders(['Idempotency-Key' => 'check-1'])->post('/api/v1/teacher/checks', [
        'kind' => 'criminal_record', 'reference' => 'EG-2026-0042', 'issued_on' => '2026-09-20', 'file' => scan(),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status.key', 'pending')->assertJsonPath('data.kind.key', 'criminal_record');
    $api($this->teacher)->get($made->json('data.file.url'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $api($this->other)->get($made->json('data.file.url'))->assertNotFound();
    $api($this->teacher)->withHeaders(['Idempotency-Key' => 'check-2'])->post('/api/v1/teacher/checks', ['kind' => 'criminal_record', 'file' => scan()], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    // Paused by the academy: the teacher still reads where their documents stand, never the pause.
    $this->actAsTenant($this->academy);
    app(DecideTeacherCheck::class)->handle($made->json('data.id'), true, CarbonImmutable::parse('2027-09-20'), null, $this->admin->id);
    app(PeopleApi::class)->pauseForMinors($this->teacher->id, 'While a concern is looked into', $this->admin->id);
    $api($this->teacher)->getJson('/api/v1/teacher/checks')->assertOk()->assertJsonPath('data.state', 'verified')->assertJsonPath('data.valid_until', '2027-09-20')
        ->assertDontSee('While a concern is looked into');
    $api($this->student)->getJson('/api/v1/teacher/checks')->assertForbidden();
});
