<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Models\Notification;
use Modules\Identity\Actions\Contact\ConfirmContactChange;
use Modules\Identity\Actions\Contact\RequestContactChange;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\Actions\AnswerCorrection;
use Modules\Privacy\Actions\CancelDataRequest;
use Modules\Privacy\Actions\CorrectDetails;
use Modules\Privacy\Actions\RequestCorrection;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Queries\EditableDetails;
use Modules\SharedKernel\Audit\AuditActor;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Putting wrong data right without waiting on the academy (docs/architecture/03,
 * section 11): a new email or number of one's own after a code sent to it, a
 * name and a student's details corrected by the person or their guardian, and a
 * correction request for everything else, answered by the academy within 30
 * days. The fixture: an adult student and their guardian, and the guardian's
 * child (8 years old, the kids' look).
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->sender = new class implements OtpSender
    {
        /** @var list<OtpMessage> */
        public array $sent = [];

        public function supports(string $channel): bool
        {
            return true;
        }

        public function send(OtpMessage $message): void
        {
            $this->sent[] = $message;
        }
    };
    app()->instance(OtpSender::class, $this->sender);

    $this->enrolStudentOnPlan();
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hala']);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->guardian->forceFill(['email' => 'mona@noor.test', 'phone_e164' => '+966500000010', 'timezone' => 'Asia/Riyadh'])->save();
    $this->child = User::factory()->withRole(Role::Student)->create(['name' => 'Salma', 'email' => null, 'phone_e164' => null, 'username' => 'salma.kid']);
    app(PeopleApi::class)->saveStudentProfile($this->child->id, CarbonImmutable::now()->subYears(8), null);
    app(PeopleApi::class)->linkGuardian($this->guardian->id, $this->child->id);
});

afterEach(function () {
    AuditActor::forget();
});

it('makes a new email the guardian\'s only after the code sent to it comes back, and tells them on every channel', function () {
    $this->actingAs($this->guardian);
    $sent = app(RequestContactChange::class)->handle($this->guardian, 'email', '  Mona.New@Example.test ');

    // The code goes to the new address; nothing changes until it comes back.
    $message = end($this->sender->sent);
    expect($message->destination)->toBe('mona.new@example.test')
        ->and($message->channel)->toBe('email')
        ->and($sent->sentTo)->toBe('m•••@example.test')
        ->and($this->guardian->refresh()->email)->toBe('mona@noor.test');

    expect(fn () => app(ConfirmContactChange::class)->handle($this->guardian, $sent->challengeId, $message->code === '000000' ? '111111' : '000000'))
        ->toThrow(ValidationException::class);
    app(ConfirmContactChange::class)->handle($this->guardian, $sent->challengeId, $message->code);

    expect($this->guardian->refresh()->email)->toBe('mona.new@example.test');
    // The audit says which way in changed and that the person did it, never the address.
    $audit = DB::table('audit_logs')->where('action', 'identity.contact_changed')->where('subject_id', $this->guardian->id)->sole();
    $data = json_decode($audit->data, true);
    expect($data)->toMatchArray(['email' => true, 'phone' => false, 'self' => true])
        ->and($audit->data)->not->toContain('example.test');
    // They hear it at once, even in their quiet hours, in case it was not them.
    expect(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'privacy.contact_changed')->exists())->toBeTrue();

    // The code is spent.
    expect(fn () => app(ConfirmContactChange::class)->handle($this->guardian, $sent->challengeId, $message->code))->toThrow(ValidationException::class);
});

