'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { askDataCopy, askErasure, cancelDataRequest } from '@/lib/actions/shared';
import { Badge, Button, Card, Empty, Field, Notice, Textarea } from '@/components/ui';
import { latestOf, type MyData } from '@/lib/privacy';

/**
 * "My data", the same for teachers and families: for each person one may ask
 * about, a copy (prepared, then downloaded for a week) and erasure (asked
 * with a reason if they like, answered by the academy, taken back until it
 * is done); then every request, and the academy's public pages. The API
 * checks each step again. Live through LiveRefresh (privacy.changed).
 */
export function MyDataView({ mine }: { mine: MyData }) {
  const t = useTranslations('myData');
  return (
    <div className="space-y-5">
      {mine.subjects.length === 0 ? <Notice tone="info">{t('young')}</Notice> : null}
      <div className="space-y-4">
        {mine.subjects.map((subject) => (
          <Person key={subject.id} subject={subject} mine={mine} />
        ))}
      </div>

      <section>
        <h2 className="mb-2 font-heading text-base font-bold">{t('history')}</h2>
        {mine.requests.length === 0 ? (
          <Empty>{t('none')}</Empty>
        ) : (
          <ul className="divide-y divide-line overflow-hidden rounded-card border border-line bg-white text-sm">
            {mine.requests.map((r) => (
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span>
                  {r.kind.label} · {t('about', { name: r.subject.name })}
                </span>
                <span className="text-muted">
                  {r.status.label} · {r.created_at?.label}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <nav aria-label={t('pages')} className="flex flex-wrap gap-4 text-sm">
        {(['privacy', 'terms', 'deletion'] as const).map((key) => (
          <a key={key} href={mine.links[key]} target="_blank" rel="noopener" className="font-semibold text-brand hover:underline">
            {t(`links.${key}`)}
          </a>
        ))}
      </nav>
    </div>
  );
}

function Person({ subject, mine }: { subject: MyData['subjects'][number]; mine: MyData }) {
  const t = useTranslations('myData');
  const { copy, erasure } = latestOf(mine.requests, subject.id);
  const [pending, start] = useTransition();
  const [error, setError] = useState<string>();
  const [asking, setAsking] = useState(false);
  const [reason, setReason] = useState('');
  const [key, setKey] = useState(() => crypto.randomUUID());
  const open = erasure && (erasure.status.key === 'pending' || erasure.status.key === 'approved');

  const run = (step: () => Promise<{ ok: boolean; message?: string }>, after?: () => void) =>
    start(async () => {
      const done = await step();
      setError(done.ok ? undefined : (done.message ?? t('failed')));
      if (done.ok) {
        setKey(crypto.randomUUID());
        after?.();
      }
    });

  return (
    <Card as="article">
      <h2 className="font-heading text-lg font-bold">{subject.name}</h2>
      {error ? (
        <div className="mt-2">
          <Notice tone="danger">{error}</Notice>
        </div>
      ) : null}
      <div className="mt-3 grid gap-3 md:grid-cols-2">
        <section className="space-y-2 rounded-control border border-line p-3">
          <h3 className="font-semibold">{t('copy.title')}</h3>
          <p className="text-sm text-muted">{t('copy.body', { days: 7 })}</p>
          {copy?.status.key === 'pending' ? <Notice tone="attention">{t('copy.preparing')}</Notice> : null}
          {copy?.download_url ? (
            <div className="flex flex-wrap items-center gap-3 rounded-control bg-brand-soft px-3 py-2 text-sm">
              <a href={copy.download_url} className="font-semibold text-brand-strong hover:underline">
                {t('copy.download')}
              </a>
              <span className="text-muted">{t('copy.until', { when: copy.expires_at?.label ?? '' })}</span>
            </div>
          ) : null}
          {subject.can_copy ? (
            <Button variant="quiet" disabled={pending} onClick={() => run(() => askDataCopy(subject.id, key))}>
              {t('copy.action')}
            </Button>
          ) : null}
        </section>

        <section className="space-y-2 rounded-control border border-line p-3">
          <h3 className="font-semibold">{t('erase.title')}</h3>
          <p className="text-sm text-muted">{t('erase.body', { days: mine.answer_days })}</p>
          {erasure?.status.key === 'pending' ? <Notice tone="attention">{t('erase.waiting', { when: erasure.due_at?.label ?? '' })}</Notice> : null}
          {erasure?.status.key === 'approved' ? <Notice tone="danger">{t('erase.approved', { when: erasure.erase_after?.label ?? '' })}</Notice> : null}
          {erasure?.status.key === 'refused' ? <Notice tone="info">{t('erase.refused', { answer: erasure.answer ?? '' })}</Notice> : null}
          {open && erasure.can_cancel ? (
            <Button variant="quiet" disabled={pending} onClick={() => run(() => cancelDataRequest(erasure.id))}>
              {t('erase.cancel')}
            </Button>
          ) : null}
          {!open && !asking ? (
            <Button variant="quiet" className="text-danger" disabled={pending} onClick={() => setAsking(true)}>
              {t('erase.action')}
            </Button>
          ) : null}
          {!open && asking ? (
            <div className="space-y-2 rounded-control bg-danger-soft p-3">
              <p className="text-sm font-semibold">{t('erase.confirm', { name: subject.name })}</p>
              <p className="text-sm">{t('erase.copyFirst')}</p>
              <Field label={t('erase.reason')}>
                <Textarea rows={2} maxLength={1000} value={reason} onChange={(e) => setReason(e.target.value)} />
              </Field>
              <div className="flex flex-wrap gap-2">
                <Button variant="danger" disabled={pending} onClick={() => run(() => askErasure(subject.id, reason, key), () => setAsking(false))}>
                  {t('erase.action')}
                </Button>
                <Button variant="quiet" disabled={pending} onClick={() => setAsking(false)}>
                  {t('back')}
                </Button>
              </div>
            </div>
          ) : null}
          <details className="text-sm">
            <summary className="cursor-pointer text-brand">{t('whatGoes')}</summary>
            <ul className="mt-2 list-disc space-y-1 ps-5 text-muted">
              {mine.goes.map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
            <p className="mt-2 font-semibold">{t('whatStays')}</p>
            <ul className="mt-1 list-disc space-y-1 ps-5 text-muted">
              {mine.stays.map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
            <p className="mt-2 text-muted">{mine.stays_note}</p>
          </details>
        </section>
      </div>
      {subject.self ? null : <Badge tone="muted">{t('learner')}</Badge>}
    </Card>
  );
}
