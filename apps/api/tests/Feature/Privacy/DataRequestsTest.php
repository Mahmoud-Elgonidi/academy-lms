<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\Actions\ApproveErasure;
use Modules\Privacy\Actions\CancelDataRequest;
use Modules\Privacy\Actions\RefuseErasure;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Actions\SavePrivacySettings;
use Modules\Privacy\Actions\SweepDataRequests;
use Modules\Privacy\Filament\Family\MyData as FamilyMyData;
use Modules\Privacy\Filament\Pages\DataRequests as DataRequestsPage;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Queries\DataRequests;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\SharedKernel\Audit\AuditActor;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * People's rights over their data (docs/architecture/03, section 11): a copy
 * of everything the academy holds about a person (a ZIP, a week, a signed
 * address), erasure asked by them or their guardian and answered by the
 * academy (refused with a reason, or approved and done a day later by
 * anonymising the account), the public privacy notice, terms and
 * account-deletion page. The fixture: an adult student enrolled with lessons
 * ahead and an invoice due (so erasing them is held back), their guardian,
 * and a child of that guardian with nothing running.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    Storage::fake('local');
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hala']);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->child = User::factory()->withRole(Role::Student)->create(['name' => 'Salma', 'email' => 'salma@noor.test', 'phone_e164' => '+966500000001']);
    app(PeopleApi::class)->saveStudentProfile($this->child->id, CarbonImmutable::parse('2016-02-01'), null);
    app(PeopleApi::class)->linkGuardian($this->guardian->id, $this->child->id);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function privacyAs(User $user): void
{
    test()->actingAs($user);
}

it('prepares a copy of a child\'s data for their guardian: a ZIP with every module\'s part and a README, kept a week', function () {
    privacyAs($this->guardian);
    $request = app(RequestDataExport::class)->handle($this->child->id, $this->guardian->id, 'family')->refresh();

    // The queue runs it at once in tests: ready, on the private disk under the academy's folder.
    expect($request->status)->toBe('ready')
        ->and($request->file_path)->toStartWith('academies/'.$this->academy->id.'/privacy/')
        ->and(now()->diffInDays($request->expires_at))->toBeGreaterThanOrEqual(6.9);
    Storage::disk('local')->assertExists($request->file_path);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($request->file_path));
    $json = json_decode((string) $zip->getFromName('personal-data.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();
    expect($json['person'])->toBe('Salma')
        ->and($json['parts'])->toHaveKeys(['account', 'profile'])
        ->and($json['parts']['account']['email'])->toBe('salma@noor.test')
        ->and($json['parts']['account'])->not->toHaveKey('password')
        ->and($json['parts']['profile']['guardians'][0]['name'] ?? null)->toBe($this->guardian->name)
        ->and($readme)->toContain('personal-data.json')->toContain('A copy of your data');

    // Whoever asked hears it is ready; a second copy waits a day (the academy can make one for them).
    expect(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'privacy.export_ready')->exists())->toBeTrue();
    expect(fn () => app(RequestDataExport::class)->handle($this->child->id, $this->guardian->id, 'family'))->toThrow(ValidationException::class);
    privacyAs($this->owner);
    expect(app(RequestDataExport::class)->handle($this->child->id, $this->owner->id, 'staff')->refresh()->status)->toBe('ready');
});

it('lets only the person, their guardian (in the classic look) or the academy ask', function () {
    // A child cannot ask for themselves: their guardian does.
    privacyAs($this->child);
    expect(fn () => app(RequestErasure::class)->handle($this->child->id, $this->child->id, 'family'))->toThrow(AuthorizationException::class);
    // Nobody asks about someone else's child, and a teacher only about themselves.
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    privacyAs($stranger);
    expect(fn () => app(RequestDataExport::class)->handle($this->child->id, $stranger->id, 'family'))->toThrow(AuthorizationException::class);
    privacyAs($this->teacher);
    expect(fn () => app(RequestDataExport::class)->handle($this->student->id, $this->teacher->id, 'teacher'))->toThrow(AuthorizationException::class);
    expect(app(RequestDataExport::class)->handle($this->teacher->id, $this->teacher->id, 'teacher')->subject_id)->toBe($this->teacher->id);
    // A supervisor does not handle these requests.
    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    privacyAs($supervisor);
    expect(fn () => app(RequestErasure::class)->handle($this->child->id, $supervisor->id, 'staff'))->toThrow(AuthorizationException::class);
});

