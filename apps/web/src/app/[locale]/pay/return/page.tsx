import type { Metadata } from 'next';
import { cookies } from 'next/headers';
import { getLocale, getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { LiveRefresh, RefreshAt } from '@/components/live-refresh';
import { CalmCard, TrialShell } from '@/components/site/trial/shell';
import { PayNow } from '@/components/start/invoice-actions';
import { InvoiceLines } from '@/components/start/invoice-lines';
import { StartedWelcome } from '@/components/start/started-welcome';
import { buttonLook, join } from '@/components/ui';
import { read } from '@/lib/api/server';
import type { FamilyLessons, Invoices } from '@/lib/family/types';
import { CARDS_PATH, cardSaved } from '@/lib/family/cards';
import { liveOf } from '@/lib/live-server';
import { momentNow } from '@/lib/site/report';
import { SESSION_COOKIE } from '@/lib/session';
import { academySite } from '@/lib/site/server';
import { payState, type PayState } from '@/lib/start/pay';
import type { InvoiceRead } from '@/lib/start/types';
import { branding } from '@/lib/viewer';
import '@/components/start/start.css';

type Props = { searchParams: Promise<Record<string, string | string[] | undefined>> };

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('payReturn');
  return { title: t('metaTitle'), robots: { index: false, follow: false }, referrer: 'no-referrer' };
}

/**
 * Back from the academy's gateway (docs/architecture/02, section 27, J3):
 * Billing sends a payment begun in the portal or the app here,
 * /{locale}/pay/return?invoice=…&checkout=… (&from=app). Signed in, the page
 * reads the invoice and that payment: paid (with the course's welcome: the
 * teacher, the weekly times, the first lesson, the way to join, the app),
 * still being confirmed (the page follows the payment's signal and looks
 * again shortly), or not completed (nothing charged; try again). Begun in the
 * app, it leads back to the app first. Without a session it says how to see
 * it, never an error page.
 */
