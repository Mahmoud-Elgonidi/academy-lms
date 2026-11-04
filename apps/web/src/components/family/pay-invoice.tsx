'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { payInvoice } from '@/app/[locale]/family/actions';
import { Button, join } from '@/components/ui';
import { offersSaveCard, type SaveCardOffer } from '@/lib/family/cards';
import type { Invoice } from '@/lib/family/types';
import { SaveCardOption } from './save-card';

/**
 * Pays an invoice on the academy's own payment page: picks the gateway when
 * there are several, opens a checkout (one key per press, so a retry opens one)
 * and goes there. The invoice counts as paid only when the gateway says so.
 */
export function PayInvoice({ invoice, saveCard }: { invoice: Invoice; /** Saving the card while paying (J5b): offered on a gateway that keeps cards. */ saveCard?: SaveCardOffer | null }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [account, setAccount] = useState(invoice.pay_with[0]?.account_id ?? null);
  const [save, setSave] = useState(false);
  const saving = offersSaveCard(saveCard, invoice.pay_with.find((a) => a.account_id === account));
  const [message, setMessage] = useState<string>();
  const [going, setGoing] = useState(false);
  const [pending, start] = useTransition();

  if (invoice.pay_with.length === 0) return <p className="text-sm text-muted">{t('cantPayOnline')}</p>;

  const pay = () =>
    start(async () => {
      setMessage(undefined);
      // Saving the card comes back to the portal, which says it was kept.
      const opened = await payInvoice(invoice.id, account, crypto.randomUUID(), undefined, saving && save && saveCard ? { version: saveCard.version } : null);
      if (!opened.ok) {
        setMessage(opened.message ?? common('problem'));
        return;
      }
      setGoing(true);
      window.location.assign(opened.url);
    });

  return (
    <div className="space-y-3">
      {invoice.pay_with.length > 1 ? (
        <fieldset>
          <legend className="mb-2 text-sm font-semibold">{t('pickGateway')}</legend>
          <div className="grid gap-2 sm:grid-cols-2">
            {invoice.pay_with.map((a) => (
              <label
                key={a.account_id}
                className={join(
                  'flex cursor-pointer items-start gap-3 rounded-control border p-3',
                  account === a.account_id ? 'border-brand bg-brand-soft' : 'border-line bg-white hover:border-brand-line',
                )}
              >
                <input type="radio" name={`pay-${invoice.id}`} value={a.account_id} checked={account === a.account_id} onChange={() => setAccount(a.account_id)} className="mt-1 accent-[var(--color-brand)]" />
                <span>
                  <span className="block font-semibold">{a.label}</span>
                  {a.methods.length > 0 ? <span className="block text-sm text-muted">{a.methods.join(' · ')}</span> : null}
                </span>
              </label>
            ))}
          </div>
        </fieldset>
      ) : null}
      {saving && saveCard ? <SaveCardOption checked={save} onChange={setSave} text={saveCard.text} disabled={pending || going} /> : null}
      <Button type="button" onClick={pay} disabled={pending || going} className="w-full sm:w-auto">
        {going ? t('going') : pending ? common('loading') : t('payAmount', { amount: invoice.outstanding })}
      </Button>
      {invoice.pay_with.length === 1 && invoice.pay_with[0]!.methods.length > 0 ? <p className="text-sm text-muted">{invoice.pay_with[0]!.methods.join(' · ')}</p> : null}
      <p className="text-xs text-muted">{t('payNote')}</p>
      {message ? (
        <p className="text-sm text-danger" role="alert">
          {message}
        </p>
      ) : null}
    </div>
  );
}
