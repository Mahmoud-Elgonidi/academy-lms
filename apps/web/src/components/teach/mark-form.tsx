'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { markHomework } from '@/app/[locale]/teach/actions';
import { Button, Field, Notice, Textarea, join } from '@/components/ui';

/** A grade from 1 to 5 (optional) and words for the student; the family sees both. */
export function MarkForm({ id }: { id: string }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [grade, setGrade] = useState<number | null>(null);
  const [feedback, setFeedback] = useState('');
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();

  return (
    <form
      className="space-y-3"
      onSubmit={(e) => {
        e.preventDefault();
        start(async () => {
          const done = await markHomework(id, grade, feedback);
          setMessage(done.ok ? undefined : (Object.values(done.errors ?? {})[0]?.[0] ?? done.message ?? common('problem')));
        });
      }}
    >
      <div>
        <p className="mb-1.5 text-sm font-semibold">{t('gradeLabel')}</p>
        <div role="radiogroup" aria-label={t('gradeLabel')} className="flex gap-1">
          {[1, 2, 3, 4, 5].map((n) => (
            <button
              key={n}
              type="button"
              role="radio"
              aria-checked={grade === n}
              onClick={() => setGrade(grade === n ? null : n)}
              className={join(
                'grid size-10 place-items-center rounded-control border font-semibold tabular-nums',
                grade === n ? 'border-brand bg-brand text-white' : 'border-line bg-white hover:border-brand-line',
              )}
            >
              {n}
            </button>
          ))}
        </div>
      </div>
      <Field label={t('feedbackLabel')}>
        <Textarea value={feedback} onChange={(e) => setFeedback(e.target.value)} rows={2} maxLength={3000} />
      </Field>
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <Button disabled={pending}>{pending ? common('loading') : t('saveMark')}</Button>
    </form>
  );
}
