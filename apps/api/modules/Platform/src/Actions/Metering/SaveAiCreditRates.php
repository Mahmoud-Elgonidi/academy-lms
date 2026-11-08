<?php

namespace Modules\Platform\Actions\Metering;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\Ai\OwnersOnly;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Metering\AiCreditRates;
use Modules\Platform\Models\Metering\AiCreditRate;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Ai\Capabilities;

/**
 * The platform's owners set what each AI capability costs in credits
 * (console › AI credit rates): a base each request and so much each minute
 * of audio heard. A rate set back to the capability's own follows it again.
 * The next request is charged at the new rate; what was charged stays.
 * Audited with the capabilities' names only.
 */
final class SaveAiCreditRates
{
    public const MAX = 1000;

    public function __construct(private readonly Capabilities $capabilities, private readonly PlatformAudit $audit, private readonly AiCreditRates $rates) {}

    /**
     * @param  array<string, array{base?: mixed, per_minute?: mixed}>  $rates  by capability key
     * @return list<string> the capabilities whose rate changed
     */
    public function handle(PlatformAdmin $by, array $rates): array
    {
        OwnersOnly::check($by);
        $clean = [];
        foreach ($rates as $key => $rate) {
            $capability = $this->capabilities->get((string) $key);
            if ($capability === null) {
                throw ValidationException::withMessages(['rates' => __('platform::metering.rates.errors.unknown')]);
            }
            $base = filter_var($rate['base'] ?? null, FILTER_VALIDATE_INT);
            $perMinute = filter_var($rate['per_minute'] ?? 0, FILTER_VALIDATE_INT);
            if ($base === false || $perMinute === false || $base < 0 || $perMinute < 0 || $base > self::MAX || $perMinute > self::MAX
                || ($base === 0 && $perMinute === 0)) {
                throw ValidationException::withMessages(["rates.{$key}" => __('platform::metering.rates.errors.amount', ['max' => self::MAX])]);
            }
            if ($perMinute > 0 && ! $capability->needsSpeech()) {
                throw ValidationException::withMessages(["rates.{$key}" => __('platform::metering.rates.errors.no_audio')]);
            }
            $clean[$capability->key] = [$capability, $base, $perMinute];
        }

        $changed = DB::connection('pgsql_platform')->transaction(function () use ($by, $clean): array {
            $changed = [];
            foreach ($clean as $key => [$capability, $base, $perMinute]) {
                $now = $this->rates->for($capability);
                if ($now['base'] === $base && $now['per_minute'] === $perMinute) {
                    continue;
                }
                if ($base === $capability->credits && $perMinute === $capability->creditsPerMinute) {
                    AiCreditRate::query()->whereKey($key)->delete();
                } else {
                    AiCreditRate::query()->updateOrCreate(['capability' => $key], ['base' => $base, 'per_minute' => $perMinute, 'updated_by' => $by->id]);
                }
                $changed[] = $key;
            }
            if ($changed !== []) {
                $this->audit->record(null, 'platform.ai_credit_rates_saved', 'ai_credit_rates', null, ['helpers' => $changed]);
            }

            return $changed;
        });
        $this->rates->forget();

        return $changed;
    }
}
