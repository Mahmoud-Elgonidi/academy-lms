<?php

use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Import\Importers\FamiliesImporter;
use Modules\Import\Support\ImportContext;
use Modules\People\Models\GuardianStudent;

/*
 * Importing into an academy that trains adults only (docs/architecture/02,
 * section 29): trainees alone, each with their own email or number, no
 * guardian columns; a families academy keeps its students and guardians.
 */

beforeEach(function () {
    app()->setLocale('ar');
    $this->families = $this->createAcademy(['slug' => 'noor']);
    $this->adults = $this->actAsTenant($this->createAcademy(['slug' => 'hm-trust', 'audience' => 'adults']));
    $this->owner = User::factory()->withRole(Role::Owner)->create();
});

it('imports trainees who sign in themselves, and no guardians', function () {
    $context = new ImportContext('20', 'Africa/Cairo', 'ar', $this->owner->id);
    $importer = app(FamiliesImporter::class);
    $keys = array_map(fn ($f) => $f->key, $importer->fields());
    expect($keys)->not->toContain('guardian_name')->not->toContain('guardian_phone')->not->toContain('relationship')
        ->and(collect($importer->fields())->firstWhere('key', 'student_email')->matches('Email'))->toBeTrue()
        ->and(__('import::import.kinds.families.title'))->toBe('المتدرّبون');
    $row = array_fill_keys($keys, '');

    expect(fn () => $importer->import(['student_name' => 'Karim'] + $row, $context))
        ->toThrow(ValidationException::class, __('import::rows.errors.family_contact'));
    $outcome = $importer->import(['student_name' => 'Karim', 'student_email' => 'karim@example.com', 'gender' => 'ذكر'] + $row, $context);
    expect($outcome->status)->toBe('created')->and($outcome->notes[0])->toBe('متدرّب يدخل بنفسه')
        ->and(User::query()->findOrFail($outcome->refs['student'])->is_managed_minor)->toBeFalse()
        ->and(GuardianStudent::query()->count())->toBe(0);

    // A families academy: the guardian's columns, and a child under them.
    $this->actAsTenant($this->families);
    $owner = User::factory()->withRole(Role::Owner)->create();
    $importer = app(FamiliesImporter::class);
    $keys = array_map(fn ($f) => $f->key, $importer->fields());
    expect($keys)->toContain('guardian_phone');
    $outcome = $importer->import(['student_name' => 'Salma', 'guardian_name' => 'Amr', 'guardian_phone' => '01033334444'] + array_fill_keys($keys, ''), new ImportContext('20', 'Africa/Cairo', 'ar', $owner->id));
    expect(User::query()->findOrFail($outcome->refs['student'])->is_managed_minor)->toBeTrue();
});
