'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { disputeLesson } from '@/lib/actions/shared';
import { Dialog } from '@/components/dialog';
import { Button, Field, Notice, Textarea, join } from '@/components/ui';

/**
 * "Dispute": within 48 hours of a lesson recorded as held or missed, the
 * family or the teacher says what really happened (the reasons the API
 * offers for that lesson, and their words). The academy decides and answers.
 */
export function DisputeLesson({ id, side, reasons }: { id: string; side: 'family' | 'teacher'; reasons: { key: string; label: string }[] }) {
  const t = useTranslations('dispute');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState('');
  const [note, setNote] = useState('');
  const [message, setMessage] = useState<string>();
  const [sent, setSent] = useState(false);
  // One key per dialog: pressing again (or a retry) disputes once.
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [pending, start] = useTransition();

  if (sent) return <p className="w-full text-sm text-brand-strong">{t('sent')}</p>;
  return (
    <>
      <Button
        type="button"
        variant="quiet"
        className="min-h-10 px-4 text-sm"
        onClick={() => {
          setKey(crypto.randomUUID());
          setMessage(undefined);
          setOpen(true);
        }}
      >
        {t('button')}
      </Button>
      <Dialog open={open} onClose={() => setOpen(false)} title={t('title')}>
        <p className="text-sm text-muted">{t(side === 'family' ? 'introFamily' : 'introTeacher')}</p>
        <fieldset>
          <legend className="mb-2 text-sm font-semibold">{t('reason')}</legend>
          <div className="flex flex-wrap gap-2">
            {reasons.map((r) => (
              <button
                key={r.key}
                type="button"
                aria-pressed={reason === r.key}
                onClick={() => setReason(r.key)}
                className={join('min-h-10 rounded-full border px-3 text-sm', reason === r.key ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-line bg-white')}
              >
                {r.label}
              </button>
            ))}
          </div>
        </fieldset>
        <Field label={t('note')} hint={t('noteHint')}>
          <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} maxLength={1000} required />
        </Field>
        {message ? <Notice tone="danger">{message}</Notice> : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="quiet" onClick={() => setOpen(false)}>
            {common('cancel')}
          </Button>
          <Button
            type="button"
            disabled={pending}
            onClick={() => {
              if (!reason || note.trim() === '') {
                setMessage(t('fillIn'));
                return;
              }
              start(async () => {
                const result = await disputeLesson(side, id, reason, note, key);
                if (result.ok) {
                  setOpen(false);
                  setSent(true);
                } else {
                  setMessage(result.message ?? common('problem'));
                }
              });
            }}
          >
            {t('send')}
          </Button>
        </div>
      </Dialog>
    </>
  );
}
