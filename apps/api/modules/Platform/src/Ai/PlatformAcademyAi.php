<?php

namespace Modules\Platform\Ai;

use Carbon\CarbonImmutable;
use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\AiUsage;
use Modules\Platform\Plans\Extras;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the current academy may use a helper now: the platform has not
 * switched the helpers off (AI_DRIVER=off), the academy's plan has the AI
 * area, the academy turned this helper on (Settings › AI, off by default),
 * the chosen provider has its key and model (Ollama: its address) and the
 * speech key is set where it listens, the month's uses are under the plan's
 * ai_uses (with the month's active extra packs; else quota, and the work
 * goes on by hand), and today's requests are under AI_DAILY_CAP.
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

    /** The platform has what this helper needs: the demo driver needs nothing. */
    public static function configured(AiFeature $feature): bool
    {
        if (self::demo()) {
            return true;
        }
        if ($feature->needsLanguage() && ! (AiProvider::current()?->configured() ?? false)) {
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
