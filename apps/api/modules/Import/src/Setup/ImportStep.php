<?php

namespace Modules\Import\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Import\Models\ImportRun;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** Optional: the teachers and families the academy already has, brought in from a sheet (one import finished). */
final class ImportStep implements SetupStep
{
    public function key(): string
    {
        return 'import.people';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 50;
    }

    public function required(): bool
    {
        return false;
    }

    public function title(): string
    {
        return __('import::setup.people.title');
    }

    public function hint(): string
    {
        return __('import::setup.people.hint');
    }

    public function done(): bool
    {
        return ImportRun::query()->where('status', 'done')->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ImportData->value) ? route('filament.academy.pages.settings.import') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ImportData;
    }
}
