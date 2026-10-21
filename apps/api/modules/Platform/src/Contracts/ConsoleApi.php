<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\DTOs\SignupRequestRef;

/** The platform console, for the modules that add screens to it or send people back to it. */
interface ConsoleApi
{
    /** The platform admin signed in to the console, if any. */
    public function currentAdmin(): ?PlatformAdminRef;

    /** A console address, e.g. consoleUrl('support') for the support sign-in screen. */
    public function consoleUrl(string $path = ''): string;

    /** Whether this admin may still act (not locked out with platform:admin --deactivate). */
    public function adminActive(string $adminId): bool;

    /** @return array<string, string> the plans a new academy can start on, code => name, the default first */
    public function offeredPlans(): array;

    /** A request to join from the platform's page, to make the academy from (null when there is none). */
    public function signupRequest(string $id): ?SignupRequestRef;

    /** The academy asked for in this request is made: the request says so, and who made it. */
    public function signupRequestCreated(string $id, string $academyId): void;
}
