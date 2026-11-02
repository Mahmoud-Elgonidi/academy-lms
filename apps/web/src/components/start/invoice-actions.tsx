'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { couponOnInvoice, payInvoice } from '@/app/[locale]/family/actions';
import { cancelStart } from '@/app/[locale]/family/start/actions';
import { Dialog } from '@/components/dialog';
import { Icon } from '@/components/icons';
import { Button, Input, Notice, buttonLook, join } from '@/components/ui';
import { Link, useRouter } from '@/i18n/navigation';
import { INVOICE_COURSE_KEY } from '@/lib/start/flow';
import type { InvoiceSummary } from '@/lib/start/types';
import { usePressKey } from './press-key';

/*
 * What a payer does with an invoice before paying (docs/architecture/02,
 * section 27, J3): pay it on the academy's gateway and come back to the
 * portal, type a coupon or take it off (the invoice is issued again, so the
 * page goes to the new one), and call off a course they started themselves.
 * One key per press; refusals in the payer's words where they pressed.
 */

/** Pay online: the checkout opens at the academy's gateway, which brings the payer back to /pay/return. */
export function PayNow({ invoice, amount, accounts }: { invoice: string; amount: string; accounts: { account_id: string; label: string; methods: string[] }[] }) {
  const t = useTranslations('invoice');
  const [account, setAccount] = useState(accounts[0]?.account_id ?? null);
  const [message, setMessage] = useState<string>();
  const [going, setGoing] = useState(false);
  const [pending, start] = useTransition();
  const key = usePressKey();

  const pay = () =>
    start(async () => {
      setMessage(undefined);
      const opened = await payInvoice(invoice, account, key.take(), 'portal');
      key.answered();
      if (!opened.ok) {
        setMessage(opened.message ?? t('problem'));
        return;
      }
      setGoing(true);
      window.location.assign(opened.url);
    });

  return (
    <div className="space-y-3" data-pay>
      {accounts.length > 1 ? (
        <fieldset>
          <legend className="mb-2 text-sm font-semibold">{t('pickGateway')}</legend>
          <div className="grid gap-2 sm:grid-cols-2">
            {accounts.map((a) => (
              <label key={a.account_id} className={join('flex cursor-pointer items-start gap-3 rounded-control border p-3', account === a.account_id ? 'border-brand bg-brand-soft' : 'border-line bg-white hover:border-brand-line')}>
                <input type="radio" name={`gw-${invoice}`} value={a.account_id} checked={account === a.account_id} onChange={() => setAccount(a.account_id)} className="mt-1 accent-[var(--color-brand)]" />
                <span>
                  <span className="block font-semibold">{a.label}</span>
                  {a.methods.length > 0 ? <span className="block text-sm text-muted">{a.methods.join(' · ')}</span> : null}
                </span>
              </label>
            ))}
          </div>
        </fieldset>
      ) : null}
      <Button type="button" onClick={pay} disabled={pending || going} wide className="min-h-12 text-base">
        <Icon name="lock" size={18} />
        {going ? t('going') : pending ? t('opening') : t('payAmount', { amount })}
      </Button>
      <p className="text-center text-xs text-muted">{accounts.length === 1 && accounts[0]!.methods.length > 0 ? `${accounts[0]!.methods.join(' · ')} · ` : ''}{t('secure')}</p>
      {message ? <p role="alert" className="text-sm font-semibold text-danger">{message}</p> : null}
    </div>
  );
}

/**
 * The coupon before paying: the trial's offer stays on (said so, nothing to
 * type), another coupon comes off with one press, else a code is typed; or
 * why not now, in the payer's words. The invoice is issued again either way.
 */
