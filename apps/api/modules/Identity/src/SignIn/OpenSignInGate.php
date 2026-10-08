<?php

namespace Modules\Identity\SignIn;

use Modules\Identity\Contracts\DTOs\SignInRefusal;
use Modules\Identity\Contracts\SignInGate;

/** Nobody is held back: the gate until a module above binds its own. */
final class OpenSignInGate implements SignInGate
{
    public function refusal(string $userId): ?SignInRefusal
    {
        return null;
    }
}
