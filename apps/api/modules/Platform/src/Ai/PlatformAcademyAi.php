<?php

namespace Modules\Platform\Ai;

use Modules\Platform\Ai\Gateway\Routes;
use Modules\Platform\Metering\AiCreditRates;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the current academy may use a helper now: the platform has not
 * switched the helpers off (AI_DRIVER=off), the academy's plan has the AI
 * area, the academy turned this helper on (Settings › AI, off by default),
 * its route has a ready engine for each kind of work (console › AI routing;
 * with no route, the chosen provider has its key and model and the speech
 * key is set where it listens), its AI credits (the month's allowance from
 * the plan's ai_uses and the bought balance, in the usage ledger) still pay
 * for the least request (else quota, and the work goes on by hand), and
 * today's requests are under AI_DAILY_CAP.
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
            $known = app(Capabilities::class)->get($capability);
            throw new AiFailed($why, needed: $why === 'quota' && $known !== null ? app(AiCreditRates::class)->least($known) : null);
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
        if ($this->usedUp($known)) {
            return 'quota';
        }
        if (AiUsage::today() >= (int) config('platform.ai.daily_cap', 500)) {
            return 'limit';
        }

        return null;
    }

    /**
     * The academy's AI credits (the month's allowance and the bought balance,
     * less what is owed and held) cannot pay for even the least request of
     * this helper. No limit: never. The gateway holds the exact estimate.
     */
    private function usedUp(Capability $capability): bool
    {
        $state = app(Meters::class)->state($this->tenants->require()->id, Meter::AiCredits);

        return $state->limited && $state->available() < app(AiCreditRates::class)->least($capability);
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
