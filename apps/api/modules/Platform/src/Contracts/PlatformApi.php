<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\SharedKernel\Tenancy\TenantRef;
use SensitiveParameter;

/** What other modules and the app layer may ask of Platform. */
interface PlatformApi
{
    public function createAcademy(NewAcademy $academy): TenantRef;

    /**
     * The platform team puts an academy on a plan (by its code) with paid or optional areas taken away,
     * and the reason (ChangeAcademyPlan: the plan it was on ends and stays in its history; audited).
     *
     * @param  list<string>  $featuresOff  Feature values
     */
    public function changeAcademyPlan(string $academyId, string $planCode, string $reason, array $featuresOff = []): void;

    /**
     * The platform team hides optional areas of the current academy as it sets it up (SaveAcademyModules'
     * rules; no screen comes this way). The areas hidden now.
     *
     * @param  list<string>  $features  optional Feature values
     * @return list<string>
     */
    public function hideAcademyModules(array $features): array;

    /**
     * The demo console team (…@platform.test) takes this password, and this authenticator key when one is
     * given; their sign-ins end. How many there were.
     */
    public function resetDemoTeam(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $secret): int;
}
