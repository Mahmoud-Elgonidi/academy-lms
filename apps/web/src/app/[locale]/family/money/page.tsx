import { getTranslations } from 'next-intl/server';
import { CourseState } from '@/components/family/course-state';
import { ExtraLessons } from '@/components/family/extra-lessons';
import { PayInvoice } from '@/components/family/pay-invoice';
import { SendProof } from '@/components/family/send-proof';
import { TransferDetails } from '@/components/family/transfer-details';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Badge, Empty, Heading, Notice, SectionTitle, join, type Tone } from '@/components/ui';
import { Link, redirect } from '@/i18n/navigation';
import { get, read } from '@/lib/api/server';
import { CARDS_PATH, cardsLine, type Cards } from '@/lib/family/cards';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import { transferGroups, transferText } from '@/lib/family/transfer';
import type { Balance, Invoice, Invoices } from '@/lib/family/types';
import { courseGroup } from '@/lib/lesson-format';

const tone: Record<string, Tone> = { paid: 'brand', overdue: 'danger', partly_paid: 'attention', issued: 'info', void: 'muted', draft: 'muted' };
const proofTone: Record<string, Tone> = { pending: 'info', approved: 'brand', rejected: 'danger' };

/** What came off an invoice (the family discount, a coupon), each on its own line. */
function Discounts({ invoice }: { invoice: Invoice }) {
  const off = invoice.lines.filter((l) => l.kind === 'discount');
  if (off.length === 0) return null;
  return (
    <ul className="mt-1 space-y-0.5 text-sm text-brand-strong">
      {off.map((l, n) => (
        <li key={n} className="flex justify-between gap-3">
          <span>{l.description}</span>
          <span className="shrink-0 tabular-nums">{l.amount}</span>
        </li>
      ))}
    </ul>
  );
}

/** Where the latest proof of paying by hand stands, in the API's words. */
function ProofLine({ proof }: { proof: NonNullable<Invoice['proof']> }) {
  return <Notice tone={proofTone[proof.status] ?? 'info'}>{proof.text}</Notice>;
}

/**
 * Money, for whoever pays (the classic look only; a student's own look never
 * shows it): what is owed now with a way to pay it online or by hand with the
 * receipt (and where to transfer, as the academy wrote it for the payer's region), the lessons left for the chosen child (and extra lessons to buy on
 * a course this person pays for), and every invoice with what came off it and
 * its printable tax document.
 */
