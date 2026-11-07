<?php

namespace Modules\Platform\Actions\Plans;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\TopUpPack;

/**
 * The platform team adds an extra pack or edits one (console › Plans): which
 * monthly limit it adds to, how much, its names, the price shown to the
 * academy, its order; archiving takes it off what academies may ask for
 * (requests already made keep their amount). Audited.
 */
final class SaveTopUpPack
{
    public const LIMITS = ['video_minutes', 'ai_uses'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $data  limit, amount, name_ar, name_en, price_minor, currency, sort, archived */
    public function handle(?string $packId, array $data): TopUpPack
    {
        $valid = Validator::make($data, [
            'limit' => ['required', Rule::in(self::LIMITS)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'name_ar' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
            'price_minor' => ['nullable', 'integer', 'min:0', 'required_with:currency'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/', 'required_with:price_minor'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'archived' => ['boolean'],
        ])->validate();

        return DB::connection('pgsql_platform')->transaction(function () use ($packId, $valid) {
            $pack = $packId ? TopUpPack::query()->lockForUpdate()->findOrFail($packId) : new TopUpPack;
            $archived = (bool) ($valid['archived'] ?? false);
            $pack->fill([
                'limit' => $valid['limit'],
                'amount' => (int) $valid['amount'],
                'name_ar' => trim((string) $valid['name_ar']),
                'name_en' => trim((string) $valid['name_en']),
                'price_minor' => $valid['price_minor'] ?? null,
                'currency' => $valid['currency'] ?? null,
                'sort' => (int) ($valid['sort'] ?? $pack->sort ?? 0),
                'archived_at' => $archived ? ($pack->archived_at ?? now()) : null,
            ])->save();
            $this->audit->record(null, 'platform.topup_pack_saved', 'topup_pack', $pack->id, ['limit' => $pack->limit, 'amount' => $pack->amount]);

            return $pack;
        });
    }
}
