<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\Events\InvoiceIssued;
use Modules\Billing\Contracts\Events\InvoicePaid;
use Modules\Billing\Contracts\Events\PaymentProofDecided;
use Modules\Billing\Contracts\Events\PaymentProofSubmitted;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;

/**
 * The payer hears about each invoice and each payment in full
 * (docs/architecture/01-features.md, section 15); whoever records payments
 * hears that a proof of paying by hand is waiting, and the payer hears how
 * it was decided (an approval that pays the invoice in full is the "paid"
 * message itself).
 */
final class SendBillingNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        foreach ([
            ['invoice_issued', ['email', 'whatsapp', 'push'], ['number' => 'NR-000002', 'student' => 'زياد', 'total' => '560 ر.س.', 'due' => 'الأربعاء 14 أكتوبر', 'link' => 'https://noor.example.com/pay/invoice/…']],
            ['invoice_paid', ['email', 'push'], ['number' => 'NR-000002', 'total' => '560 ر.س.']],
            ['proof_approved', ['email', 'push'], ['number' => 'NR-000002', 'amount' => '300 ر.س.', 'left' => '260 ر.س.']],
            ['proof_rejected', ['email', 'whatsapp', 'push'], ['number' => 'NR-000002', 'amount' => '560 ر.س.', 'reason' => 'المبلغ ماوصلش الحساب لحد دلوقتي.', 'link' => 'https://noor.example.com/pay/invoice/…']],
        ] as [$name, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "billing.{$name}", category: 'billing',
                title: "billing::notifications.{$name}.title", body: "billing::notifications.{$name}.body",
                audience: 'billing::notifications.audience.payer', channels: $channels, sample: $sample,
            ));
        }
        $notifications->define(new TemplateDefinition(
            key: 'billing.proof_received', category: 'staff',
            title: 'billing::notifications.proof_received.title', body: 'billing::notifications.proof_received.body',
            audience: 'billing::notifications.audience.accounts', channels: ['push', 'email'],
            sample: ['payer' => 'أ. منى', 'number' => 'NR-000002', 'amount' => '560 ر.س.', 'method' => 'InstaPay', 'student' => 'زياد'],
        ));
    }

    public function issued(InvoiceIssued $event): void
    {
        $invoice = Invoice::query()->with('billingAccount')->find($event->invoiceId);
        if ($invoice === null || $invoice->billingAccount === null) {
            return;
        }
        $student = $this->studentOf($invoice);

        $this->notifications->send(new Message('billing.invoice_issued', [$invoice->billingAccount->payer_id], [
            'number' => $invoice->number,
            'student' => $student ?? '',
            'total' => $invoice->total(),
            'due' => $invoice->due_at ? new Day($invoice->due_at->toDateString()) : '',
        ], ['screen' => 'invoice', 'id' => $invoice->id], "invoice:{$invoice->id}"));
    }

    public function paid(InvoicePaid $event): void
    {
        $invoice = Invoice::query()->with('billingAccount')->find($event->invoiceId);
        if ($invoice === null || $invoice->billingAccount === null) {
            return;
        }

        $this->notifications->send(new Message('billing.invoice_paid', [$invoice->billingAccount->payer_id], [
            'number' => $invoice->number,
            'total' => $invoice->total(),
        ], ['screen' => 'invoice', 'id' => $invoice->id], "invoice-paid:{$invoice->id}"));
    }

    public function proofSubmitted(PaymentProofSubmitted $event): void
    {
        $proof = PaymentProof::query()->find($event->proofId);
        $invoice = Invoice::query()->find($event->invoiceId);
        if ($proof === null || $invoice === null) {
            return;
        }

        $this->notifications->send(new Message('billing.proof_received', $this->identity->usersWithAbility(Ability::RecordPayments), [
            'payer' => (string) DB::table('users')->where('id', $proof->payer_id)->value('name'),
            'number' => $invoice->number,
            'amount' => $proof->money(),
            'method' => new Translatable('billing::billing.methods.'.$proof->method),
            'student' => $this->studentOf($invoice) ?? '',
        ], ['screen' => 'invoice', 'id' => $invoice->id], "proof:{$proof->id}"));
    }

    public function proofDecided(PaymentProofDecided $event): void
    {
        $proof = PaymentProof::query()->find($event->proofId);
        $invoice = Invoice::query()->find($event->invoiceId);
        if ($proof === null || $invoice === null) {
            return;
        }
        if ($event->approved) {
            if ($invoice->status === 'paid') {
                return;     // "paid in full" says it (InvoicePaid)
            }
            $amount = DB::table('payments')->where('id', $proof->payment_id)->value('amount_minor');
            $this->notifications->send(new Message('billing.proof_approved', [$proof->payer_id], [
                'number' => $invoice->number,
                'amount' => Money::of((int) $amount, $invoice->currency),
                'left' => $invoice->outstanding(),
            ], ['screen' => 'invoice', 'id' => $invoice->id], "proof-decided:{$proof->id}"));

            return;
        }

        $this->notifications->send(new Message('billing.proof_rejected', [$proof->payer_id], [
            'number' => $invoice->number,
            'amount' => $proof->money(),
            'reason' => (string) $proof->decision_note,
        ], ['screen' => 'invoice', 'id' => $invoice->id], "proof-decided:{$proof->id}"));
    }

    private function studentOf(Invoice $invoice): ?string
    {
        return $invoice->subscription_id === null ? null : DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->where('s.id', $invoice->subscription_id)->value('u.name');
    }
}
