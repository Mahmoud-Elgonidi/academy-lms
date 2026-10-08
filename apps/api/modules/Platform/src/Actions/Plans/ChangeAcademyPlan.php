<?php

namespace Modules\Platform\Actions\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanWarning;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * The platform team puts an academy on a plan, or changes its terms there:
 * its own limits over the plan's (a number, or null for none) and areas
 * added or taken away, with the reason. The plan it was on ends now and
 * stays in its history; both logs keep the change.
 */
final class ChangeAcademyPlan
{
    public const REASON_MIN = 5;

    public function __construct(
        private readonly PlatformAudit $audit,
        private readonly PlatformAcademyPlan $plans,
    ) {}

    /**
     * @param  array<string, ?int>  $limits  by Limit value: this academy's own limit (null: none); a limit left out follows the plan
     * @param  list<string>  $featuresOn
     * @param  list<string>  $featuresOff
     */
    public function handle(string $academyId, string $planId, string $reason, array $limits = [], array $featuresOn = [], array $featuresOff = []): SaasSubscription
    {
        $reason = trim($reason);
        $features = array_column(Feature::cases(), 'value');
        Validator::make(
            ['reason' => $reason, 'plan' => $planId, 'limits' => $limits, 'on' => $featuresOn, 'off' => $featuresOff],
            [
                'reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:300'],
                'plan' => ['required', 'uuid'],
                'limits' => ['array'],
                'limits.*' => ['nullable', 'integer', 'min:0', 'max:10000000'],
                'on' => ['array'], 'on.*' => [Rule::in($features)],
                'off' => ['array'], 'off.*' => [Rule::in($features)],
            ],
            [],
            ['reason' => __('platform::plans.attributes.reason')],
        )->validate();
        $limits = array_intersect_key($limits, array_flip(array_column(Limit::cases(), 'value')));
        $featuresOn = array_values(array_unique($featuresOn));
        $featuresOff = array_values(array_diff(array_unique($featuresOff), $featuresOn));

        $subscription = DB::connection('pgsql_platform')->transaction(function () use ($academyId, $planId, $reason, $limits, $featuresOn, $featuresOff) {
            $academy = Academy::query()->lockForUpdate()->findOrFail($academyId);
            $current = SaasSubscription::query()->where('academy_id', $academy->id)->whereNull('ended_at')->lockForUpdate()->first();
            $plan = SaasPlan::query()->findOrFail($planId);
            if ($plan->archived_at !== null && $current?->plan_id !== $plan->id) {
                throw ValidationException::withMessages(['plan' => __('platform::plans.errors.archived')]);
            }
            $now = CarbonImmutable::now();
            $from = $current?->plan?->code;
            $current?->update(['ended_at' => $now]);
            $actor = AuditActor::current();
            $new = SaasSubscription::query()->create([
                'academy_id' => $academy->id,
                'plan_id' => $plan->id,
                'limits' => (object) $limits,
                'features_on' => $featuresOn,
                'features_off' => $featuresOff,
                'reason' => $reason,
                'changed_by' => $actor->type === 'platform' ? $actor->id : null,
                'started_at' => $now,
            ]);
            $this->audit->record($academy->id, 'academy.plan_changed', 'academy', $academy->id, array_filter([
                'plan' => $plan->name_ar.' / '.$plan->name_en, 'code' => $plan->code, 'from' => $from, 'reason' => $reason,
                'limits' => $limits ?: null, 'on' => $featuresOn ?: null, 'off' => $featuresOff ?: null,
            ], fn ($v) => $v !== null));

            return $new;
        });
        $this->plans->forget();
        app(PlanWarning::class)->forget($academyId);

        return $subscription;
    }
}
