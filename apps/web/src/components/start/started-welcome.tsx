'use client';

import { useTranslations } from 'next-intl';
import { useSyncExternalStore, type ReactNode } from 'react';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { INVOICE_COURSE_KEY, STARTED_KEY } from '@/lib/start/flow';
import type { StartedCourse } from '@/lib/start/types';

const nothing = () => () => undefined;

/** The start this tab just made (kept for its welcome), by its course or by its invoice; null when this tab has none. */
function useStarted(course: string | null, invoice: string | null): StartedCourse | null {
  const raw = useSyncExternalStore(
    nothing,
    () => {
      try {
        const id = course ?? (invoice ? window.sessionStorage.getItem(INVOICE_COURSE_KEY(invoice)) : null);
        return id ? window.sessionStorage.getItem(STARTED_KEY(id)) : null;
      } catch {
        return null;
      }
    },
    () => null,
  );
  if (!raw) return null;
  try {
    return JSON.parse(raw) as StartedCourse;
  } catch {
    return null;
  }
}

/**
 * The course just started, as the API answered the start: the teacher, the
 * weekly times on the family's clock, the first lesson, the lessons booked,
 * what became of the offer, and paying (`waiting`) or what comes next once
 * paid (`paid`). Kept for this tab only, so a refresh keeps it; without it
 * the page says what the invoice says, and `fallback` stands in.
 */
export function StartedWelcome({ course, invoice, mode, fallback, after }: {
  course: string | null;
  invoice: string | null;
  mode: 'waiting' | 'paid';
  fallback?: ReactNode;
  /** More for the paid welcome (the way to the lessons, the app). */
  after?: ReactNode;
}) {
  const t = useTranslations('invoice.welcome');
  const started = useStarted(course, invoice);
  if (!started) return <>{fallback}</>;
  const group = started.plan?.format === 'group';
  return (
    <section className="rounded-card border border-brand-line bg-white p-5" aria-labelledby="st-welcome" data-welcome={mode}>
      <div className="flex items-start gap-3">
        <span className="st-celebrate grid size-12 shrink-0 place-items-center rounded-full bg-brand text-white" aria-hidden="true"><Icon name={mode === 'paid' ? 'check' : 'calendar'} size={24} /></span>
        <div className="min-w-0">
          <h2 id="st-welcome" className="text-xl font-bold">
            {mode === 'paid' ? t('paidTitle', { learner: started.learner.name, subject: started.subject.name }) : t('waitingTitle', { learner: started.learner.name, subject: started.subject.name })}
          </h2>
          <p className="mt-1 text-muted">{mode === 'paid' ? t('paidText') : t('waitingText')}</p>
        </div>
      </div>
      <dl className="mt-4 grid gap-3 sm:grid-cols-2">
        <div className="rounded-control bg-paper p-3">
          <dt className="text-xs text-muted">{group ? t('group') : t('teacher')}</dt>
          <dd className="mt-0.5 flex flex-wrap items-center gap-2 font-semibold"><bdi>{started.teacher.name}</bdi>{group ? <FormatBadge format="group" /> : null}</dd>
        </div>
        <div className="rounded-control bg-paper p-3">
          <dt className="text-xs text-muted">{t('first')}</dt>
          <dd className="mt-0.5 font-semibold" data-first>{started.first_lesson?.label ?? t('firstLater')}</dd>
        </div>
        <div className="rounded-control bg-paper p-3 sm:col-span-2">
          <dt className="text-xs text-muted">{t('weekly')}</dt>
          <dd className="mt-1 flex flex-wrap gap-2">
            {started.weekly_times.map((w) => (
              <span key={`${w.weekday}${w.start}`} className="inline-flex items-center gap-1.5 rounded-full bg-brand-soft px-3 py-1 text-sm font-semibold text-brand-strong"><Icon name="calendar" size={14} />{w.label}</span>
            ))}
          </dd>
        </div>
      </dl>
      {started.lessons > 0 ? <p className="mt-3 text-sm text-ink/80">{t('booked', { count: started.lessons })}</p> : null}
      {started.skipped_dates.length > 0 ? <p className="mt-1 text-sm text-ink/80">{t('skipped', { count: started.skipped_dates.length })}</p> : null}
      {started.offer.message ? <p className="mt-2 flex items-start gap-2 text-sm font-semibold text-brand-strong" data-offer-state={started.offer.state}><Icon name="spark" size={16} className="mt-0.5 shrink-0" />{started.offer.message}</p> : null}
      {mode === 'waiting' ? (
        <div className="mt-3 rounded-control border border-attention/40 bg-attention-soft p-3 text-sm" data-pay-by>
          <p className="flex items-start gap-2 font-semibold"><Icon name="clock" size={16} className="mt-0.5 shrink-0 text-attention" />{started.payment.text}</p>
          {started.payment.renews ? <p className="mt-1 text-ink/80">{started.payment.renews}</p> : null}
        </div>
      ) : null}
      {after}
    </section>
  );
}
