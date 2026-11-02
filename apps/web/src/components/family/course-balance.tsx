import { useTranslations } from 'next-intl';
import type { ReactNode } from 'react';
import { Icon, type IconName } from '@/components/icons';
import { Badge, buttonLook, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { STATE_TONE, balanceBar, balanceLook, balanceRows, balanceState, kidsDots, type BalanceRow, type Tone } from '@/lib/family/balance';
import type { CourseBalance as Data } from '@/lib/family/types';

/*
 * A course's balance (docs/architecture/02, section 27, J5a), in the look of
 * the person reading it: a child reads how many lessons are left in a few
 * words with a dot for each; a student reads the bar, the plan and when the
 * lessons expire; whoever pays also reads the invoice waiting (with the way
 * to pay it) or when the next one goes out and about how much. The
 * sentences, days and sums are the API's, written as it wrote them.
 */

const BORDER: Record<Tone, string> = {
  brand: 'border-line',
  attention: 'border-attention/40',
  danger: 'border-danger/40',
  info: 'border-info/30',
  muted: 'border-line',
};
const FILL: Record<Tone, string> = {
  brand: 'bg-brand',
  attention: 'bg-attention',
  danger: 'bg-danger',
  info: 'bg-info',
  muted: 'bg-muted',
};
const SOFT: Record<Tone, string> = {
  brand: 'bg-brand-soft text-brand-strong',
  attention: 'bg-attention-soft text-attention',
  danger: 'bg-danger-soft text-danger',
  info: 'bg-info-soft text-info',
  muted: 'bg-paper text-muted',
};
const ROW_ICON: Record<BalanceRow['kind'], IconName> = { waiting: 'wallet', expiry: 'clock', next: 'calendar', note: 'leave' };

export function CourseBalanceCard({ balance, subject, href, className }: {
  balance: Data;
  /** The course's subject (from the family's balance list or the journey), as a kicker. */
  subject?: string | null;
  /** The course's own page, when the card stands elsewhere (the home). */
  href?: string;
  className?: string;
}) {
  if (balanceLook(balance) === 'kids') return <KidsBalance balance={balance} subject={subject} className={className} />;
  return <FullBalance balance={balance} subject={subject} href={href} className={className} />;
}

/** The kids' look: the lessons left in the API's simple words, and a dot for each. */
function KidsBalance({ balance, subject, className }: { balance: Data; subject?: string | null; className?: string }) {
  const t = useTranslations('balance');
  const { dots, more } = kidsDots(balance.lessons_left);
  const none = (balance.lessons_left ?? 0) === 0;
  return (
    <section
      id={`balance-${balance.enrollment_id}`}
      data-balance="kids"
      data-state={none ? 'empty' : 'some'}
      className={join('scroll-mt-24 rounded-card border-2 border-brand-line bg-brand-soft p-5', className)}
      aria-labelledby={`bal-${balance.enrollment_id}`}
    >
      <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong">
        <Icon name="star" size={18} fill="currentColor" />
        {subject ? t('kidsOf', { subject }) : t('kidsTitle')}
      </p>
      <h2 id={`bal-${balance.enrollment_id}`} className="mt-1 font-heading text-2xl font-bold" data-lessons-text>
        {balance.lessons_text ?? t('kidsNone')}
      </h2>
      {dots > 0 ? (
        <p className="mt-3 flex flex-wrap items-center gap-1.5" aria-hidden="true">
          {Array.from({ length: dots }, (_, n) => (
            <span key={n} className="pop grid size-8 place-items-center rounded-full bg-white text-brand shadow-sm" style={{ animationDelay: `${n * 40}ms` }}>
              <Icon name="star" size={18} fill="currentColor" />
            </span>
          ))}
          {more > 0 ? <span className="ms-1 text-sm font-bold text-brand-strong">+{more}</span> : null}
        </p>
      ) : null}
      <p className="mt-2 text-sm text-ink/80">{none ? t('kidsEmpty') : t('kidsHint')}</p>
    </section>
  );
}

function FullBalance({ balance, subject, href, className }: { balance: Data; subject?: string | null; href?: string; className?: string }) {
  const t = useTranslations('balance');
  const state = balanceState(balance);
  const tone = STATE_TONE[state];
  const bar = balanceBar(balance);
  const rows = balanceRows(balance);
  const plan = balance.plan;
  const id = balance.enrollment_id;

  return (
    <section
      id={`balance-${id}`}
      data-balance={balanceLook(balance)}
      data-state={state}
      className={join('scroll-mt-24 rounded-card border bg-white p-5', BORDER[tone], className)}
      aria-labelledby={`bal-${id}`}
    >
      <header className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-sm text-muted">{subject ? t('of', { subject }) : t('title')}</p>
          {plan ? <p className="mt-0.5 font-semibold" data-plan>{plan.name}</p> : null}
        </div>
        {state !== 'calm' ? <Badge tone={tone}>{t(`state.${state}`)}</Badge> : null}
      </header>

      <div className="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h2 id={`bal-${id}`} className="font-heading text-3xl font-bold tabular-nums" data-lessons-left>
          {balance.lessons_left ?? '—'}
        </h2>
        <p className="font-semibold">{balance.lessons_text ?? t('noCount')}</p>
        {bar && plan && bar.total === plan.sessions_per_block ? <p className="text-sm text-muted tabular-nums">{t('ofPackage', { n: bar.total })}</p> : null}
      </div>

      {bar ? (
        <div className="mt-3">
          <div
            role="meter"
            aria-valuemin={0}
            aria-valuemax={bar.total}
            aria-valuenow={bar.left}
            aria-label={t('meter', { left: bar.left, total: bar.total })}
            className="relative h-3 rounded-full bg-line/70"
          >
            {bar.fill > 0 ? (
              <span className={join('bar-fill absolute inset-y-0 start-0 rounded-full', FILL[tone])} style={{ width: `${Math.max(bar.fill * 100, 3)}%` }} />
            ) : null}
            {bar.mark !== null ? (
              <span className="absolute -inset-y-1 w-0.5 rounded-full bg-ink/60" style={{ insetInlineStart: `${bar.mark * 100}%` }} aria-hidden="true" />
            ) : null}
          </div>
          {bar.markAt !== null && balance.renews !== false ? (
            <p className="mt-1.5 flex items-center gap-1.5 text-xs text-muted" data-mark>
              <span className="inline-block h-3 w-0.5 rounded-full bg-ink/60" aria-hidden="true" />
              {balance.viewer_stage === 'classic' ? t('markPayer', { n: bar.markAt }) : t('markStudent', { n: bar.markAt })}
            </p>
          ) : null}
        </div>
      ) : null}

      {/* The plan's name says its shape (lessons, a week, minutes); what it does not say is said here. */}
      {plan && (plan.shared || plan.format === 'group') ? (
        <p className="mt-3 flex flex-wrap gap-1.5 text-sm">
          {plan.shared ? <Chip>{t('shared')}</Chip> : null}
          {plan.format === 'group' ? <Chip>{t('group')}</Chip> : null}
        </p>
      ) : null}
      {balance.makeup_lessons ? <p className="mt-2 text-sm text-info">{t('makeup', { n: balance.makeup_lessons })}</p> : null}
      {state === 'first_payment' ? <p className="mt-3 text-sm leading-6">{t('firstPayment')}</p> : null}
      {state === 'empty' && !balance.waiting_invoice ? <p className="mt-3 text-sm leading-6">{t('emptyNoInvoice')}</p> : null}

      {rows.length > 0 ? (
        <ul className="mt-4 space-y-2">
          {rows.map((row) => (
            <li key={row.kind}>{row.kind === 'waiting' ? <Waiting row={row} /> : <Line row={row} />}</li>
          ))}
        </ul>
      ) : null}

      {href ? (
        <Link href={href} className="mt-3 inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-brand-strong" data-balance-course>
          {t('course')}
          <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
        </Link>
      ) : null}
    </section>
  );
}

function Chip({ children }: { children: ReactNode }) {
  return <span className="rounded-full bg-paper px-2.5 py-0.5 text-ink/80">{children}</span>;
}

/** A sentence of the API's with its icon, in its tone. */
function Line({ row }: { row: Exclude<BalanceRow, { kind: 'waiting' }> }) {
  const t = useTranslations('balance');
  return (
    <div className={join('flex items-start gap-2.5 rounded-control px-3 py-2.5 text-sm leading-6', SOFT[row.tone])} data-row={row.kind}>
      <Icon name={ROW_ICON[row.kind]} size={18} className="mt-0.5 shrink-0" />
      <span>
        {row.kind === 'next' ? <strong className="block font-semibold text-ink">{t('nextTitle')}</strong> : null}
        {row.kind === 'expiry' ? <strong className="block font-semibold text-ink">{t('expiryTitle')}</strong> : null}
        <span className={row.tone === 'muted' || row.kind === 'next' ? 'text-ink/80' : undefined}>{row.text}</span>
        {row.kind === 'next' ? <span className="mt-0.5 block text-xs text-muted">{t('estimate')}</span> : null}
      </span>
    </div>
  );
}

/** The package's invoice waiting to be paid: how much, until when, and the way to pay it (its payer only). */
function Waiting({ row }: { row: Extract<BalanceRow, { kind: 'waiting' }> }) {
  const t = useTranslations('balance');
  return (
    <div className={join('rounded-control border p-3', row.tone === 'danger' ? 'border-danger/40 bg-danger-soft' : 'border-attention/40 bg-attention-soft')} data-row="waiting" data-due={row.due ?? undefined}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <p className="flex min-w-0 items-start gap-2.5 text-sm leading-6">
          <Icon name="wallet" size={18} className={join('mt-0.5 shrink-0', row.tone === 'danger' ? 'text-danger' : 'text-attention')} />
          <span>
            <strong className="block font-semibold">{t('waitingTitle')}</strong>
            <span className="text-ink/85">{row.text}</span>
          </span>
        </p>
        {row.due === 'overdue' ? <Badge tone="danger">{t('overdue')}</Badge> : row.due === 'soon' ? <Badge tone="attention">{t('dueSoon')}</Badge> : null}
      </div>
      {row.dueLabel ? <p className="mt-1 ps-7 text-sm font-semibold" data-due-label>{t('dueOn', { when: row.dueLabel })}</p> : null}
      {row.href ? (
        <Link href={row.href} className={join(buttonLook('primary'), 'mt-3 min-h-11 w-full px-4 text-sm sm:w-auto')} data-pay-invoice>
          {t('pay', { amount: row.amount })}
          <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
        </Link>
      ) : (
        <p className="mt-2 ps-7 text-xs text-muted">{t('otherPayer')}</p>
      )}
    </div>
  );
}
