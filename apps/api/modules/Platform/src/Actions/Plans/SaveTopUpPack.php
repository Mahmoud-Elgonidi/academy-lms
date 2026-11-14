<?php

namespace Modules\Platform\Actions\Plans;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\TopUpPack;

/**
 * The platform team adds an extra pack or edits one (console › Plans): which
 * meter it adds to, how much, its names, its price in each currency (minor
 * units; none: an academy in that currency asks for it by transfer), its
 * order; archiving takes it off what academies may buy (purchases and
 * requests already made keep their amount and price). Audited with the
 * currencies priced, never a price.
 */
final class SaveTopUpPack
{
    public const LIMITS = ['video_minutes', 'ai_uses'];

    /** The currencies a pack may be priced in. */
    public const CURRENCIES = ['SAR', 'USD', 'EGP', 'AED', 'KWD', 'EUR'];

    /** Three-decimal currencies: Stripe charges them in steps of 10 of the minor unit. */
    public const STEP_OF_TEN = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $data  limit, amount, name_ar, name_en, prices (currency => minor units), sort, archived */
    public function handle(?string $packId, array $data): TopUpPack
    {
        $valid = Validator::make($data, [
            'limit' => ['required', Rule::in(self::LIMITS)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'name_ar' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
            'prices' => ['array', 'max:'.count(self::CURRENCIES)],
            'prices.*' => ['integer', 'min:1', 'max:100000000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'archived' => ['boolean'],
        ])->after(function ($validator) use ($data): void {
            foreach (array_keys((array) ($data['prices'] ?? [])) as $currency) {
                if (! in_array($currency, self::CURRENCIES, true)) {
                    $validator->errors()->add('prices', __('platform::recharge.packs.errors.currency', ['currency' => (string) $currency]));
                } elseif (in_array($currency, self::STEP_OF_TEN, true) && (int) $data['prices'][$currency] % 10 !== 0) {
                    $validator->errors()->add('prices', __('platform::recharge.packs.errors.step', ['currency' => $currency]));
                }
            }
        })->validate();
        $prices = array_map('intval', (array) ($valid['prices'] ?? []));

        return DB::connection('pgsql_platform')->transaction(function () use ($packId, $valid, $prices) {
            $pack = $packId ? TopUpPack::query()->lockForUpdate()->findOrFail($packId) : new TopUpPack;
            $archived = (bool) ($valid['archived'] ?? false);
            $pack->fill([
                'limit' => $valid['limit'],
                'amount' => (int) $valid['amount'],
                'name_ar' => trim((string) $valid['name_ar']),
                'name_en' => trim((string) $valid['name_en']),
                'prices' => $prices,
                'sort' => (int) ($valid['sort'] ?? $pack->sort ?? 0),
                'archived_at' => $archived ? ($pack->archived_at ?? now()) : null,
            ])->save();
            $this->audit->record(null, 'platform.topup_pack_saved', 'topup_pack', $pack->id, ['limit' => $pack->limit, 'amount' => $pack->amount, 'currencies' => array_keys($prices)]);

            return $pack;
        });
    }
}
