<?php

namespace Modules\People\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\People\Queries\Models\Person;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** At least one active teacher (People › Teachers); their weekly hours are Scheduling's step. */
final class TeacherStep implements SetupStep
{
    public function key(): string
    {
        return 'people.teacher';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 20;
    }

    public function required(): bool
    {
        return true;
    }

    public function title(): string
    {
        return __('people::setup.teacher.title');
    }

    public function hint(): string
    {
        return __('people::setup.teacher.hint');
    }

    public function done(): bool
    {
        return Person::query()->withRole('teacher')->where('status', 'active')->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageTeachers->value) ? route('filament.academy.resources.people.teachers.index') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageTeachers;
    }
}
