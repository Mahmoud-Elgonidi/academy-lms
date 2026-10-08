<?php

namespace Modules\SharedKernel\Plans;

/**
 * Areas an academy's SaaS plan includes or not. The rest (lessons,
 * scheduling, families, reports, invoices paid by hand, live lessons) is in
 * every plan. A module guards its own area: its screens (canAccess), its
 * API routes (the "plan.feature" middleware) and its Actions.
 */
enum Feature: string
{
    case Crm = 'crm';
    case OnlinePayments = 'online_payments';
    case Import = 'import';
    case CustomBranding = 'custom_branding';
    case Payroll = 'payroll';

    public function label(): string
    {
        return __('shared::plans.features.'.$this->value);
    }
}
