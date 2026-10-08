<?php

namespace Modules\Platform\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanSummary;
use Modules\Platform\Plans\PlanUsage;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use UnitEnum;

/**
 * The academy's SaaS plan, for its owner and admins: how much of each limit
 * is used now, and the areas it includes. Changing it is the platform
 * team's (from the console), so the page says how to ask.
 */
final class AcademyPlanPage extends Page
{
    protected string $view = 'platform::plans.academy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'settings/plan';

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->check() && Gate::allows(Ability::ViewPlan->value)
            && SaasSubscription::currentFor(app(TenantContext::class)->require()->id) !== null;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::plans.academy.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::plans.academy.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::plans.academy.subtitle');
    }

    /** @return ?array<string, mixed> */
    #[Computed]
    public function plan(): ?array
    {
        $usage = app(PlanUsage::class);
        $terms = $usage->terms();

        return $terms === null ? null : PlanSummary::of($terms, $usage->lines());
    }
}
