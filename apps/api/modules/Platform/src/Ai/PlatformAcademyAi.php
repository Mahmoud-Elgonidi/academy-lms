<?php

namespace Modules\Platform\Ai;

use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the current academy may use a helper now: the platform has not
 * switched the helpers off (AI_DRIVER=off), the academy's plan has the AI
 * area, the academy turned this helper on (Settings › AI, off by default),
 * the keys it needs are set, and today's uses are under AI_DAILY_CAP.
 */
final class PlatformAcademyAi implements AcademyAi
{
    public function __construct(
        private readonly AcademyPlan $plan,
        private readonly TenantContext $tenants,
    ) {}

    public function on(AiFeature $feature): bool
    {
        return $this->why($feature) === null;
    }

    public function require(AiFeature $feature): void
    {
        $why = $this->why($feature);
        if ($why !== null) {
            throw new AiFailed($why);
        }
    }

    /** Why the helper cannot run now (an AiFailed reason), or null. */
    public function why(AiFeature $feature): ?string
    {
        if ($this->driver() === 'off' || $this->tenants->id() === null || ! $this->plan->has(Feature::Ai)) {
            return 'off';
        }
        if (! AiSettings::current()->isOn($feature)) {
            return 'off';
        }
        if (! self::configured($feature)) {
            return 'not_configured';
        }
        if (AiUsage::today() >= (int) config('platform.ai.daily_cap', 500)) {
            return 'limit';
        }

        return null;
    }

    /** The platform has what this helper needs: the demo driver needs nothing. */
    public static function configured(AiFeature $feature): bool
    {
        if (self::demo()) {
            return true;
        }
        if ($feature->needsLanguage() && ((string) config('platform.ai.key') === '' || (string) config('platform.ai.models.best') === '')) {
            return false;
        }

        return ! $feature->needsSpeech() || (string) config('platform.ai.speech.key') !== '';
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