it('holds an erasure back while lessons, a course or money are open, and while the academy would lose its last owner', function () {
    $preview = app(ErasurePreview::class);
    expect(implode(' ', $preview->blockers($this->student->id)))
        ->toContain('course')->toContain('lessons')
        ->and($preview->blockers($this->guardian->id))->not->toBeEmpty()
        ->and($preview->blockers($this->child->id))->toBe([])
        ->and($preview->blockers($this->owner->id))->toHaveCount(1);

    privacyAs($this->student);
    $asked = app(RequestErasure::class)->handle($this->student->id, $this->student->id, 'family', 'Moving on.');
    // Asked again while open, it is the same request.
    expect(app(RequestErasure::class)->handle($this->student->id, $this->student->id, 'family')->id)->toBe($asked->id)
        ->and($asked->due_at->toDateString())->toBe(now()->addDays(30)->toDateString());
    // The owner and admins hear of it, not the one who asked.
    expect(Notification::query()->where('template_key', 'privacy.erasure_requested')->pluck('user_id')->all())
        ->toContain($this->owner->id)->toContain($this->admin->id)->not->toContain($this->student->id);

    privacyAs($this->owner);
    expect(fn () => app(ApproveErasure::class)->handle($asked, $this->owner->id))->toThrow(ValidationException::class);
    expect($asked->refresh()->status)->toBe('pending');
});

it('refuses with the reason whoever asked reads, and an answer is required', function () {
    privacyAs($this->student);
    $asked = app(RequestErasure::class)->handle($this->student->id, $this->student->id, 'family');
    privacyAs($this->owner);
    expect(fn () => app(RefuseErasure::class)->handle($asked, $this->owner->id, '  '))->toThrow(ValidationException::class);
    $refused = app(RefuseErasure::class)->handle($asked, $this->owner->id, 'Your course is still running.');
    expect($refused->status)->toBe('refused')->and($refused->answer)->toBe('Your course is still running.');
    $message = Notification::query()->where('user_id', $this->student->id)->where('template_key', 'privacy.erasure_refused')->first();
    expect($message)->not->toBeNull()->and((string) $message->body)->toContain('Your course is still running.');
    // Refused, it is closed: a new request may be made.
    privacyAs($this->student);
    expect(app(RequestErasure::class)->handle($this->student->id, $this->student->id, 'family')->id)->not->toBe($asked->id);
});

it('erases a child\'s account a day after the academy approves: anonymised everywhere, signed out, and their guardian told', function () {
    // Things the child has: a message, a device, a token, a linked Google account.
    $this->child->createToken('phone');
    DB::table('push_devices')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'user_id' => $this->child->id, 'platform' => 'android', 'token' => 'push-token-1', 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('social_identities')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'user_id' => $this->child->id, 'provider' => 'google', 'subject' => 'g-123', 'email' => 'salma@gmail.test', 'via' => 'settings', 'linked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    privacyAs($this->guardian);
    $asked = app(RequestErasure::class)->handle($this->child->id, $this->guardian->id, 'family', 'She finished.');
    privacyAs($this->owner);
    $approved = app(ApproveErasure::class)->handle($asked, $this->owner->id);
    expect($approved->status)->toBe('approved')->and($approved->erase_after->equalTo(now()->addDay()))->toBeTrue();
    expect(Notification::query()->where('template_key', 'privacy.erasure_approved')->pluck('user_id')->all())
        ->toContain($this->guardian->id)->toContain($this->child->id);

    // Within the day, nothing happens.
    expect(app(SweepDataRequests::class)->handle()['erased'])->toBe(0);
    $this->travel(25)->hours();
    expect(app(SweepDataRequests::class)->handle()['erased'])->toBe(1);

    $gone = User::query()->find($this->child->id);
    expect($gone->status->value)->toBe('erased')
        ->and($gone->email)->toBeNull()->and($gone->phone_e164)->toBeNull()
        ->and($gone->name)->not->toBe('Salma')
        ->and($gone->username)->toStartWith('erased-')
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $this->child->id)->count())->toBe(0)
        ->and(DB::table('push_devices')->where('user_id', $this->child->id)->count())->toBe(0)
        ->and(DB::table('social_identities')->where('user_id', $this->child->id)->count())->toBe(0)
        ->and(DB::table('guardian_student')->where('student_id', $this->child->id)->count())->toBe(0)
        ->and(Notification::query()->where('user_id', $this->child->id)->count())->toBe(0);

    $done = $asked->refresh();
    expect($done->status)->toBe('done')->and($done->summary['removed'])->toHaveKey('account');
    // The audit log keeps counts, never the name or address.
    $audit = DB::table('audit_logs')->where('action', 'privacy.account_erased')->first();
    expect($audit)->not->toBeNull()->and((string) $audit->data)->not->toContain('Salma')->not->toContain('salma@noor.test');
    // The guardian who asked hears it is done; nobody can ask about the account again.
    expect(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'privacy.account_erased')->exists())->toBeTrue();
    privacyAs($this->owner);
    expect(fn () => app(RequestDataExport::class)->handle($this->child->id, $this->owner->id, 'staff'))->toThrow(ValidationException::class);
});

