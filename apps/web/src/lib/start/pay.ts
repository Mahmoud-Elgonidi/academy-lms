import type { CheckoutState, InvoiceSummary } from './types';

/**
 * Where a payment stands on the page after the gateway: paid (the invoice
 * says so, or the gateway confirmed it), confirming (the gateway has not
 * answered yet), failed (declined or the page expired: nothing charged, try
 * again), refund (more came than was due), or waiting (no online payment on
 * it yet, something still to pay).
 */
export type PayState = 'paid' | 'confirming' | 'failed' | 'refund' | 'waiting';

export function payState(invoice: Pick<InvoiceSummary, 'status' | 'outstanding'>, checkout: Pick<CheckoutState, 'status'> | null): PayState {
  if (checkout?.status === 'refund_due') return 'refund';
  if (invoice.status === 'paid' || checkout?.status === 'paid' || (invoice.outstanding.amount_minor <= 0 && invoice.status !== 'void')) return 'paid';
  if (checkout?.status === 'open') return 'confirming';
  if (checkout?.status === 'failed' || checkout?.status === 'expired') return 'failed';
  return 'waiting';
}
