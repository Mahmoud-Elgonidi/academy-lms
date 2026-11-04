<?php

namespace Modules\Billing\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Cards\RevokePaymentMethod;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Support\AutoCharges;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * What a payer was invoiced and paid, the receipts they sent (with the
 * files), the personal offers made for them (a trial offer) and the
 * reminders they were sent. Money still
 * due holds an erasure back. Issued invoices and payments are kept as tax
 * law asks (they never change anyway), and so are the receipts that back a
 * payment; an erased payer's personal offers are turned off. Saved cards
 * (J5b) are copied as brand, last four, expiry and dates only (never the
 * gateway's references), with the automatic charges; erasure lets every card
 * go (its references dropped), turns automatic renewal off and cancels the
 * charges still to come.
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
            'offers' => DB::table('coupons')->where('payer_id', $userId)->orderBy('created_at')
                ->get(['purpose', 'percent_bp', 'expires_at', 'is_active', 'created_at'])->map(fn (object $r) => (array) $r)->all(),
            // The reminders the collection timeline sent them (J5a): which step, when.
            'reminders' => DB::table('collection_steps as c')->leftJoin('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'c.academy_id')->on('i.id', '=', 'c.invoice_id'))
                ->where('c.payer_id', $userId)->orderBy('c.sent_at')->get(['i.number as invoice', 'c.step', 'c.sent_at'])->map(fn (object $r) => (array) $r)->all(),
            // Saved cards: what was shown of them, never the gateway's references (J5b).
            'saved_cards' => DB::table('payment_methods')->where('payer_id', $userId)->orderBy('created_at')
                ->get(['brand', 'last4', 'exp_month', 'exp_year', 'mandate_version', 'created_at', 'revoked_at', 'revoked_reason'])->map(fn (object $r) => (array) $r)->all(),
            'automatic_charges' => DB::table('auto_charges as c')->leftJoin('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'c.academy_id')->on('i.id', '=', 'c.invoice_id'))
                ->where('c.payer_id', $userId)->orderBy('c.created_at')
                ->get(['i.number as invoice', 'c.amount_minor', 'c.currency', 'c.status', 'c.attempts', 'c.noticed_at', 'c.charge_at', 'c.failure', 'c.cancel_reason'])
                ->map(fn (object $r) => (array) $r)->all(),
            'receipts_sent' => $proofs->map(fn (object $p) => array_diff_key((array) $p, ['file_path' => 1, 'file_name' => 1])
                + ['file' => $p->file_path === null ? null : $files[$p->file_path]])->all(),
        ], $files);
    }

    public function blockers(string $userId): array
    {
        $due = DB::table('invoices as i')->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->where('a.payer_id', $userId)->where('i.status', 'issued')->whereColumn('i.paid_minor', '<', 'i.total_minor')->count();

        $blockers = $due === 0 ? [] : [trans_choice('billing::privacy.money_due', $due, ['count' => $due])];
        // A charge to the saved card under way: its answer comes first (J5b).
        if (DB::table('auto_charges')->where('payer_id', $userId)->where('status', 'charging')->exists()) {
            $blockers[] = __('billing::privacy.charge_under_way');
        }

        return $blockers;
    }

    public function erase(string $userId): array
    {
        $offers = DB::table('coupons')->where('payer_id', $userId)->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]);
        $cards = 0;
        foreach (PaymentMethod::query()->live()->where('payer_id', $userId)->pluck('id') as $id) {
            app(RevokePaymentMethod::class)->handle((string) $id, $userId, 'erased');
            $cards++;
        }
        DB::table('billing_accounts')->where('payer_id', $userId)->update(['auto_renew' => false, 'auto_renew_changed_at' => now()]);
        $charges = app(AutoCharges::class)->cancelForPayer($userId, 'erased');

        return array_filter(['offers' => $offers, 'cards' => $cards, 'automatic_charges' => $charges]);
    }
}