export default async function Money({ params }: { params: Promise<{ locale: string }> }) {
  const [{ locale }, t, fam] = await Promise.all([params, getTranslations('family'), family()]);
  if (!seesMoney(fam.stage)) redirect({ href: '/family', locale });
  const learner = fam.current;

  const [invoices, balance, f, bt, cardsRead, ct] = await Promise.all([
    get<Invoices>('/v1/family/invoices'),
    learner ? get<Balance>('/v1/family/balance', { query: { learner_id: learner.id } }) : Promise.resolve(null),
    getTranslations('format'),
    getTranslations('balance'),
    read<{ data: Cards }>('/v1/family/cards'),
    getTranslations('cards'),
  ]);
  // The saved card and automatic renewal (J5b): the way to its page, and saving a card while paying online.
  const cards = cardsRead.ok ? cardsRead.data.data : null;
  const saveCard = cards ? { offered: true, version: cards.mandate.version, text: cards.mandate.text } : null;
  const line = cards ? cardsLine(cards) : null;
  const owed = invoices.data.filter((i) => i.outstanding_minor > 0);
  // Where to transfer, once per text: named by invoice only when the payer's invoices go to different places.
  const transfers = transferGroups(owed, fam.stage);
  const title = (await getTranslations('nav.classic'))('money');

  return (
    <div>
      <Heading title={title} hint={invoices.owed ? `${t('owed')}: ${invoices.owed}` : t('nothingOwed')} />

      {owed.length > 0 ? (
        <section aria-labelledby="owed">
          <SectionTitle id="owed">{t('owedNow')}</SectionTitle>
          {transfers.length > 0 ? (
            <div className="mb-3 space-y-2">
              {transfers.map((g) => (
                <TransferDetails key={g.text} text={g.text} numbers={transfers.length > 1 ? g.numbers : []} />
              ))}
            </div>
          ) : null}
          <ul className="stagger space-y-3">
            {owed.map((i) => (
              <li key={i.id}>
                <article className={join('rounded-card border bg-white p-5', i.status === 'overdue' ? 'border-danger/40' : 'border-brand-line')}>
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="text-sm text-muted">{i.due_at ? t('invoiceDue', { number: i.number, when: i.due_at.label }) : i.number}</p>
                      <p className="font-semibold">{i.for}</p>
                      <Discounts invoice={i} />
                      <Link href={`/family/invoices/${i.id}`} className="mt-1 inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-brand-strong" data-invoice-details={i.id}>
                        {t('invoiceDetails')}
                        <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
                      </Link>
                    </div>
                    <div className="text-end">
                      <p className="font-heading text-2xl font-bold tabular-nums">{i.outstanding}</p>
                      <Badge tone={tone[i.status] ?? 'muted'}>{i.status_label}</Badge>
                    </div>
                  </div>
                  <div className="mt-4 space-y-3 border-t border-line pt-4">
                    {i.proof ? <ProofLine proof={i.proof} /> : null}
                    {i.pay_with.length > 0 || !i.can_send_proof ? <PayInvoice invoice={i} saveCard={saveCard} /> : null}
                    <SendProof invoice={i} manual={invoices.manual} transfer={transferText(i, fam.stage)} primary={i.pay_with.length === 0} canSend={i.can_send_proof} />
                  </div>
                </article>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {line ? (
        <Link href={CARDS_PATH} className="lift mt-5 flex items-center gap-3 rounded-card border border-line bg-white p-4 hover:border-brand-line" data-cards-entry={line.state}>
          <span className={join('grid size-11 shrink-0 place-items-center rounded-full', line.state === 'on' ? 'bg-brand text-white' : 'bg-brand-soft text-brand-strong')} aria-hidden="true">
            <Icon name={line.state === 'on' ? 'renew' : 'card'} size={22} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-semibold">{ct('title')}</span>
            <span className="block text-sm text-muted">{line.card ? ct(`entry.${line.state}`, { card: line.card }) : ct('entry.none')}</span>
          </span>
          <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
        </Link>
      ) : null}

      {learner && balance ? (
        <section aria-labelledby="left">
          <SectionTitle id="left">{learner.is_self ? t('lessonsLeftTitle') : `${t('lessonsLeftTitle')} · ${learner.name}`}</SectionTitle>
          {balance.data.length === 0 ? (
            <Empty>{t('lessonsLeft', { n: 0 })}</Empty>
          ) : (
            <ul className="grid gap-3 sm:grid-cols-2">
              {balance.data.map((b) => {
                const group = courseGroup(b);
                return (
                <li key={b.enrollment_id} data-format={group ? 'group' : 'private'} className={join('rounded-card border bg-white p-4', group ? 'border-group-line border-s-4 border-s-group' : 'border-line')}>
                  <p className="text-sm text-muted">
                    {b.subject}
                    {b.shared ? ` · ${t('sharedPackage')}` : ''}
                    {group ? ` · ${f('groupPlan')}` : ''}
                  </p>
                  {group ? (
                    <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                      <FormatBadge format="group" seats={{ taken: group.taken, capacity: group.capacity }} />
                      {group.seat ? <span className="font-semibold text-group-strong">{f('seatNumber', { seat: group.seat, capacity: group.capacity })}</span> : null}
                    </p>
                  ) : null}
                  <CourseState course={b} />
                  <p className="mt-1 font-heading text-2xl font-bold">{t('lessonsLeft', { n: b.sessions ?? 0 })}</p>
                  <p className="text-sm tabular-nums text-muted">{t('minutesLeft', { n: b.minutes })}</p>
                  <Link href={`/family/progress/${b.enrollment_id}#balance-${b.enrollment_id}`} className="mt-1 inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-brand-strong" data-balance-details={b.enrollment_id}>
                    {bt('details')}
                    <Icon name="chevron" size={16} className="rtl:-scale-x-100" />
                  </Link>
                  {b.extra ? <ExtraLessons enrollment={b.enrollment_id} learnerId={learner.id} subject={b.subject ?? ''} offer={b.extra} /> : null}
                </li>
                );
              })}
            </ul>
          )}
        </section>
      ) : null}

      <section aria-labelledby="invoices">
        <SectionTitle id="invoices">{t('invoices')}</SectionTitle>
        {invoices.data.length === 0 ? (
          <Empty>{t('noInvoices')}</Empty>
        ) : (
          <>
            <p className="mb-3 text-sm text-muted">{t('invoiceNote')}</p>
            <ul className="divide-y divide-line overflow-hidden rounded-card border border-line bg-white">
              {invoices.data.map((i) => (
                <li key={i.id} className="px-4 py-3">
                  {/* The whole row opens the invoice's own page (its lines, paying it, its document). */}
                  <Link href={`/family/invoices/${i.id}`} className="-mx-2 flex items-start justify-between gap-3 rounded-control px-2 py-1 hover:bg-paper" data-invoice-row={i.id}>
                    <div className="min-w-0">
                      <p className="flex flex-wrap items-center gap-2">
                        <span className="whitespace-nowrap font-semibold tabular-nums">{i.number}</span>
                        {i.family ? <Badge tone="muted">{t('familyInvoice')}</Badge> : null}
                      </p>
                      <p className="text-sm text-muted">
                        {i.for}
                        {i.issued_at ? ` · ${i.issued_at.label}` : ''}
                      </p>
                      <Discounts invoice={i} />
                    </div>
                    <span className="flex shrink-0 items-center gap-1">
                      <span className="font-semibold tabular-nums">{i.total}</span>
                      <Icon name="chevron" size={16} className="text-muted rtl:-scale-x-100" />
                    </span>
                  </Link>
                  {i.proof && (i.proof.status !== 'rejected' || i.outstanding_minor > 0) ? (
                    <p className={join('mt-1 text-sm', i.proof.status === 'rejected' ? 'text-danger' : 'text-muted')}>{i.proof.text}</p>
                  ) : null}
                  <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <Badge tone={tone[i.status] ?? 'muted'}>{i.status_label}</Badge>
                    {i.document_url ? (
                      <a
                        href={i.document_url}
                        target="_blank"
                        rel="noopener"
                        className="inline-flex min-h-10 items-center gap-1.5 rounded-control px-2 text-sm font-semibold text-brand-strong hover:bg-brand-soft"
                      >
                        <Icon name="file" size={16} />
                        {t('openInvoice')}
                      </a>
                    ) : null}
                  </div>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>
    </div>
  );
}