it('takes a local mobile number in the person\'s own country, and refuses one already theirs or another account\'s', function () {
    $this->actingAs($this->guardian);
    $sent = app(RequestContactChange::class)->handle($this->guardian, 'phone', '050 123 4567');
    $message = end($this->sender->sent);
    expect($message->destination)->toBe('+966501234567');
    app(ConfirmContactChange::class)->handle($this->guardian, $sent->challengeId, $message->code);
    expect($this->guardian->refresh()->phone_e164)->toBe('+966501234567');

    $errors = fn (string $kind, string $value) => rescue(fn () => app(RequestContactChange::class)->handle($this->guardian, $kind, $value), fn (ValidationException $e) => array_key_first($e->errors()), false);
    expect($errors('phone', '+966501234567'))->toBe('value')
        ->and(rescue(fn () => app(RequestContactChange::class)->handle($this->guardian, 'email', $this->owner->email), fn (ValidationException $e) => $e->errors()['value'][0], false))
        ->toBe(__('identity::settings.contact.errors.taken'))
        ->and($errors('email', 'not-an-email'))->toBe('value')
        ->and($errors('fax', '123'))->toBe('kind');
});

it('does not change a way in while the platform team is signed in as the person, nor for a child the guardian runs', function () {
    AuditActor::use(new AuditActor('platform', null, 'Support', $this->guardian->id));
    expect(fn () => app(RequestContactChange::class)->handle($this->guardian, 'email', 'other@example.test'))->toThrow(ValidationException::class);
    AuditActor::forget();

    $this->child->forceFill(['is_managed_minor' => true, 'locale' => 'ar'])->save();
    expect(fn () => app(RequestContactChange::class)->handle($this->child, 'email', 'salma@example.test'))
        ->toThrow(ValidationException::class, __('identity::settings.contact.errors.managed'));
    expect($this->sender->sent)->toBe([]);
});

it('lets a guardian correct their child\'s name and school details; the audit names the fields, not the values', function () {
    $this->actingAs($this->guardian);
    $changed = app(CorrectDetails::class)->handle($this->child->id, $this->guardian->id, 'family', [
        'name' => '  Salma Rami ', 'school_grade' => 'g3', 'teacher_gender' => 'female_only', 'country' => 'sa',
        'email' => 'sneaky@example.test',
    ]);

    expect($changed)->toEqualCanonicalizing(['name', 'school_grade', 'teacher_gender', 'country'])
        ->and($this->child->refresh()->name)->toBe('Salma Rami')
        ->and($this->child->email)->toBeNull();
    $details = app(PeopleApi::class)->studentDetails($this->child->id);
    expect($details->schoolGrade)->toBe('g3')->and($details->teacherGender)->toBe('female_only')->and($details->country)->toBe('SA');

    $audit = DB::table('audit_logs')->where('action', 'privacy.details_corrected')->sole();
    expect(json_decode($audit->data, true)['fields'])->toEqualCanonicalizing(['name', 'school_grade', 'teacher_gender', 'country'])
        ->and($audit->data)->not->toContain('Salma Rami');

    // "Either" again: a preference can be cleared.
    app(CorrectDetails::class)->handle($this->child->id, $this->guardian->id, 'family', ['teacher_gender' => null]);
    expect(app(PeopleApi::class)->studentDetails($this->child->id)->teacherGender)->toBeNull();

    // A wrong value is People's to refuse.
    expect(fn () => app(CorrectDetails::class)->handle($this->child->id, $this->guardian->id, 'family', ['school_grade' => 'g99']))->toThrow(ValidationException::class);
});

