<?php

namespace Modules\Platform;

use Modules\Platform\Actions\CreateAcademy;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\SharedKernel\Tenancy\TenantRef;

final class PlatformService implements PlatformApi
{
    public function __construct(private readonly CreateAcademy $createAcademy) {}

    public function createAcademy(NewAcademy $academy): TenantRef
    {
        return $this->createAcademy->handle($academy);
    }
}
