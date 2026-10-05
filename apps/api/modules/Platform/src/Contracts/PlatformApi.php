<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\SharedKernel\Tenancy\TenantRef;

/** What other modules and the app layer may ask of Platform. */
interface PlatformApi
{
    public function createAcademy(NewAcademy $academy): TenantRef;
}