it('keeps a teacher to their name, a child to their guardian, and a family to its own children', function () {
    $this->actingAs($this->teacher);
    expect(app(CorrectDetails::class)->handle($this->teacher->id, $this->teacher->id, 'teacher', ['name' => 'Nour H', 'gender' => 'male']))->toBe(['name'])
        ->and(app(EditableDetails::class)->forViewer($this->teacher->id, 'Nour H', 'teacher')[0]['fields'])->toBe(['name']);

    // The child (the kids' look) asks their guardian; another family cannot touch them.
    $this->actingAs($this->child);
    expect(fn () => app(CorrectDetails::class)->handle($this->child->id, $this->child->id, 'family', ['name' => 'Sally']))->toThrow(AuthorizationException::class);
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $this->actingAs($stranger);
    expect(fn () => app(CorrectDetails::class)->handle($this->child->id, $stranger->id, 'family', ['name' => 'Sally']))->toThrow(AuthorizationException::class);

    // A guardian sees themselves (their name) and each child (name and school details).
    $rows = app(EditableDetails::class)->forViewer($this->guardian->id, $this->guardian->name, 'family');
    $byId = collect($rows)->keyBy('id');
    expect($byId[$this->guardian->id]['fields'])->toBe(['name'])
        ->and($byId[$this->child->id]['fields'])->toBe(['name', 'gender', 'country', 'school_grade', 'teacher_gender'])
        ->and($byId[$this->student->id]['student'])->toBeTrue();
});

it('takes a correction request in the person\'s words, and the academy answers it within 30 days', function () {
    $this->actingAs($this->guardian);
    $request = app(RequestCorrection::class)->handle($this->child->id, $this->guardian->id, 'family', 'تاريخ ميلاد سلمى غلط: هي مولودة في 2017.');
    expect($request->kind)->toBe(DataRequest::CORRECTION)
        ->and($request->status)->toBe('pending')
        ->and((int) round(now()->diffInDays($request->due_at)))->toBe(30)
        ->and(Notification::query()->where('user_id', $this->owner->id)->where('template_key', 'privacy.correction_requested')->exists())->toBeTrue();

    // One waits at a time; too little said is not a request; the words stay out of the audit.
    expect(fn () => app(RequestCorrection::class)->handle($this->child->id, $this->guardian->id, 'family', 'وكمان الاسم غلط شوية'))->toThrow(ValidationException::class)
        ->and(fn () => app(RequestCorrection::class)->handle($this->guardian->id, $this->guardian->id, 'family', 'غلط'))->toThrow(ValidationException::class)
        ->and(DB::table('audit_logs')->where('action', 'privacy.correction_requested')->value('data'))->not->toContain('2017');

    // Only the academy answers.
    expect(fn () => app(AnswerCorrection::class)->done($request, $this->guardian->id))->toThrow(AuthorizationException::class);

    // Taken back and asked again, then corrected with a note whoever asked reads.
    app(CancelDataRequest::class)->handle($request, $this->guardian->id);
    $again = app(RequestCorrection::class)->handle($this->child->id, $this->guardian->id, 'family', 'تاريخ ميلاد سلمى غلط، وكمان الصف: هي في تالتة.');
    $this->actingAs($this->owner);
    expect(fn () => app(AnswerCorrection::class)->refuse($again, $this->owner->id, '   '))->toThrow(ValidationException::class);
    $done = app(AnswerCorrection::class)->done($again, $this->owner->id, 'صلحنا تاريخ الميلاد والصف.');
    expect($done->status)->toBe('done')->and($done->statusLabel())->toBe(__('privacy::requests.correction_statuses.done'))
        ->and(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'privacy.correction_done')->exists())->toBeTrue()
        ->and(fn () => app(AnswerCorrection::class)->refuse($done, $this->owner->id, 'late'))->toThrow(ValidationException::class);

    // A refusal carries the academy's reason.
    $this->actingAs($this->student);
    $third = app(RequestCorrection::class)->handle($this->student->id, $this->student->id, 'family', 'اسمي في الفاتورة القديمة مكتوب غلط.');
    $this->actingAs($this->owner);
    app(AnswerCorrection::class)->refuse($third, $this->owner->id, 'الفاتورة اللي صدرت مابتتعدلش، والجاية هتطلع بالاسم الصح.');
    expect(Notification::query()->where('user_id', $this->student->id)->where('template_key', 'privacy.correction_refused')->exists())->toBeTrue();
});

