<?php

namespace Modules\Billing\Payments;

use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Throwable;

/** Which of the academy's gateway accounts can take an invoice, and how to pay by hand otherwise. */
final class OnlinePayments
{
    public function __construct(private readonly GatewayManager $gateways) {}

    /** @return list<GatewayAccount> active, taking this currency, offered on this server, and able to charge this amount */
    public function accountsFor(Invoice $invoice): array
    {
        if ($invoice->status !== 'issued' || $invoice->outstanding()->minor <= 0) {
            return [];
        }

        return GatewayAccount::query()->where('is_active', true)->orderBy('sort')->orderBy('created_at')->get()
            ->filter(function (GatewayAccount $account) use ($invoice) {
                try {
                    return $account->takes($invoice->currency) && $this->gateways->for($account)->refuses($invoice->outstanding()) === null;
                } catch (Throwable) {
                    return false;
                }
            })->values()->all();
    }

    /** Whether any account takes this currency at all (for the pay link in messages and the "pay now" button). */
    public function offers(string $currency): bool
    {
        return GatewayAccount::query()->where('is_active', true)->get()
            ->contains(fn (GatewayAccount $account) => $account->takes($currency) && ($account->gateway !== 'demo' || $this->gateways->demoAllowed()));
    }

    public function manualNote(): ?string
    {
        return BillingSettings::current()->manual_payment_note;
    }
}
