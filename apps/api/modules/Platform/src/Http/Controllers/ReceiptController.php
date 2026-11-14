<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Recharging\ReceiptPage;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A platform receipt as a page to read and print (no PDF library: the
 * browser prints it): for whoever sees the academy's plan, signed in on the
 * academy's panel, its own receipts only; and for the platform team in the
 * console. Anyone else: not found.
 */
final class ReceiptController
{
    public function academy(Request $request, string $receipt, TenantContext $tenants): View
    {
        $user = $request->user() ?? abort(403);
        abort_unless($tenants->check() && Gate::forUser($user)->allows(Ability::ViewPlan->value), 403);
        $found = Str::isUuid($receipt) ? PlatformReceipt::query()->where('academy_id', $tenants->id())->find($receipt) : null;
        abort_if($found === null, 404);
        // In the reader's own language, as on the panel.
        $locale = (string) ($user->locale ?? '');
        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }

        return ReceiptPage::view($found);
    }

    public function console(string $receipt): View
    {
        $admin = auth('platform')->user();
        abort_unless($admin instanceof PlatformAdmin && $admin->is_active, 403);
        $found = Str::isUuid($receipt) ? PlatformReceipt::query()->find($receipt) : null;
        abort_if($found === null, 404);

        return ReceiptPage::view($found);
    }
}