it('gives the apps and the portal the same: a new email by code, details put right, and a correction request', function () {
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Asia/Riyadh']);

    // A new email: the code goes to it, and the account answers with it once the code comes back.
    $api($this->guardian)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.can_change_contact', true);
    $sent = $api($this->guardian)->postJson('/api/v1/me/contact', ['kind' => 'email', 'value' => 'mona.app@example.test'])
        ->assertStatus(202)->assertJsonPath('channel', 'email')->json();
    $api($this->guardian)->postJson('/api/v1/me/contact', ['kind' => 'email', 'value' => 'not an email'])->assertUnprocessable()->assertJsonValidationErrors('value');
    $api($this->guardian)->postJson('/api/v1/me/contact/confirm', ['challenge_id' => $sent['challenge_id'], 'code' => end($this->sender->sent)->code])
        ->assertOk()->assertJsonPath('data.email', 'mona.app@example.test');
    // A child run by the guardian changes nothing of the sort.
    $this->child->forceFill(['is_managed_minor' => true, 'locale' => 'ar'])->save();
    $api($this->child)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.can_change_contact', false);

    // My data: what the guardian may put right, for whom, and the choices.
    $data = $api($this->guardian)->getJson('/api/v1/me/data-requests')->assertOk()->json('data');
    $salma = collect($data['subjects'])->firstWhere('id', $this->child->id);
    expect(collect($data['subjects'])->firstWhere('self', true)['details'])->toHaveCount(1)
        ->and(collect($salma['details'])->pluck('key')->all())->toBe(['name', 'gender', 'country', 'school_grade', 'teacher_gender'])
        ->and(collect($salma['details'])->firstWhere('key', 'teacher_gender')['shown'])->toBeString()
        ->and(collect($data['choices']['country'])->pluck('value'))->toContain('SA')
        ->and($salma['correction'])->toBeNull();

    $api($this->guardian)->patchJson("/api/v1/me/details/{$this->child->id}", ['country' => 'SA', 'school_grade' => 'g3', 'phone' => '+1'])
        ->assertOk()->assertJsonPath('data.changed', ['country', 'school_grade'])
        ->assertJson(fn ($json) => $json->where('data.details.2.value', 'SA')->etc());
    $api($this->guardian)->patchJson("/api/v1/me/details/{$this->child->id}", ['country' => 'XX'])->assertUnprocessable();
    // Someone else's child, or a teacher's student: not theirs.
    $api($this->teacher)->patchJson("/api/v1/me/details/{$this->child->id}", ['name' => 'Someone'])->assertForbidden();

    // The rest is a correction the academy makes; one open at a time, and it can be taken back while it waits.
    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'fix-1'])->postJson('/api/v1/me/data-requests', ['kind' => 'correction', 'subject_id' => $this->child->id])
        ->assertUnprocessable()->assertJsonValidationErrors('says');
    $made = $api($this->guardian)->withHeaders(['Idempotency-Key' => 'fix-2'])
        ->postJson('/api/v1/me/data-requests', ['kind' => 'correction', 'subject_id' => $this->child->id, 'says' => 'Her birth year is 2018, not 2017.'])
        ->assertCreated()->assertJsonPath('data.kind.key', 'correction')->assertJsonPath('data.status.key', 'pending')
        ->assertJsonPath('data.can_cancel', true)->json('data.id');
    expect(collect($api($this->guardian)->getJson('/api/v1/me/data-requests')->json('data.subjects'))->firstWhere('id', $this->child->id)['correction']['id'])->toBe($made);
    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'fix-3'])
        ->postJson('/api/v1/me/data-requests', ['kind' => 'correction', 'subject_id' => $this->child->id, 'says' => 'And her grade is wrong too.'])
        ->assertUnprocessable();
    $api($this->guardian)->postJson("/api/v1/me/data-requests/{$made}/cancel")->assertOk()->assertJsonPath('data.status.key', 'cancelled')
        ->assertJsonPath('data.can_cancel', false);
});
