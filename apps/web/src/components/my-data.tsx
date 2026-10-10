'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { askCorrection, askDataCopy, askErasure, cancelDataRequest, correctDetails } from '@/lib/actions/shared';
import { Badge, Button, Card, Empty, Field, Input, Notice, Select, Textarea } from '@/components/ui';
import { latestOf, type MyData } from '@/lib/privacy';

type Subject = MyData['subjects'][number];

/**
 * "My data", the same for teachers and families: for each person one may ask
 * about, their details put right on the spot (the name; a student's gender,
 * country, grade and who should teach them) or a correction asked of the
 * academy for the rest, a copy (prepared, then downloaded for a week) and
 * erasure (asked with a reason if they like, answered by the academy, taken
 * back until it is done); then every request, and the academy's public
 * pages. The email and number change on `account`. The API checks each step
 * again. Live through LiveRefresh (privacy.changed).
 */
export function MyDataView({ mine, account }: { mine: MyData; account: string }) {
  const t = useTranslations('myData');
  return (
    <div className="space-y-5">
      {mine.subjects.length === 0 ? <Notice tone="info">{t('young')}</Notice> : null}
      <div className="space-y-4">
        {mine.subjects.map((subject) => (
          <Person key={subject.id} subject={subject} mine={mine} account={account} />
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

function Person({ subject, mine, account }: { subject: Subject; mine: MyData; account: string }) {
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
        <Fix subject={subject} mine={mine} account={account} />

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

/** Putting the details right: on the spot where one may, else a correction the academy makes (one open at a time). */
function Fix({ subject, mine, account }: { subject: Subject; mine: MyData; account: string }) {
  const t = useTranslations('myData');
  const [pending, start] = useTransition();
  const [notice, setNotice] = useState<{ tone: 'brand' | 'danger'; text: string }>();
  const [editing, setEditing] = useState(false);
  const [values, setValues] = useState<Record<string, string>>({});
  const [asking, setAsking] = useState(false);
  const [says, setSays] = useState('');
  const [key, setKey] = useState(() => crypto.randomUUID());
  const details = subject.details ?? [];
  const fix = subject.correction;
  const waiting = fix?.status.key === 'pending';

  const edit = () => {
    setValues(Object.fromEntries(details.map((d) => [d.key, d.value ?? ''])));
    setNotice(undefined);
    setEditing(true);
  };
  const save = () =>
    start(async () => {
      const changes: Record<string, string | null> = {};
      for (const d of details) {
        const next = (values[d.key] ?? '').trim();
        if (next !== (d.value ?? '')) changes[d.key] = next === '' ? null : next;
      }
      const done = Object.keys(changes).length === 0 ? { ok: true as const, changed: [] } : await correctDetails(subject.id, changes);
      if (!done.ok) return setNotice({ tone: 'danger', text: done.message ?? t('failed') });
      setEditing(false);
      setNotice({ tone: 'brand', text: done.changed.length === 0 ? t('fix.unchanged') : t('fix.saved') });
    });
  const ask = () =>
    start(async () => {
      if (says.trim().length < mine.says_min) return setNotice({ tone: 'danger', text: t('fix.tooShort', { min: mine.says_min }) });
      const done = await askCorrection(subject.id, says, key);
      if (!done.ok) return setNotice({ tone: 'danger', text: done.message ?? t('failed') });
      setKey(crypto.randomUUID());
      setAsking(false);
      setSays('');
      setNotice(undefined);
    });
  const cancel = (id: string) =>
    start(async () => {
      const done = await cancelDataRequest(id);
      setNotice(done.ok ? undefined : { tone: 'danger', text: done.message ?? t('failed') });
    });

  return (
    <section className="space-y-3 rounded-control border border-line p-3 md:col-span-2" data-part="fix">
      <h3 className="font-semibold">{t('fix.title')}</h3>
      {notice ? <Notice tone={notice.tone}>{notice.text}</Notice> : null}
      {details.length > 0 && !editing ? (
        <>
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            {details.map((d) => (
              <div key={d.key} className="contents">
                <dt className="text-muted">{d.label}</dt>
                <dd className="font-medium break-words">{d.shown}</dd>
              </div>
            ))}
          </dl>
          <Button variant="quiet" disabled={pending} onClick={edit}>
            {t('fix.edit')}
          </Button>
        </>
      ) : null}
      {editing ? (
        <div className="space-y-3 rounded-control bg-paper p-3">
          {details.map((d) =>
            d.key === 'name' ? (
              <Field key={d.key} label={d.label}>
                <Input required maxLength={255} value={values.name ?? ''} onChange={(e) => setValues({ ...values, name: e.target.value })} />
              </Field>
            ) : (
              <Field key={d.key} label={d.label}>
                <Select value={values[d.key] ?? ''} onChange={(e) => setValues({ ...values, [d.key]: e.target.value })}>
                  {(mine.choices[d.key] ?? []).some((c) => c.value === '') ? null : <option value="">—</option>}
                  {(mine.choices[d.key] ?? []).map((c) => (
                    <option key={c.value} value={c.value}>
                      {c.label}
                    </option>
                  ))}
                </Select>
              </Field>
            ),
          )}
          <div className="flex flex-wrap gap-2">
            <Button disabled={pending || (values.name ?? '').trim() === ''} onClick={save}>
              {t('fix.save')}
            </Button>
            <Button variant="quiet" disabled={pending} onClick={() => setEditing(false)}>
              {t('back')}
            </Button>
          </div>
        </div>
      ) : null}
      {subject.self ? (
        <p className="text-sm text-muted">
          {t('fix.contact')}{' '}
          <a href={account} className="font-semibold text-brand hover:underline">
            {t('fix.account')}
          </a>
        </p>
      ) : null}
      <p className="text-sm text-muted">{t('fix.body', { days: mine.answer_days })}</p>
      {waiting ? (
        <Notice tone="attention">
          {t('fix.waiting', { when: fix.due_at?.label ?? '' })}
          {fix.reason ? <span className="mt-1 block text-muted">«{fix.reason}»</span> : null}
        </Notice>
      ) : null}
      {fix?.status.key === 'done' ? <Notice tone="brand">{fix.answer ? `${t('fix.done')}: ${fix.answer}` : t('fix.done')}</Notice> : null}
      {fix?.status.key === 'refused' ? <Notice tone="info">{t('fix.refused', { answer: fix.answer ?? '' })}</Notice> : null}
      {waiting && fix.can_cancel ? (
        <Button variant="quiet" disabled={pending} onClick={() => cancel(fix.id)}>
          {t('fix.cancel')}
        </Button>
      ) : null}
      {!waiting && !asking ? (
        <Button variant="quiet" disabled={pending} onClick={() => setAsking(true)}>
          {t('fix.ask')}
        </Button>
      ) : null}
      {!waiting && asking ? (
        <div className="space-y-2 rounded-control bg-paper p-3">
          <p className="text-sm">{t('fix.askBody', { days: mine.answer_days })}</p>
          <Field label={t('fix.says')}>
            <Textarea rows={3} minLength={mine.says_min} maxLength={mine.says_max} placeholder={t('fix.placeholder')} value={says} onChange={(e) => setSays(e.target.value)} />
          </Field>
          <div className="flex flex-wrap gap-2">
            <Button disabled={pending} onClick={ask}>
              {t('fix.send')}
            </Button>
            <Button variant="quiet" disabled={pending} onClick={() => setAsking(false)}>
              {t('back')}
            </Button>
          </div>
        </div>
      ) : null}
    </section>
  );
}
