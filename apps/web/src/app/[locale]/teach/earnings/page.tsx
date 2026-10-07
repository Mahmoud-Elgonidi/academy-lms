import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Card, Empty, Heading, Notice, SectionTitle, buttonLook, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { addMonths } from '@/lib/format';
import type { Earnings } from '@/lib/teach/types';

/** A month of the teacher's pay on the academy's clock (?month=YYYY-MM): totals, each lesson, and adjustments. */
export default async function EarningsPage({ searchParams }: { searchParams: Promise<{ month?: string }> }) {
  const { month: asked } = await searchParams;
  const month = asked && /^\d{4}-\d{2}$/.test(asked) ? asked : undefined;
  const [t, common, nav, pay] = await Promise.all([
    getTranslations('teach'),
    getTranslations('common'),
    getTranslations('nav'),
    get<Earnings>('/v1/teacher/earnings', { query: { month } }),
  ]);
  const adjustments = pay.adjustments.filter((a) => !a.reversed);

  const step = (to: string, label: string, back: boolean) => (
    <Link href={`/teach/earnings?month=${to}`} className={join(buttonLook('quiet'), 'min-h-10 px-3 text-sm')} aria-label={label} title={label}>
      <Icon name="chevron" size={18} className={back ? 'ltr:-scale-x-100' : 'rtl:-scale-x-100'} />
    </Link>
  );

  return (
    <div>
      <Heading
        title={pay.label}
        hint={
          <span className="inline-flex items-center gap-2">
            {nav('earnings')} <Badge tone={pay.closed ? 'brand' : 'attention'}>{pay.closed ? t('monthClosed') : t('monthOpen')}</Badge>
          </span>
        }
        action={
          <div className="flex gap-2">
            {step(addMonths(pay.month, -1), t('prevMonth'), true)}
            {step(addMonths(pay.month, 1), t('nextMonth'), false)}
          </div>
        }
      />
      {pay.totals.length === 0 ? (
        <Empty>{t('noEarnings')}</Empty>
      ) : (
        <div className="stagger space-y-3">
          {pay.totals.map((total) => (
            <Card key={total.currency} className="border-brand-line">
              <p className="text-sm text-muted">{t('total')}</p>
              <p className="font-heading text-3xl font-bold">{total.total.formatted}</p>
              <p className="mt-1 text-sm text-muted">{common('lessonsHours', { lessons: common('lessons', { n: total.lessons }), hours: common('hoursAmount', { h: total.hours }) })}</p>
              <dl className="mt-4 grid grid-cols-3 gap-3 border-t border-line pt-4 text-sm">
                <div>
                  <dt className="text-muted">{t('lessonPay')}</dt>
                  <dd className="font-semibold">{total.lesson_pay.formatted}</dd>
                </div>
                <div>
                  <dt className="text-muted">{t('bonuses')}</dt>
                  <dd className="font-semibold">{total.bonuses.formatted}</dd>
                </div>
                <div>
                  <dt className="text-muted">{t('deductions')}</dt>
                  <dd className="font-semibold">{total.deductions.formatted}</dd>
                </div>
              </dl>
            </Card>
          ))}
        </div>
      )}
      <div className="mt-4 space-y-2">
        {pay.unpriced > 0 ? <Notice tone="attention">{t('unpricedNote', { n: pay.unpriced })}</Notice> : null}
        {pay.pending > 0 && !pay.closed ? <Notice tone="info">{t('pendingNote', { n: pay.pending })}</Notice> : null}
      </div>

      {pay.lessons.length > 0 ? (
        <>
          <SectionTitle>{t('lessonsTitle')}</SectionTitle>
          <Card className="p-0">
            <ul className="divide-y divide-line">
              {pay.lessons.map((line) => (
                <li key={line.key} className="flex flex-wrap items-start gap-x-4 gap-y-1 px-4 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold">
                      {line.student} <span className="font-normal text-muted">· {line.subject}</span>
                    </p>
                    <p className="text-sm text-muted">
                      {line.when} · {line.outcome} · <span dir="ltr">{line.share}</span>
                    </p>
                    {line.notes.map((n) => (
                      <p key={n} className="text-xs text-muted">
                        {n}
                      </p>
                    ))}
                  </div>
                  <div className="text-end">
                    <p className={join('font-semibold tabular-nums', line.negative && 'text-danger')}>{line.amount}</p>
                    {line.corrected ? <Badge tone="info">{t('corrected')}</Badge> : null}
                  </div>
                </li>
              ))}
            </ul>
          </Card>
        </>
      ) : null}

      {adjustments.length > 0 ? (
        <>
          <SectionTitle>{t('adjustments')}</SectionTitle>
          <Card className="p-0">
            <ul className="divide-y divide-line">
              {adjustments.map((a) => (
                <li key={`${a.at}-${a.note}`} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                  <div>
                    <p className="font-semibold">{a.kind}</p>
                    <p className="text-sm text-muted">
                      {a.note} · {a.at}
                    </p>
                  </div>
                  <p className={join('font-semibold tabular-nums', a.negative && 'text-danger')}>{a.amount}</p>
                </li>
              ))}
            </ul>
          </Card>
        </>
      ) : null}
    </div>
  );
}
