<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\Events\InvoiceIssued;
use Modules\Billing\Contracts\Events\InvoicePaid;
use Modules\Billing\Models\Invoice;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;

/** The payer hears about each invoice and each payment in full (docs/architecture/01-features.md, section 15). */
final class SendBillingNotifications
{
    public function __construct(private readonly NotificationsApi $notifications) {}

    public static function templates(NotificationsApi $notifications): void
    {
        foreach ([
            ['invoice_issued', ['email', 'whatsapp', 'push'], ['number' => 'NR-000002', 'student' => 'زياد', 'total' => '560 ر.س.', 'due' => 'الأربعاء 14 أكتوبر']],
            ['invoice_paid', ['email', 'push'], ['number' => 'NR-000002', 'total' => '560 ر.س.']],
        ] as [$name, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "billing.{$name}", category: 'billing',
                title: "billing::notifications.{$name}.title", body: "billing::notifications.{$name}.body",
                audience: 'billing::notifications.audience.payer', channels: $channels, sample: $sample,
            ));
        }
    }

    public function issued(InvoiceIssued $event): void
    {
        $invoice = Invoice::query()->with('billingAccount')->find($event->invoiceId);
        if ($invoice === null || $invoice->billingAccount === null) {
            return;
        }
        $student = $invoice->subscription_id === null ? null : DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->where('s.id', $invoice->subscription_id)->value('u.name');

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
}
