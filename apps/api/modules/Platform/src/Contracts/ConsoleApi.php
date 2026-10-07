<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\PlatformAdminRef;

/** The platform console, for the modules that add screens to it or send people back to it. */
interface ConsoleApi
{
    /** The platform admin signed in to the console, if any. */
    public function currentAdmin(): ?PlatformAdminRef;

    /** A console address, e.g. consoleUrl('support') for the support sign-in screen. */
    public function consoleUrl(string $path = ''): string;
}