it('can be taken back in the day after approval, and goes back to the academy if something came up meanwhile', function () {
    $second = User::factory()->withRole(Role::Owner)->create(['name' => 'Second owner']);
    privacyAs($second);
    $asked = app(RequestErasure::class)->handle($second->id, $second->id, 'family');
    privacyAs($this->owner);
    app(ApproveErasure::class)->handle($asked, $this->owner->id);
    // Taken back by the person: nothing is erased.
    privacyAs($second);
    expect(app(CancelDataRequest::class)->handle($asked->refresh(), $second->id)->status)->toBe('cancelled');
    $this->travel(2)->days();
    expect(app(SweepDataRequests::class)->handle()['erased'])->toBe(0);

    // Asked again and approved; then the other owner leaves, so this one is the last: back to the academy.
    $again = app(RequestErasure::class)->handle($second->id, $second->id, 'family');
    privacyAs($this->owner);
    app(ApproveErasure::class)->handle($again, $this->owner->id);
    DB::table('users')->where('id', $this->owner->id)->update(['status' => 'inactive']);
    $this->travel(25)->hours();
    expect(app(SweepDataRequests::class)->handle()['erased'])->toBe(0);
    $held = $again->refresh();
    expect($held->status)->toBe('pending')->and($held->summary['held_back'])->toHaveCount(1)
        ->and(User::query()->find($second->id)->status->value)->toBe('active');
});

it('serves a copy only through its signed address while it is kept, then removes it', function () {
    privacyAs($this->guardian);
    $copy = app(RequestDataExport::class)->handle($this->child->id, $this->guardian->id, 'family')->refresh();
    $link = app(DataRequests::class)->link($copy);
    $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);

    $this->get('http://noor.lms.test'.$path)->assertOk()->assertHeader('content-type', 'application/zip');
    $this->get('http://noor.lms.test/privacy/copies/'.$copy->id)->assertForbidden();
    // Another academy's host does not open it.
    $this->createAcademy(['slug' => 'rawda']);
    expect($this->get('http://rawda.lms.test'.$path)->status())->toBeIn([403, 404]);

    // A request ends by forgetting its academy; the sweep runs in it.
    $this->actAsTenant($this->academy);
    $this->travel(8)->days();
    expect(app(SweepDataRequests::class)->handle()['expired'])->toBe(1);
    Storage::disk('local')->assertMissing($copy->file_path);
    expect($copy->refresh()->status)->toBe('expired');
    $fresh = URL::temporarySignedRoute('privacy.copy', now()->addHour(), ['request' => $copy->id], absolute: false);
    $this->get('http://noor.lms.test'.$fresh)->assertStatus(410);
});

