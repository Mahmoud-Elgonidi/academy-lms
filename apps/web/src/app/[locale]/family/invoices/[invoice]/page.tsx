import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Icon } from '@/components/icons';
import { CancelStart, CouponBox, PayNow } from '@/components/start/invoice-actions';
import { InvoiceLines } from '@/components/start/invoice-lines';
import { StartedWelcome } from '@/components/start/started-welcome';
import { Badge, Notice, buttonLook, join, type Tone } from '@/components/ui';
import { Link, redirect } from '@/i18n/navigation';
import { get, read } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import type { Invoices } from '@/lib/family/types';
import type { InvoiceRead } from '@/lib/start/types';
import '@/components/start/start.css';

type Props = { params: Promise<{ locale: string; invoice: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> };

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const tone: Record<string, Tone> = { paid: 'brand', overdue: 'danger', partly_paid: 'attention', issued: 'info', void: 'muted' };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const t = await getTranslations('invoice');
  return { title: t('metaTitle', { number: (await params).invoice.slice(0, 8) }) };
}

/**
 * One invoice before paying, for whoever pays it (the classic look): the
 * course just started (its weekly times and first lesson, from this tab's
 * start), what the invoice is for line by line with its discounts and VAT,
 * a coupon typed or taken off (the invoice comes out again, so the page goes
 * to the new one), paying online with the way back to the portal, the other
 * ways to pay, and calling off a course the family started itself while
 * nothing is paid. Refreshes itself when a payment settles (payments.changed).
 */
export default async function InvoicePage({ params, searchParams }: Props) {
  const [{ locale, invoice }, query, t, fam] = await Promise.all([params, searchParams, getTranslations('invoice'), family()]);
  if (!seesMoney(fam.stage)) redirect({ href: '/family', locale });
  if (!UUID.test(invoice)) notFound();
  const course = typeof query.course === 'string' && UUID.test(query.course) ? query.course : null;
  const started = query.started === '1';
  const coupon = query.coupon === 'applied' || query.coupon === 'removed' ? query.coupon : null;

  const [found, list] = await Promise.all([read<InvoiceRead>('/v1/family/invoices/{invoice}', { params: { invoice } }), get<Invoices>('/v1/family/invoices')]);
  if (!found.ok) notFound();
  const summary = found.data.data;
  const row = list.data.find((i) => i.id === invoice);
  const owed = summary.outstanding.amount_minor > 0 && summary.status !== 'void';
  const nothingPaid = summary.paid.amount_minor === 0;
  const payWith = row?.pay_with ?? [];

  return (
    <div className="space-y-5" data-invoice={summary.id}>
      <Link href="/family/money" className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-brand">
        <Icon name="chevron" size={18} className="ltr:-scale-x-100" />{t('back')}
      </Link>

      {started && owed ? (
        <StartedWelcome
          course={course}
          invoice={summary.id}
          mode="waiting"
          fallback={(
            <section className="rounded-card border border-brand-line bg-white p-5" data-welcome="plain">
              <h2 className="text-xl font-bold">{t('welcome.plainTitle')}</h2>
              <p className="mt-1 text-muted">{t('welcome.plainText')}</p>
            </section>
          )}
        />
      ) : null}

      {query.cancelled === '1' && summary.status === 'void' ? (
        <section role="status" className="rounded-card border border-brand-line bg-brand-soft p-5" data-cancelled>
          <p className="flex items-start gap-2 font-semibold text-brand-strong"><Icon name="check" size={20} className="mt-0.5 shrink-0" />{t('cancel.done')}</p>
          <Link href="/family/start" className={join(buttonLook('primary'), 'mt-3 min-h-10 px-4 text-sm')}>{t('cancel.startAgain')}</Link>
        </section>
      ) : null}
      {coupon ? <Notice tone="brand">{coupon === 'applied' ? t('coupon.applied') : t('coupon.removed')}</Notice> : null}
      {summary.status === 'void' && query.cancelled !== '1' ? <Notice tone="attention">{t('void')}</Notice> : null}

      <section className="rounded-card border border-line bg-white p-5" aria-labelledby="inv-title">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-sm text-muted">{t('kicker')}</p>
            <h1 id="inv-title" className="text-xl font-bold tabular-nums">{t('title', { number: summary.number })}</h1>
            {row?.for ? <p className="mt-0.5 text-[15px]">{row.for}</p> : null}
          </div>
          <div className="text-end">
            <Badge tone={tone[summary.status] ?? 'muted'}>{row?.status_label ?? summary.status}</Badge>
            {summary.due_at && owed ? <p className="mt-1 text-xs text-muted">{t('due', { when: summary.due_at.label })}</p> : null}
          </div>
        </div>
        <div className="mt-4"><InvoiceLines invoice={summary} /></div>
        {owed ? <div className="mt-4 border-t border-line pt-4"><CouponBox invoice={summary} course={course ?? undefined} started={started} /></div> : null}
      </section>

      {owed ? (
        <section className="rounded-card border border-brand-line bg-white p-5" aria-labelledby="inv-pay">
          <h2 id="inv-pay" className="mb-3 font-bold">{t('payTitle')}</h2>
          {payWith.length > 0 ? <PayNow invoice={summary.id} amount={summary.outstanding.amount} accounts={payWith} /> : <p className="text-sm text-muted">{t('noOnline')}</p>}
          <p className="mt-4 text-sm">
            <Link href="/family/money" className="font-semibold text-brand-strong underline-offset-4 hover:underline">{t('otherWays')}</Link>
          </p>
        </section>
      ) : query.cancelled === '1' ? null : (
        <section className="rounded-card border border-brand-line bg-brand-soft p-5 text-center" data-paid>
          <p className="flex items-center justify-center gap-2 font-bold text-brand-strong"><Icon name="check" />{summary.status === 'void' ? t('voidShort') : t('paidAll')}</p>
          <Link href="/family/lessons" className={join(buttonLook('quiet'), 'mt-3 min-h-10 px-4 text-sm')}>{t('lessons')}</Link>
        </section>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <a href={summary.document_url} target="_blank" rel="noopener" className="inline-flex min-h-10 items-center gap-1.5 rounded-control px-2 text-sm font-semibold text-brand-strong hover:bg-brand-soft">
          <Icon name="file" size={16} />{t('document')}
        </a>
        {course && owed && nothingPaid && summary.status !== 'void' ? (
          <div className="text-end">
            <CancelStart enrollment={course} after={`/family/invoices/${summary.id}?cancelled=1`} />
            <p className="text-xs text-muted">{t('cancelHint')}</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}
