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
     * The demo console team (…@platform.test) takes this password, and this authenticator key when one is
     * given; their sign-ins end. How many there were.
     */
    public function resetDemoTeam(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $secret): int;
}
