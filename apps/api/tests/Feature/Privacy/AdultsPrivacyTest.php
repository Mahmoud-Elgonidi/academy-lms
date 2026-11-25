<?php

use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Privacy\Queries\EditableDetails;

/*
 * Privacy in an academy that trains adults only (docs/architecture/02,
 * section 29): its public pages speak to the trainee about their own
 * account, with no children or guardians, and no school grade to correct.
 */

beforeEach(function () {
    $this->families = $this->createAcademy(['slug' => 'noor', 'name_en' => 'Noor']);
    $this->adults = $this->createAcademy(['slug' => 'hm-trust', 'name_en' => 'HM Trust', 'audience' => 'adults']);
});

it('shows an adults academy\'s privacy, terms and deletion pages in its own words', function () {
    $this->get('http://hm-trust.lms.test/privacy?lang=ar')->assertOk()
        ->assertSee('حسابك لك')->assertSee('قاعة الجلسة')->assertDontSee('ولي الأمر')->assertDontSee('الأطفال');
    $this->get('http://hm-trust.lms.test/terms?lang=en')->assertOk()
        ->assertSee('Sessions')->assertDontSee('guardian')->assertDontSee('child');
    $this->get('http://hm-trust.lms.test/account-deletion?lang=ar')->assertOk()->assertDontSee('ابنك');

    $this->get('http://noor.lms.test/privacy?lang=ar')->assertOk()->assertSee('الأطفال')->assertSee('ولي الأمر');
    $this->get('http://noor.lms.test/account-deletion?lang=en')->assertOk()->assertSee('your child');
});

it('offers no school grade to correct', function () {
    $this->actAsTenant($this->adults);
    $trainee = User::factory()->withRole(Role::Student)->create();
    expect(app(EditableDetails::class)->fields($trainee->id, 'family'))->toBe(['name', 'gender', 'country', 'teacher_gender']);

    $this->actAsTenant($this->families);
    $student = User::factory()->withRole(Role::Student)->create();
    expect(app(EditableDetails::class)->fields($student->id, 'family'))->toContain('school_grade');
});