it('gives the apps My data: whom one may ask about, the requests, and the academy\'s pages', function () {
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Asia/Riyadh']);

    $api($this->guardian)->getJson('/api/v1/me/data-requests')->assertOk()
        ->assertJsonPath('data.subjects.0.id', $this->guardian->id)->assertJsonPath('data.subjects.0.self', true)
        ->assertJsonPath('data.subjects.0.can_copy', true)
        ->assertJsonPath('data.answer_days', 30)
        ->assertJson(fn ($json) => $json->where('data.links.privacy', fn ($url) => str_ends_with($url, '/privacy'))->etc());
    $ids = collect($api($this->guardian)->getJson('/api/v1/me/data-requests')->json('data.subjects'))->pluck('id')->all();
    expect($ids)->toContain($this->child->id)->toContain($this->student->id);

    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'copy-1'])->postJson('/api/v1/me/data-requests', ['kind' => 'export'])
        ->assertCreated()->assertJsonPath('data.kind.key', 'export')->assertJsonPath('data.status.key', 'ready')
        ->assertJson(fn ($json) => $json->whereType('data.download_url', 'string')->etc());
    $made = $api($this->guardian)->withHeaders(['Idempotency-Key' => 'erase-1'])
        ->postJson('/api/v1/me/data-requests', ['kind' => 'erasure', 'subject_id' => $this->child->id, 'reason' => 'Done.'])
        ->assertCreated()->assertJsonPath('data.can_cancel', true)->json('data.id');
    // A stranger cannot take it back; whoever asked can.
    $api($this->teacher)->postJson("/api/v1/me/data-requests/{$made}/cancel")->assertNotFound();
    $api($this->guardian)->postJson("/api/v1/me/data-requests/{$made}/cancel")->assertOk()->assertJsonPath('data.status.key', 'cancelled');

    // A younger student is told their guardian asks; they cannot ask.
    $api($this->child)->getJson('/api/v1/me/data-requests')->assertOk()->assertJsonCount(0, 'data.subjects');
    $api($this->child)->postJson('/api/v1/me/data-requests', ['kind' => 'erasure'])->assertForbidden();
});

it('shows the academy\'s privacy notice, terms and account-deletion page on its host, and the platform\'s elsewhere', function () {
    privacyAs($this->owner);
    app(SavePrivacySettings::class)->handle(['legal_name' => 'Noor Learning LLC', 'contact_email' => 'privacy@noor.test', 'privacy_extra' => 'We use WhatsApp for lesson messages only.'], $this->owner->id);

    $this->get('http://noor.lms.test/privacy?lang=en')->assertOk()
        ->assertSee('Noor Learning LLC')->assertSee('privacy@noor.test')->assertSee('We use WhatsApp for lesson messages only.');
    $this->get('http://noor.lms.test/terms?lang=ar')->assertOk()->assertSee('شروط الاستخدام');
    $this->get('http://noor.lms.test/account-deletion?lang=en')->assertOk()->assertSee('Delete your account')->assertSee('What is kept, without your name');
    // The platform's own pages (the app stores' links) name no academy.
    $this->get('http://lms.test/privacy?lang=en')->assertOk()->assertSee(config('privacy.platform_name'))->assertDontSee('Noor Learning LLC');
    expect(DB::table('audit_logs')->where('action', 'privacy.settings_saved')->exists())->toBeTrue();
});

it('gives the owner the requests to answer, and adults in /my their data', function () {
    privacyAs($this->guardian);
    $asked = app(RequestErasure::class)->handle($this->child->id, $this->guardian->id, 'family');

    privacyAs($this->owner);
    Filament::setCurrentPanel('academy');
    Livewire::test(DataRequestsPage::class)->assertOk()->assertSee('Salma')
        ->callAction('approve', arguments: ['id' => $asked->id]);
    expect($asked->refresh()->status)->toBe('approved');

    $supervisor = User::factory()->withRole(Role::Supervisor)->create();
    privacyAs($supervisor);
    expect(DataRequestsPage::canAccess())->toBeFalse();

    Filament::setCurrentPanel('family');
    privacyAs($this->guardian);
    expect(FamilyMyData::canAccess())->toBeTrue();
    Livewire::test(FamilyMyData::class)->assertOk()->assertSee('Salma')->call('askCopy', $this->guardian->id);
    expect(DataRequest::query()->where('subject_id', $this->guardian->id)->where('kind', 'export')->exists())->toBeTrue();
    privacyAs($this->child);
    expect(FamilyMyData::canAccess())->toBeFalse();
});
