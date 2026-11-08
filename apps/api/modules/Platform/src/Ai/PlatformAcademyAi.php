<?php

namespace Modules\Platform\Ai;

use Carbon\CarbonImmutable;
use Modules\Platform\Ai\Gateway\Routes;
use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\AiUsage;
use Modules\Platform\Plans\Extras;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the current academy may use a helper now: the platform has not
 * switched the helpers off (AI_DRIVER=off), the academy's plan has the AI
 * area, the academy turned this helper on (Settings › AI, off by default),
 * its route has a ready engine for each kind of work (console › AI routing;
 * with no route, the chosen provider has its key and model and the speech
 * key is set where it listens), the month's uses are under the plan's
 * ai_uses (with the month's active extra packs; else quota, and the work
 * goes on by hand), and today's requests are under AI_DAILY_CAP.
 */
final class PlatformAcademyAi implements AcademyAi
{
    public function __construct(
        private readonly AcademyPlan $plan,
        private readonly TenantContext $tenants,
    ) {}

    public function on(AiFeature|string $capability): bool
    {
        return $this->why($capability) === null;
    }

    public function require(AiFeature|string $capability): void
    {
        $why = $this->why($capability);
        if ($why !== null) {
            throw new AiFailed($why);
        }
    }

    /** Why the helper cannot run now (an AiFailed reason), or null. */
    public function why(AiFeature|string $capability): ?string
    {
        $known = app(Capabilities::class)->get($capability);
        if ($known === null || $this->driver() === 'off' || $this->tenants->id() === null || ! $this->plan->has(Feature::Ai)) {
            return 'off';
        }
        if (! AiSettings::current()->isOn($known)) {
            return 'off';
        }
        if (! self::configured($known)) {
            return 'not_configured';
        }
        if ($this->monthUsedUp()) {
            return 'quota';
        }
        if (AiUsage::today() >= (int) config('platform.ai.daily_cap', 500)) {
            return 'limit';
        }

        return null;
    }

    /** The month's uses reached the plan's AI uses and this month's extra packs (no limit: never). */
    private function monthUsedUp(): bool
    {
        $max = $this->plan->limit(Limit::AiUses);
        if ($max === null) {
            return false;
        }
        $academy = $this->tenants->require();
        $month = UsagePeriod::monthOf(CarbonImmutable::now(), $academy->defaultTimezone);

        return AiUsage::usesIn($month) >= $max + Extras::amount($academy->id, Limit::AiUses, $month->key($academy->defaultTimezone));
    }

    /**
     * The platform has what this helper needs, for this academy: an engine
     * ready on its route for each kind of work it does (console › AI routing),
     * else the server's settings (the provider in AI_DRIVER, the speech key).
     * The demo driver needs nothing.
     */
    public static function configured(AiFeature|string|Capability $capability): bool
    {
        if (self::demo()) {
            return true;
        }
        $known = $capability instanceof Capability ? $capability : app(Capabilities::class)->get($capability);
        if ($known === null) {
            return false;
        }
        $routes = app(Routes::class);
        foreach ($known->kinds as $kind) {
            if (! $routes->usable($known->key, $kind)) {
                return false;
            }
        }

        return true;
    }

    /** Fixed answers instead of the providers: never in production. */
    public static function demo(): bool
    {
        return config('platform.ai.driver') === 'demo' && ! app()->isProduction();
    }

    private function driver(): string
    {
        return (string) config('platform.ai.driver', 'claude');
    }
}
