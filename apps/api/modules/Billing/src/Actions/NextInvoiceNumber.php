<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gapless invoice numbers per academy (docs/architecture/03, "ترقيم الفواتير"):
 * a row locked for the rest of the caller's transaction, not a Postgres
 * sequence, which can skip numbers when a transaction rolls back.
 */
final class NextInvoiceNumber
{
    public function take(string $prefix): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Take an invoice number inside the transaction that issues the invoice.');
        }

        DB::statement('insert into invoice_sequences (academy_id, next_no) values (app_current_tenant(), 1) on conflict (academy_id) do nothing');
        $no = (int) DB::table('invoice_sequences')->lockForUpdate()->value('next_no');
        DB::table('invoice_sequences')->update(['next_no' => $no + 1]);

        return $prefix.str_pad((string) $no, 6, '0', STR_PAD_LEFT);
    }
}
