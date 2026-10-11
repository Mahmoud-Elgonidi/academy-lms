<?php

namespace Modules\Platform;

use Modules\Platform\Actions\CreateAcademy;
use Modules\Platform\Actions\ResetDemoTeam;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\SharedKernel\Tenancy\TenantRef;
use SensitiveParameter;

final class PlatformService implements PlatformApi
{
    public function __construct(private readonly CreateAcademy $createAcademy, private readonly ResetDemoTeam $resetDemoTeam) {}

    public function createAcademy(NewAcademy $academy): TenantRef
    {
        return $this->createAcademy->handle($academy);
    }

    public function resetDemoTeam(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $secret): int
    {
        return $this->resetDemoTeam->handle($password, $secret);
    }
}
