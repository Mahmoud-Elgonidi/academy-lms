<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Models\Lead;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Models\Academy;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The CRM of an academy that trains adults only (docs/architecture/02,
 * section 29): every lead is the learner themself. A lead for a child is
 * refused kindly, from the form and before any account is made, and the
 * learner gets their own account with their own number; a families
 * academy still takes a lead for a child under its parent.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->sales = User::factory()->withRole(Role::Sales)->create(['name' => 'Salma Sales']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function adultsLead(array $over = []): Lead
{
    return app(CreateLead::class)->handle($over + [
        'contact_name' => 'Eng. Tarek', 'phone_e164' => '+966501234567', 'country' => 'SA', 'timezone' => 'Asia/Riyadh',
        'for_child' => false, 'subject_id' => test()->quran, 'source' => 'whatsapp',
    ], test()->sales->id);
}

function trainAdults(): void
{
    Academy::query()->whereKey(test()->academy->id)->update(['audience' => 'adults']);
    test()->academy = Academy::query()->findOrFail(test()->academy->id)->toTenantRef();
    test()->actAsTenant(test()->academy);
}

it('takes a lead for a child in a families academy, as before', function () {
    $lead = adultsLead(['for_child' => true, 'learner_name' => 'Yousef', 'learner_birth_year' => 2017]);
    $learner = app(PrepareLearner::class)->handle($lead, test()->sales->id);
    expect(DB::table('guardian_student')->where('student_id', $learner)->exists())->toBeTrue();
});

it('refuses a lead for a child in an adults academy, and makes the learner their own account', function () {
    trainAdults();

    try {
        adultsLead(['for_child' => true, 'learner_name' => 'Yousef', 'learner_birth_year' => 2017]);
        $this->fail('A lead for a child was taken.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('for_child')
            ->and($e->errors()['for_child'][0])->toBe(__('crm::leads.errors.adults_only'))
            ->and($e->errors()['for_child'][0])->not->toContain('طفل');
    }

    // One that came in before the academy changed is refused before any account is made.
    $old = adultsLead();
    $old->forceFill(['for_child' => true, 'learner_name' => 'Yousef'])->save();
    $users = DB::table('users')->count();
    expect(fn () => app(PrepareLearner::class)->handle($old->refresh(), test()->sales->id))->toThrow(ValidationException::class)
        ->and(DB::table('users')->count())->toBe($users);

    $lead = adultsLead(['phone_e164' => '+966501234568']);
    $learner = app(PrepareLearner::class)->handle($lead, test()->sales->id);
    expect(DB::table('users')->where('id', $learner)->value('phone_e164'))->toBe('+966501234568')
        ->and(DB::table('guardian_student')->where('student_id', $learner)->exists())->toBeFalse()
        ->and($lead->refresh()->contact_id)->toBe($learner);
});

it('speaks of a session and a trainer on the CRM\'s screens of an adults academy', function () {
    trainAdults();
    $fields = __('crm::leads.fields', [], 'ar');
    expect(json_encode($fields, JSON_UNESCAPED_UNICODE))->not->toContain('طفل')->not->toContain('ولي');
});