export default async function PayReturn({ searchParams }: Props) {
  const [query, locale, t, look, jar, site] = await Promise.all([searchParams, getLocale(), getTranslations('payReturn'), branding(), cookies(), academySite()]);
  const invoice = typeof query.invoice === 'string' && UUID.test(query.invoice) ? query.invoice : null;
  const checkout = typeof query.checkout === 'string' && UUID.test(query.checkout) ? query.checkout : null;
  const fromApp = query.from === 'app';
  const path = `/pay/return?${new URLSearchParams(Object.entries({ invoice, checkout, from: fromApp ? 'app' : null }).filter((e): e is [string, string] => Boolean(e[1]))).toString()}`;
  const signedIn = jar.has(SESSION_COOKIE);
  const appLink = invoice ? `academyapp://app/open/invoice/${invoice}` : 'academyapp://app/open/home/0';

  const backToApp = fromApp ? (
    <section className="lt-card space-y-3 text-center" aria-labelledby="pr-app" data-app>
      <span className="mx-auto grid size-14 place-items-center rounded-full bg-brand-soft text-brand-strong" aria-hidden="true"><Icon name="send" size={26} /></span>
      <h1 id="pr-app" className="text-xl font-bold">{t('app.title')}</h1>
      <p className="text-ink/80">{t('app.text')}</p>
      <a href={appLink} className={join(buttonLook('primary'), 'w-full min-h-12 text-base')}>{t('app.open')}</a>
      <p className="text-xs text-muted">{t('app.hint')}</p>
    </section>
  ) : null;

  if (!invoice || !signedIn) {
    return (
      <TrialShell look={look} path={path}>
        {backToApp ?? (
          <CalmCard icon="shield" title={t('signIn.title')} text={invoice ? t('signIn.text') : t('missing')} home={`/${locale}/sign-in?next=${encodeURIComponent(`/${locale}${path}`)}`} homeLabel={t('signIn.action')} />
        )}
      </TrialShell>
    );
  }

  const found = await read<InvoiceRead>('/v1/family/invoices/{invoice}', { params: { invoice }, query: { checkout } });
  if (!found.ok) {
    return (
      <TrialShell look={look} path={path}>
        {backToApp}
        <CalmCard icon="shield" title={t('notYours.title')} text={t('notYours.text')} home={`/${locale}/family/money`} homeLabel={t('money')} />
      </TrialShell>
    );
  }
  const { data: summary, checkout: payment } = found.data;
  const state: PayState = payState(summary, payment);
  const [live, list] = await Promise.all([liveOf(), read<Invoices>('/v1/family/invoices')]);
  const row = list.ok ? list.data.data.find((i) => i.id === invoice) : undefined;
  // The first lesson to come for the paid welcome when this tab kept no start of its own.
  const student = row?.student_ids[0];
  const lessons = state === 'paid' && student ? await read<FamilyLessons>('/v1/family/lessons', { query: { learner_id: student } }) : null;
  const next = lessons?.ok ? lessons.data.data.find((l) => l.status === 'scheduled' && l.type !== 'trial') : undefined;
  const stores = site?.app && (site.app.android_url || site.app.ios_url) ? site.app : null;

  const after = (
    <div className="mt-4 space-y-3">
      <div className="flex flex-wrap gap-2">
        <a href={`/${locale}/family/lessons`} className={join(buttonLook('primary'), 'min-h-11')}><Icon name="video" size={18} />{t('paid.join')}</a>
        <a href={`/${locale}/family`} className={join(buttonLook('quiet'), 'min-h-11')}>{t('paid.home')}</a>
      </div>
      <p className="text-sm text-muted">{t('paid.joinHint')}</p>
      {stores ? (
        <div className="rounded-control bg-paper p-3 text-sm" data-stores>
          <p className="font-semibold">{t('paid.app')}</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {stores.android_url ? <a href={stores.android_url} target="_blank" rel="noopener" className={join(buttonLook('quiet'), 'min-h-10 px-3 text-sm')}>{t('paid.android')}</a> : null}
            {stores.ios_url ? <a href={stores.ios_url} target="_blank" rel="noopener" className={join(buttonLook('quiet'), 'min-h-10 px-3 text-sm')}>{t('paid.ios')}</a> : null}
          </div>
        </div>
      ) : null}
    </div>
  );

  return (
    <TrialShell look={look} path={path}>
      <LiveRefresh realtime={live.realtime} />
      {state === 'confirming' ? <RefreshAt moments={[new Date(momentNow() + 8_000).toISOString()]} /> : null}
      <div className="space-y-4" data-pay-state={state}>
        {backToApp}
        <section className="lt-card" aria-labelledby="pr-title">
          <div className="flex items-start gap-3">
            <span className={join('grid size-12 shrink-0 place-items-center rounded-full', state === 'paid' ? 'st-celebrate bg-brand text-white' : state === 'confirming' ? 'bg-info-soft text-info' : state === 'failed' ? 'bg-danger-soft text-danger' : 'bg-attention-soft text-attention')} aria-hidden="true">
              <Icon name={state === 'paid' ? 'check' : state === 'confirming' ? 'clock' : state === 'failed' ? 'x' : 'alert'} size={24} />
            </span>
            <div className="min-w-0">
              <h1 id="pr-title" className="text-xl font-bold" aria-live="polite">{payment?.text ?? t(`state.${state}`)}</h1>
              <p className="mt-1 text-ink/80">{payment?.hint ?? t(`hint.${state}`)}</p>
            </div>
          </div>
          <div className="mt-4 rounded-control bg-paper p-3">
            <p className="mb-2 text-sm font-semibold">{t('invoice', { number: summary.number })}{row?.for ? ` · ${row.for}` : ''}</p>
            <InvoiceLines invoice={summary} />
          </div>
          {state === 'failed' && summary.outstanding.amount_minor > 0 ? (
            <div className="mt-4 space-y-3" data-retry>
              {row && row.pay_with.length > 0 ? <PayNow invoice={summary.id} amount={summary.outstanding.amount} accounts={row.pay_with} saveCard={found.data.save_card} /> : null}
              <a href={`/${locale}/family/invoices/${summary.id}`} className="block text-center text-sm font-semibold text-brand-strong">{t('otherWays')}</a>
            </div>
          ) : null}
          {state === 'confirming' ? <p className="mt-3 text-sm text-muted">{t('confirmingNote')}</p> : null}
        </section>

        {state === 'paid' && cardSaved(payment) ? (
          <section className="lt-card" aria-labelledby="pr-card" data-card-saved>
            <div className="flex items-start gap-3">
              <span className="grid size-12 shrink-0 place-items-center rounded-full bg-brand-soft text-brand-strong" aria-hidden="true"><Icon name="card" size={24} /></span>
              <div className="min-w-0">
                <h2 id="pr-card" className="text-lg font-bold">{t('card.title')}</h2>
                <p className="mt-1 text-ink/80">{t('card.text')}</p>
                <a href={`/${locale}${CARDS_PATH}`} className={join(buttonLook('quiet'), 'mt-3 min-h-11 px-4 text-sm')} data-manage-cards>
                  {t('card.manage')}
                  <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
                </a>
              </div>
            </div>
          </section>
        ) : null}

        {state === 'paid' ? (
          <StartedWelcome
            course={null}
            invoice={summary.id}
            mode="paid"
            after={after}
            fallback={(
              <section className="lt-card" aria-labelledby="pr-welcome" data-welcome="plain">
                <h2 id="pr-welcome" className="text-lg font-bold">{t('paid.title')}</h2>
                {next ? <p className="mt-2" data-first>{t('paid.first', { when: next.starts_at.label, teacher: next.with })}</p> : <p className="mt-2 text-ink/80">{t('paid.text')}</p>}
                {after}
              </section>
            )}
          />
        ) : null}
      </div>
    </TrialShell>
  );
}
