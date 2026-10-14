<?php

namespace Modules\Curriculum\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Curriculum\Models\Program;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/** Optional: a curriculum with a published version, so courses follow it and students see their journey. */
final class ProgramStep implements SetupStep
{
    public function key(): string
    {
        return 'curriculum.program';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 40;
    }

    public function required(): bool
    {
        return false;
    }

    public function title(): string
    {
        return __('curriculum::setup.program.title');
    }

    public function hint(): string
    {
        return __('curriculum::setup.program.hint');
    }

    public function done(): bool
    {
        return Program::query()->where('status', 'active')->whereHas('versions', fn ($q) => $q->where('status', 'published'))->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageCurriculum->value) ? route('filament.academy.resources.curricula.index') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageCurriculum;
    }
}
