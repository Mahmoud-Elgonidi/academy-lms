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

        return $admin instanceof PlatformAdmin && $admin->is_active ? new PlatformAdminRef($admin->id, $admin->name, $admin->email) : null;
    }

    public function adminActive(string $adminId): bool
    {
        return PlatformAdmin::query()->whereKey($adminId)->where('is_active', true)->exists();
    }

    public function consoleUrl(string $path = ''): string
    {
        $base = config('platform.console_url') ?: rtrim((string) config('app.url'), '/').'/console';

        return rtrim($base, '/').($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
