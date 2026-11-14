<?php

namespace Modules\Platform;

use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\CreateAcademy;
use Modules\Platform\Actions\Modules\SaveAcademyModules;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\ResetDemoTeam;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\TenantRef;
use SensitiveParameter;

final class PlatformService implements PlatformApi
{
    public function __construct(
        private readonly CreateAcademy $createAcademy,
        private readonly ResetDemoTeam $resetDemoTeam,
        private readonly ChangeAcademyPlan $changePlan,
        private readonly SaveAcademyModules $modules,
    ) {}

    public function createAcademy(NewAcademy $academy): TenantRef
    {
        return $this->createAcademy->handle($academy);
    }

    public function changeAcademyPlan(string $academyId, string $planCode, string $reason, array $featuresOff = []): void
    {
        $plan = SaasPlan::query()->offered()->where('code', $planCode)->first()
            ?? throw ValidationException::withMessages(['plan' => __('validation.exists', ['attribute' => 'plan'])]);
        $this->changePlan->handle($academyId, $plan->id, $reason, featuresOff: array_values($featuresOff));
    }

    public function hideAcademyModules(array $features): array
    {
        $hidden = $this->modules->byPlatform(array_fill_keys(array_values($features), false));

        return array_map(fn (Feature $f) => $f->value, $hidden);
    }

    public function resetDemoTeam(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $secret): int
    {
        return $this->resetDemoTeam->handle($password, $secret);
    }
}
