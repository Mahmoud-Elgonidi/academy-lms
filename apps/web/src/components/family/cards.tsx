'use client';

import { useTranslations } from 'next-intl';
import { useId, useOptimistic, useState, useTransition } from 'react';
import { removeCard, setAutoRenew } from '@/app/[locale]/family/actions';
import { Dialog } from '@/components/dialog';
import { Icon } from '@/components/icons';
import { usePressKey } from '@/components/start/press-key';
import { Badge, Button, Notice, buttonLook, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { cardExpiry, cardsState, chargeLook, chargeUnderWay, removalStopsRenewal, type Cards, type SavedCard } from '@/lib/family/cards';

/*
 * The payer's saved card and automatic renewal (docs/architecture/02,
 * section 27, J5b): the switch per account (turning it off says what happens
 * first; on needs a card), the card as a card (brand, last four, expiry, a
 * warning when it expires soon) with the way to remove it (said first too),
 * the charges coming, and how it works. Each write sends one key per press
 * and shows the API's sentence; the page then reads again.
 */

type Said = { tone: 'brand' | 'danger'; text: string };

export function CardsManager({ cards }: { cards: Cards }) {
  const t = useTranslations('cards');
  const [said, setSaid] = useState<Said>();
  const state = cardsState(cards);
  const several = cards.accounts.length > 1;

  return (
    <div className="space-y-5">
      <div aria-live="polite">{said ? <Notice tone={said.tone}>{said.text}</Notice> : null}</div>

      {state === 'none' ? (
        <section className="rounded-card border border-dashed border-brand-line bg-white p-6 text-center" data-cards-empty>
          <span className="mx-auto grid size-14 place-items-center rounded-full bg-brand-soft text-brand-strong" aria-hidden="true"><Icon name="card" size={28} /></span>
          <h2 className="mt-3 text-lg font-bold">{t('empty.title')}</h2>
          <p className="mx-auto mt-1 max-w-md text-[15px] leading-7 text-ink/80">{t('empty.text')}</p>
          <p className="mx-auto mt-3 inline-flex max-w-md items-start gap-2 rounded-control bg-paper px-3 py-2 text-start text-sm">
            <Icon name="check" size={18} className="mt-0.5 shrink-0 text-brand" />
            <span>{t('empty.box')}</span>
          </p>
          <div className="mt-4">
            <Link href="/family/money" className={join(buttonLook('primary'), 'min-h-11 px-5')}>{t('empty.toMoney')}</Link>
          </div>
        </section>
      ) : (
        <>
          <section className="space-y-3" aria-label={t('renewal.title')}>
            {cards.accounts.map((account) => (
              <RenewalSwitch key={account.id} account={account} cards={cards} currency={several ? account.currency : null} onSaid={setSaid} />
            ))}
          </section>

          <section aria-labelledby="cards-saved">
            <h2 id="cards-saved" className="mb-3 text-sm font-semibold text-muted">{cards.cards.length > 1 ? t('savedMany') : t('saved')}</h2>
            <ul className="grid gap-4 sm:grid-cols-2">
              {cards.cards.map((card) => (
                <li key={card.id}>
                  <CardTile card={card} cards={cards} onSaid={setSaid} />
                </li>
              ))}
            </ul>
          </section>
        </>
      )}

      {cards.coming.length > 0 ? <Coming cards={cards} /> : null}

      <HowItWorks />
    </div>
  );
}

/** One account's automatic renewal, as a switch with what it means now. */
function RenewalSwitch({ account, cards, currency, onSaid }: { account: Cards['accounts'][number]; cards: Cards; currency: string | null; onSaid: (said: Said) => void }) {
  const t = useTranslations('cards');
  const title = useId();
  const hint = useId();
  const [asking, setAsking] = useState(false);
  const [pending, start] = useTransition();
  const [on, setOn] = useOptimistic(account.auto_renew);
  const key = usePressKey();
  const card = cards.cards.find((c) => c.id === account.card_id) ?? null;
  const underWay = chargeUnderWay(cards);

  const send = (next: boolean) =>
    start(async () => {
      setOn(next);
      const done = await setAutoRenew(next, cards.accounts.length > 1 ? account.id : null, key.take());
      key.answered();
      setAsking(false);
      onSaid(done.ok ? { tone: 'brand', text: done.message } : { tone: 'danger', text: done.message ?? t('problem') });
    });

  return (
    <div className={join('rounded-card border bg-white p-5', on ? 'border-brand-line' : 'border-line')} data-renewal={on ? 'on' : 'off'} data-account={account.id}>
      <div className="flex items-start justify-between gap-4">
        <div className="flex min-w-0 items-start gap-3">
          <span className={join('grid size-11 shrink-0 place-items-center rounded-full', on ? 'bg-brand text-white' : 'bg-paper text-muted')} aria-hidden="true">
            <Icon name="renew" size={22} />
          </span>
          <div className="min-w-0">
            <h2 id={title} className="font-heading text-lg font-bold">
              {currency ? t('renewal.titleIn', { currency }) : t('renewal.title')}
            </h2>
            <p className="mt-0.5"><Badge tone={on ? 'brand' : 'muted'}>{on ? t('renewal.on') : t('renewal.off')}</Badge></p>
          </div>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={on}
          aria-labelledby={title}
          aria-describedby={hint}
          disabled={pending || (!on && card === null)}
          onClick={() => (on ? setAsking(true) : send(true))}
          className={join('relative mt-1 h-8 w-14 shrink-0 rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-60', on ? 'bg-brand' : 'bg-line')}
          data-auto-renew-switch
        >
          <span aria-hidden="true" className={join('absolute start-1 top-1 size-6 rounded-full bg-white shadow motion-safe:transition-transform', on && 'translate-x-6 rtl:-translate-x-6')} />
        </button>
      </div>
      <p id={hint} className="mt-3 text-[15px] leading-7 text-ink/85" data-renewal-text>
        {on && card ? t('renewal.onText', { card: card.label }) : card ? t('renewal.offText') : t('renewal.noCard')}
      </p>

      <Dialog open={asking} onClose={() => setAsking(false)} title={t('off.title')}>
        <ul className="space-y-2 text-[15px] leading-7">
          <li className="flex gap-2"><Icon name="wallet" size={18} className="mt-1 shrink-0 text-brand" />{t('off.byLink')}</li>
          <li className="flex gap-2"><Icon name="x" size={18} className="mt-1 shrink-0 text-brand" />{t('off.coming')}</li>
          {underWay ? <li className="flex gap-2"><Icon name="clock" size={18} className="mt-1 shrink-0 text-attention" />{t('off.underWay')}</li> : null}
          <li className="flex gap-2"><Icon name="card" size={18} className="mt-1 shrink-0 text-brand" />{t('off.cardStays')}</li>
        </ul>
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="quiet" onClick={() => setAsking(false)}>{t('off.keep')}</Button>
          <Button variant="danger" onClick={() => send(false)} disabled={pending} data-confirm-off>{pending ? t('working') : t('off.confirm')}</Button>
        </div>
      </Dialog>
    </div>
  );
}

/** The card as a card: brand, last four, expiry, where it is kept; a warning when it expires soon; removing it, said first. */
function CardTile({ card, cards, onSaid }: { card: SavedCard; cards: Cards; onSaid: (said: Said) => void }) {
  const t = useTranslations('cards');
  const [asking, setAsking] = useState(false);
  const [pending, start] = useTransition();
  const key = usePressKey();
  const stops = removalStopsRenewal(cards, card.id);

  const remove = () =>
    start(async () => {
      const done = await removeCard(card.id, key.take());
      key.answered();
      setAsking(false);
      onSaid(done.ok ? { tone: 'brand', text: done.message } : { tone: 'danger', text: done.message ?? t('problem') });
    });

  return (
    <div className="space-y-3" data-card={card.id}>
      <div className="relative flex aspect-[1.586] max-w-sm flex-col justify-between overflow-hidden rounded-card bg-brand p-5 text-white shadow-sm">
        <span className="pointer-events-none absolute -end-10 -top-12 size-40 rounded-full bg-white/10" aria-hidden="true" />
        <span className="pointer-events-none absolute -bottom-16 -start-8 size-44 rounded-full bg-black/10" aria-hidden="true" />
        <div className="relative flex items-start justify-between gap-2">
          <span className="text-lg font-bold" data-card-brand>{card.brand_label}</span>
          <Icon name="card" size={26} className="opacity-80" />
        </div>
        <p className="relative font-mono text-xl tracking-[0.2em] tabular-nums sm:text-2xl" dir="ltr" aria-label={t('tile.number', { last4: card.last4 })} data-card-last4>
          •••• {card.last4}
        </p>
        <div className="relative flex items-end justify-between gap-2 text-sm">
          <span className="min-w-0 truncate opacity-90">{card.account}</span>
          <span className="shrink-0 text-end">
            <span className="block text-[11px] opacity-80">{t('tile.expires')}</span>
            <span className="font-semibold tabular-nums" dir="ltr" data-card-expiry>{cardExpiry(card)}</span>
          </span>
        </div>
      </div>

      {card.expires_soon ? (
        <div className="flex max-w-sm items-start gap-2 rounded-control border border-attention/40 bg-attention-soft px-3 py-2.5 text-sm leading-6" data-card-expiring>
          <Icon name="alert" size={18} className="mt-0.5 shrink-0 text-attention" />
          <span><strong className="block font-semibold">{t('tile.soonTitle')}</strong>{t('tile.soonText')}</span>
        </div>
      ) : null}

      <div className="flex max-w-sm flex-wrap items-center justify-between gap-2">
        {card.saved_at ? <p className="text-xs text-muted">{t('tile.savedAt', { when: card.saved_at.label })}</p> : <span />}
        <button type="button" onClick={() => setAsking(true)} className="inline-flex min-h-11 items-center gap-1.5 rounded-control px-3 text-sm font-semibold text-danger hover:bg-danger-soft" data-remove-card>
          <Icon name="x" size={16} />
          {t('remove.action')}
        </button>
      </div>

      <Dialog open={asking} onClose={() => setAsking(false)} title={t('remove.title', { card: card.label })}>
        <ul className="space-y-2 text-[15px] leading-7">
          <li className="flex gap-2"><Icon name="check" size={18} className="mt-1 shrink-0 text-brand" />{t('remove.nothing')}</li>
          {stops ? <li className="flex gap-2"><Icon name="renew" size={18} className="mt-1 shrink-0 text-attention" />{t('remove.renewalOff')}</li> : null}
          <li className="flex gap-2"><Icon name="wallet" size={18} className="mt-1 shrink-0 text-brand" />{t('remove.byLink')}</li>
          <li className="flex gap-2"><Icon name="card" size={18} className="mt-1 shrink-0 text-brand" />{t('remove.again')}</li>
        </ul>
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="quiet" onClick={() => setAsking(false)}>{t('remove.keep')}</Button>
          <Button variant="danger" onClick={remove} disabled={pending} data-confirm-remove>{pending ? t('working') : t('remove.confirm')}</Button>
        </div>
      </Dialog>
    </div>
  );
}

/** The automatic charges coming: which invoice, how much, when (as the API wrote it), and how each stands. */
function Coming({ cards }: { cards: Cards }) {
  const t = useTranslations('cards');
  return (
    <section aria-labelledby="cards-coming">
      <h2 id="cards-coming" className="mb-3 text-sm font-semibold text-muted">{t('coming.title')}</h2>
      <ul className="divide-y divide-line overflow-hidden rounded-card border border-line bg-white">
        {cards.coming.map((charge) => {
          const look = chargeLook(charge.status);
          return (
            <li key={charge.invoice_id} className="p-4" data-charge={look.key}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-sm text-muted tabular-nums">{t('coming.invoice', { number: charge.number })}</p>
                  <p className="font-semibold">{charge.at ? t(`coming.when.${look.key}`, { when: charge.at.label }) : t(`coming.now.${look.key}`)}</p>
                </div>
                <div className="text-end">
                  <p className="font-heading text-xl font-bold tabular-nums">{charge.amount}</p>
                  <Badge tone={look.tone}>{t(`coming.status.${look.key}`)}</Badge>
                </div>
              </div>
              {look.key === 'retry' ? (
                <p className="mt-2 flex items-start gap-2 rounded-control bg-attention-soft px-3 py-2 text-sm leading-6" data-retry-note>
                  <Icon name="clock" size={18} className="mt-0.5 shrink-0 text-attention" />
                  <span>{t('coming.retryNote')}</span>
                </p>
              ) : look.key === 'charging' ? (
                <p className="mt-2 text-sm text-muted">{t('coming.chargingNote')}</p>
              ) : null}
              <Link href={`/family/invoices/${charge.invoice_id}`} className="mt-2 inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-brand-strong">
                {look.key === 'retry' ? t('coming.payNow') : t('coming.open')}
                <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

/** How automatic renewal works, in three steps. */
function HowItWorks() {
  const t = useTranslations('cards.how');
  const steps = ['tell', 'charge', 'stop'] as const;
  return (
    <section aria-labelledby="cards-how" className="rounded-card border border-line bg-paper/60 p-5">
      <h2 id="cards-how" className="font-bold">{t('title')}</h2>
      <ol className="mt-3 space-y-3">
        {steps.map((step, n) => (
          <li key={step} className="flex items-start gap-3">
            <span className="grid size-7 shrink-0 place-items-center rounded-full bg-brand-soft text-sm font-bold text-brand-strong">{n + 1}</span>
            <span className="text-[15px] leading-7">{t(step)}</span>
          </li>
        ))}
      </ol>
      <p className="mt-3 flex items-start gap-2 text-sm text-muted"><Icon name="lock" size={16} className="mt-0.5 shrink-0" />{t('safe')}</p>
    </section>
  );
}
