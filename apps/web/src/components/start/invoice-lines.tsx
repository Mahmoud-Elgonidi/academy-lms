import { useTranslations } from 'next-intl';
import type { InvoiceSummary } from '@/lib/start/types';

/**
 * An invoice as its payer reads it before paying: what it is for and what
 * came off it line by line, the VAT (inside the prices or added), the total,
 * and what is left when part is paid. The same on the invoice page, after
 * starting a course and back from the gateway.
 */
export function InvoiceLines({ invoice }: { invoice: InvoiceSummary }) {
  const t = useTranslations('invoice');
  const vat = invoice.vat;
  const partly = invoice.paid.amount_minor > 0;
  return (
    <dl className="st-lines text-[15px]" data-lines>
      {invoice.lines.map((line, n) => (
        <div key={n} data-kind={line.kind === 'discount' ? 'discount' : 'line'}>
          <dt className="min-w-0">{line.description}{line.quantity > 1 ? ` × ${line.quantity}` : ''}</dt>
          <dd>{line.amount}</dd>
        </div>
      ))}
      {vat.rate > 0 ? (
        <>
          {vat.included ? null : (
            <div className="text-sm text-muted"><dt>{t('subtotal')}</dt><dd>{invoice.subtotal.amount}</dd></div>
          )}
          <div className="text-sm text-muted">
            <dt>{vat.included ? t('vatIncluded', { rate: vat.rate }) : t('vatAdded', { rate: vat.rate })}</dt>
            <dd>{vat.amount}</dd>
          </div>
        </>
      ) : null}
      <div data-kind="total"><dt>{t('total')}</dt><dd data-total>{invoice.total.amount}</dd></div>
      {partly ? (
        <>
          <div className="text-sm text-muted"><dt>{t('paid')}</dt><dd>{invoice.paid.amount}</dd></div>
          <div className="font-semibold"><dt>{t('left')}</dt><dd>{invoice.outstanding.amount}</dd></div>
        </>
      ) : null}
    </dl>
  );
}
