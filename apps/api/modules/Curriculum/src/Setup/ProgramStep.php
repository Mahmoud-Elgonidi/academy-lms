<?php

namespace Modules\Curriculum\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Curriculum\Models\Program;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Setup\ConditionalSetupStep;
use Modules\SharedKernel\Setup\SetupGroup;

/** Optional: a curriculum with a published version, so courses follow it and students see their journey. */
final class ProgramStep implements ConditionalSetupStep
{
    /** Only while the academy has the curriculum (an optional area it may hide). */
    public function applies(): bool
    {
        return app(AcademyPlan::class)->has(Feature::Curriculum);
    }

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
