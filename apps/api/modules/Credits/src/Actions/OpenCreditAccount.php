<?php

namespace Modules\Credits\Actions;

use Modules\Credits\Models\CreditAccount;

final class OpenCreditAccount
{
    public function handle(string $studentId, string $scopeType, ?string $scopeId, ?int $lowBalanceMin = null): CreditAccount
    {
        return CreditAccount::query()->firstOrCreate(
            ['scope_type' => $scopeType, 'scope_id' => $scopeId],
            ['student_id' => $studentId, 'low_balance_min' => $lowBalanceMin],
        );
    }
}
