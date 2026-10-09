<?php

namespace Modules\Billing\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * What a payer was invoiced and paid, and the receipts they sent (with the
 * files). Money still due holds an erasure back. Nothing is erased here:
 * issued invoices and payments are kept as tax law asks (they never change
 * anyway), and so are the receipts that back a payment.
 */
final class BillingData implements PersonalDataSource
{
    public function key(): string
    {
        return 'billing';
    }

    public function export(string $userId): PersonalDataPart
    {
        $invoices = DB::table('invoices as i')->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->where('a.payer_id', $userId)->whereIn('i.status', ['issued', 'paid', 'void'])->orderBy('i.issued_at');
        $proofs = DB::table('manual_payment_proofs')->where('payer_id', $userId)->orderBy('created_at')
            ->get(['method', 'amount_minor', 'currency', 'paid_on', 'reference', 'note', 'file_path', 'file_name', 'status', 'decision_note', 'created_at']);
        $files = [];
        foreach ($proofs as $i => $p) {
            if ($p->file_path !== null) {
                $files[$p->file_path] = 'receipts/'.($i + 1).'-'.basename((string) $p->file_name);
            }
        }

        return new PersonalDataPart([
            'invoices' => (clone $invoices)->get(['i.number', 'i.status', 'i.currency', 'i.subtotal_minor', 'i.tax_minor', 'i.total_minor', 'i.paid_minor', 'i.issued_at', 'i.due_at', 'i.paid_at', 'i.voided_at'])
                ->map(fn (object $r) => (array) $r)->all(),
            'payments' => DB::table('payments as p')->joinSub((clone $invoices)->select(['i.id', 'i.number']), 'iv', 'iv.id', '=', 'p.invoice_id')
                ->orderBy('p.received_at')->get(['iv.number as invoice', 'p.method', 'p.amount_minor', 'p.currency', 'p.reference', 'p.received_at'])->map(fn (object $r) => (array) $r)->all(),
            'receipts_sent' => $proofs->map(fn (object $p) => array_diff_key((array) $p, ['file_path' => 1, 'file_name' => 1])
                + ['file' => $p->file_path === null ? null : $files[$p->file_path]])->all(),
        ], $files);
    }

    public function blockers(string $userId): array
    {
        $due = DB::table('invoices as i')->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->where('a.payer_id', $userId)->where('i.status', 'issued')->whereColumn('i.paid_minor', '<', 'i.total_minor')->count();

        return $due === 0 ? [] : [trans_choice('billing::privacy.money_due', $due, ['count' => $due])];
    }

    public function erase(string $userId): array
    {
        return [];
    }
}