export function CouponBox({ invoice, course, started }: { invoice: InvoiceSummary; course?: string; started?: boolean }) {
  const t = useTranslations('invoice.coupon');
  const router = useRouter();
  const [code, setCode] = useState('');
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();
  const key = usePressKey();
  const coupon = invoice.coupon;

  const send = (typed: string | null) =>
    start(async () => {
      setMessage(undefined);
      const done = await couponOnInvoice(invoice.id, typed, key.take());
      key.answered();
      if (!done.ok) {
        setMessage(done.message ?? t('problem'));
        return;
      }
      try {
        if (course) window.sessionStorage.setItem(INVOICE_COURSE_KEY(done.invoice), course);
      } catch {
        // The welcome then shows what the invoice says.
      }
      const query = new URLSearchParams({ ...(course ? { course } : {}), ...(started ? { started: '1' } : {}), coupon: typed === null ? 'removed' : 'applied' });
      router.replace(`/family/invoices/${done.invoice}?${query.toString()}`, { scroll: false });
    });

  if (coupon?.personal) {
    return (
      <div className="flex items-start gap-3 rounded-control border border-attention/40 bg-attention-soft p-3" data-coupon="offer">
        <Icon name="spark" className="mt-0.5 shrink-0 text-attention" />
        <div className="text-sm">
          <p className="font-semibold">{t('offerOn')}</p>
          <p className="text-ink/80">{t('offerStays')}</p>
        </div>
      </div>
    );
  }
  if (coupon) {
    return (
      <div className="space-y-2" data-coupon="on">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-control border border-brand-line bg-brand-soft p-3">
          <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong"><Icon name="check" size={18} />{t('on', { code: coupon.code })}</p>
          {invoice.coupon_entry.open ? (
            <button type="button" onClick={() => send(null)} disabled={pending} className={join(buttonLook('quiet'), 'min-h-10 px-3 text-sm')}>{pending ? t('working') : t('remove')}</button>
          ) : null}
        </div>
        {message ? <p role="alert" className="text-sm font-semibold text-danger">{message}</p> : null}
      </div>
    );
  }
  if (!invoice.coupon_entry.open) {
    return invoice.coupon_entry.why_not ? <p className="text-sm text-muted" data-coupon="closed">{invoice.coupon_entry.why_not}</p> : null;
  }
  return (
    <form
      className="space-y-2"
      data-coupon="entry"
      onSubmit={(e) => {
        e.preventDefault();
        const typed = code.trim();
        if (!typed) {
          setMessage(t('empty'));
          return;
        }
        send(typed);
      }}
    >
      <label htmlFor={`coupon-${invoice.id}`} className="block text-sm font-semibold">{t('label')}</label>
      <div className="flex gap-2">
        <Input id={`coupon-${invoice.id}`} value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} autoComplete="off" autoCapitalize="characters" spellCheck={false} maxLength={32} dir="ltr" placeholder={t('placeholder')} className="font-mono uppercase" aria-describedby={`coupon-hint-${invoice.id}`} />
        <Button type="submit" variant="quiet" disabled={pending} className="shrink-0">{pending ? t('working') : t('apply')}</Button>
      </div>
      <p id={`coupon-hint-${invoice.id}`} className="text-xs text-muted">{t('hint')}</p>
      {message ? <p role="alert" className="text-sm font-semibold text-danger">{message}</p> : null}
    </form>
  );
}

/** Call off a course the payer started, before paying: a confirmation that says what happens, then the API's words. */
export function CancelStart({ enrollment, compact, after }: { enrollment: string; compact?: boolean; /** Where to go once called off (the page then says so), else said here. */ after?: string }) {
  const t = useTranslations('invoice.cancel');
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState<string>();
  const [done, setDone] = useState<string>();
  const [pending, start] = useTransition();
  const key = usePressKey();

  const cancel = () =>
    start(async () => {
      setMessage(undefined);
      const answer = await cancelStart(enrollment, key.take());
      key.answered();
      if (answer.ok) {
        setOpen(false);
        if (after) {
          router.replace(after, { scroll: false });
          return;
        }
        setDone(answer.message);
        return;
      }
      setMessage(answer.message ?? t('problem'));
    });

  if (done) {
    return (
      <div role="status" className="w-full rounded-card border border-brand-line bg-brand-soft p-4 text-sm" data-cancelled>
        <p className="flex items-start gap-2 font-semibold text-brand-strong"><Icon name="check" size={18} className="mt-0.5 shrink-0" />{done}</p>
        <Link href="/family/start" className={join(buttonLook('primary'), 'mt-3 min-h-10 px-4 text-sm')}>{t('startAgain')}</Link>
      </div>
    );
  }
  return (
    <>
      <button type="button" onClick={() => setOpen(true)} className={join(compact ? join(buttonLook('quiet'), 'min-h-10 px-4 text-sm') : 'inline-flex min-h-11 items-center gap-2 rounded-control px-3 text-sm font-semibold text-danger hover:bg-danger-soft')}>
        {compact ? null : <Icon name="x" size={16} />}
        {t('action')}
      </button>
      <Dialog open={open} onClose={() => setOpen(false)} title={t('title')}>
        <ul className="space-y-2 text-[15px]">
          <li className="flex gap-2"><Icon name="check" size={18} className="mt-0.5 shrink-0 text-brand" />{t('lessons')}</li>
          <li className="flex gap-2"><Icon name="check" size={18} className="mt-0.5 shrink-0 text-brand" />{t('invoice')}</li>
          <li className="flex gap-2"><Icon name="check" size={18} className="mt-0.5 shrink-0 text-brand" />{t('again')}</li>
        </ul>
        {message ? <Notice tone="danger">{message}</Notice> : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="quiet" onClick={() => setOpen(false)}>{t('keep')}</Button>
          <Button variant="danger" onClick={cancel} disabled={pending}>{pending ? t('working') : t('confirm')}</Button>
        </div>
      </Dialog>
    </>
  );
}
