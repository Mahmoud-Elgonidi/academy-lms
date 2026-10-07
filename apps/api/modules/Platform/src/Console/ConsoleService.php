<?php

namespace Modules\Platform\Console;

use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Models\PlatformAdmin;

final class ConsoleService implements ConsoleApi
{
    public function currentAdmin(): ?PlatformAdminRef
    {
        $admin = auth()->guard('platform')->user();

        return $admin instanceof PlatformAdmin ? new PlatformAdminRef($admin->id, $admin->name, $admin->email) : null;
    }

    public function consoleUrl(string $path = ''): string
    {
        $base = config('platform.console_url') ?: rtrim((string) config('app.url'), '/').'/console';

        return rtrim($base, '/').($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
