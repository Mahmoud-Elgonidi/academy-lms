<?php

namespace Modules\Catalog\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Catalog\Models\Subject;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** At least one subject: teachers, prices and courses all hang on one. */
final class SubjectsStep implements SetupStep
{
    public function key(): string
    {
        return 'catalog.subjects';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 10;
    }

    public function required(): bool
    {
        return true;
    }

    public function title(): string
    {
        return __('catalog::setup.subjects.title');
    }

    public function hint(): string
    {
        return __('catalog::setup.subjects.hint');
    }

    public function done(): bool
    {
        return Subject::query()->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageCatalog->value) ? route('filament.academy.resources.subjects.index') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageCatalog;
    }
}
