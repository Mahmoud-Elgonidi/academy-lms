<?php

namespace Modules\Platform\Actions\Recharging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Domain\Recharging\AutoRechargeRules;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\PlatformPaymentMethod;
use Modules\Platform\Payments\StripeClient;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Meter;

/**
 * The academy sets automatic recharge for a meter (Settings › Usage and
 * balance): when the balance falls below the threshold, buy this pack on the
 * saved card, at most so many times a calendar month. Its owner or an admin,
 * never while the platform team is signed in as someone; turning it on needs
 * a live card and a pack priced in the academy's currency. Audited (the
 * meter, on or off, the threshold and the cap; never the card).
 */
final class SaveAutoRecharge
{
    public function __construct(
        private readonly PlanRequests $requests,
        private readonly PlatformAudit $audit,
        private readonly PlatformAcademyPlan $plan,
    ) {}

    /**
     * @param  array<string, mixed>  $data  enabled, pack_id, threshold, monthly_cap
     *
     * @throws ValidationException
     */
    public function handle(Meter $meter, array $data, string $userId): AutoRecharge
    {
        $tenant = $this->requests->authorizeAcademy();
        $academy = Academy::query()->findOrFail($tenant->id);
        $valid = Validator::make($data, [
            'enabled' => ['required', 'boolean'],
            'pack_id' => ['required', 'uuid'],
            'threshold' => ['required', 'integer', 'min:0', 'max:10000000'],
            'monthly_cap' => ['required', 'integer', 'min:1', 'max:'.AutoRechargeRules::CAP_MAX],
        ], [], [
            'pack_id' => __('platform::recharge.auto.pack'), 'threshold' => __('platform::recharge.auto.threshold'), 'monthly_cap' => __('platform::recharge.auto.cap'),
        ])->validate();

        $pack = TopUpPack::query()->offered()->where('limit', $meter->limit()->value)->find($valid['pack_id']);
        if ($pack === null) {
            throw ValidationException::withMessages(['pack_id' => __('platform::topups.errors.no_pack')]);
        }
        $enabled = (bool) $valid['enabled'];
        if ($enabled) {
            if ($meter === Meter::AiCredits && ! $this->plan->has(Feature::Ai)) {
                throw ValidationException::withMessages(['pack_id' => __('platform::topups.errors.no_ai')]);
            }
            if (! StripeClient::configured()) {
                throw ValidationException::withMessages(['enabled' => __('platform::recharge.errors.online_off')]);
            }
            if ($pack->priceIn($academy->base_currency) === null) {
                throw ValidationException::withMessages(['pack_id' => __('platform::recharge.errors.no_price', ['currency' => $academy->base_currency])]);
            }
            $card = PlatformPaymentMethod::liveFor($academy->id);
            if ($card === null || $card->expired()) {
                throw ValidationException::withMessages(['enabled' => __('platform::recharge.errors.no_card')]);
            }
        }

        return DB::connection('pgsql_platform')->transaction(function () use ($academy, $meter, $pack, $valid, $enabled, $userId) {
            $setting = AutoRecharge::for($academy->id, $meter) ?? new AutoRecharge(['academy_id' => $academy->id, 'meter' => $meter->value]);
            $setting->fill([
                'pack_id' => $pack->id,
                'threshold' => (int) $valid['threshold'],
                'monthly_cap' => (int) $valid['monthly_cap'],
                'enabled' => $enabled,
                'updated_by' => $userId,
                'off_reason' => null,
                'off_at' => $enabled ? null : now(),
            ])->save();
            $this->audit->record($academy->id, 'academy.auto_recharge_saved', 'auto_recharge', null, [
                'meter' => $meter->value, 'auto_recharge' => $enabled ? 'on' : 'off', 'threshold' => (int) $valid['threshold'], 'cap' => (int) $valid['monthly_cap'],
            ]);

            return $setting;
        });
    }
}
