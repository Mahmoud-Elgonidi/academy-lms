<?php

namespace Modules\Platform\Actions\Metering;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\Ai\OwnersOnly;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Metering\NotEnoughBalance;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Plans\PlanWarning;
use Modules\SharedKernel\Plans\Meter;

/**
 * The platform's owners give an academy balance or put it right (console ›
 * the academy), each with a reason the ledger keeps: a bonus (free balance
 * for so many months, 12 at first), an adjustment (up: a lot of its own;
 * down: taken like a use) or a refund (what is left of a bought lot, or some
 * of it, taken back when its money is returned). Audited with the meter and
 * the amount, never the reason.
 */
final class GrantUsage
{
    public const KINDS = [Ledger::BONUS, Ledger::ADJUST, Ledger::REFUND];

    public const REASON_MIN = 5;

    public const MAX = 1000000;

    public function __construct(private readonly Meters $meters, private readonly PlatformAudit $audit) {}

    /**
     * @param  array{kind: string, meter: string, amount: mixed, reason: string, months?: mixed, lot?: ?string}  $data  amount signed for an adjustment
     *
     * @throws ValidationException
     */
    public function handle(PlatformAdmin $by, string $academyId, array $data): void
    {
        OwnersOnly::check($by);
        $academy = Academy::query()->findOrFail($academyId);
        $valid = Validator::make($data, [
            'kind' => ['required', Rule::in(self::KINDS)],
            'meter' => ['required', Rule::enum(Meter::class)],
            'amount' => ['required', 'integer', 'not_in:0', 'min:-'.self::MAX, 'max:'.self::MAX],
            'reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:300'],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'lot' => ['nullable', 'uuid', 'required_if:kind,refund'],
        ], [], ['amount' => __('platform::metering.grant.amount'), 'reason' => __('platform::plans.attributes.reason')])->validate();

        $meter = Meter::from($valid['meter']);
        $amount = (int) $valid['amount'];
        $about = ['reason' => trim((string) $valid['reason']), 'actor_id' => $by->id, 'ref_type' => 'console'];
        if ($valid['kind'] !== Ledger::ADJUST && $amount < 0) {
            throw ValidationException::withMessages(['amount' => __('platform::metering.grant.errors.positive')]);
        }

        try {
            match ($valid['kind']) {
                Ledger::BONUS => $this->meters->addLot($academy->id, $meter, Ledger::BONUS, $amount, CarbonImmutable::now()->addMonths((int) ($valid['months'] ?? Ledger::LOT_MONTHS)), $about),
                Ledger::ADJUST => $amount > 0
                    ? $this->meters->addLot($academy->id, $meter, Ledger::ADJUST, $amount, CarbonImmutable::now()->addMonths((int) ($valid['months'] ?? Ledger::LOT_MONTHS)), $about)
                    : $this->meters->adjustDown($academy->id, $meter, -$amount, $about),
                Ledger::REFUND => $this->meters->takeBack($academy->id, $meter, (string) $valid['lot'], $amount, $about),
            };
        } catch (NotEnoughBalance $e) {
            throw ValidationException::withMessages(['amount' => __('platform::metering.grant.errors.more_than_left', ['left' => $e->available])]);
        }

        $action = ['bonus' => 'academy.usage_bonus', 'adjust' => 'academy.usage_adjusted', 'refund' => 'academy.usage_refunded'][$valid['kind']];
        $this->audit->record($academy->id, $action, 'usage_ledger', null, ['meter' => $meter->value, 'amount' => $valid['kind'] === Ledger::ADJUST && $amount > 0 ? '+'.$amount : $amount]);
        app(PlanWarning::class)->forget($academy->id);
    }
}
