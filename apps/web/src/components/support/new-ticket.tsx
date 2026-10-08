'use client';

import { useTranslations } from 'next-intl';
import { useRef, useState, useTransition } from 'react';
import { useRouter } from '@/i18n/navigation';
import { openTicket } from '@/lib/actions/shared';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Select, Textarea, join } from '@/components/ui';
import type { NewTicketOptions } from '@/lib/support/types';

const MAX_BYTES = 10 * 1024 * 1024;

/**
 * Writing to the academy: what about (the kinds this person may choose, with
 * a word on each), maybe a lesson, the subject and what happened, and a
 * picture or PDF. One key per submit, so a retry opens it once.
 */
export function NewTicket({ options, base, side }: { options: NewTicketOptions; base: '/family/help' | '/teach/help'; side: 'family' | 'teacher' }) {
  const t = useTranslations('support');
  const common = useTranslations('common');
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [kind, setKind] = useState(options.kinds[0]?.key ?? 'other');
  const [file, setFile] = useState<File | null>(null);
  const [message, setMessage] = useState<string>();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, start] = useTransition();
  const key = useRef<string | null>(null);

  if (!open) {
    return (
      <Button type="button" onClick={() => setOpen(true)}>
        <Icon name="plus" size={18} />
        {t('new')}
      </Button>
    );
  }

  const submit = (form: FormData) => {
    if (file && file.size > MAX_BYTES) {
      setMessage(t('tooBig'));
      return;
    }
    form.set('kind', kind);
    if (options.learner) form.set('learner_id', options.learner.id);
    if (file) form.set('file', file, file.name);
    key.current ??= crypto.randomUUID();
    start(async () => {
      setMessage(undefined);
      const done = await openTicket(form, key.current!);
      key.current = null;
      if (!done.ok) {
        setErrors(done.errors ?? {});
        setMessage(done.message ?? common('problem'));
        return;
      }
      setOpen(false);
      if (done.id) router.push(`${base}/${done.id}`);
    });
  };

  return (
    <form action={submit} className="space-y-4 rounded-card border border-line bg-white p-4 sm:p-5">
      <p className="font-heading text-lg font-bold">{t('newHeading')}</p>
      <fieldset className="space-y-2">
        <legend className="mb-1 text-sm font-semibold">{t('kind')}</legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {options.kinds.map((k) => (
            <label
              key={k.key}
              className={join(
                'flex cursor-pointer items-start gap-3 rounded-control border p-3 text-sm',
                kind === k.key ? 'border-brand bg-brand-soft' : 'border-line hover:border-brand-line',
              )}
            >
              <input type="radio" name="kind-choice" className="mt-1 accent-brand" checked={kind === k.key} onChange={() => setKind(k.key)} />
              <span>
                <span className="block font-semibold">{k.label}</span>
                <span className="text-muted">{t(side === 'teacher' && k.key === 'billing' ? 'hints.pay' : `hints.${k.key}`)}</span>
              </span>
            </label>
          ))}
        </div>
      </fieldset>
      {options.lessons.length > 0 ? (
        <Field label={t('lesson')} error={errors.session_id?.[0]}>
          <Select name="session_id" defaultValue="">
            <option value="">{t('noLesson')}</option>
            {options.lessons.map((l) => (
              <option key={l.id} value={l.id}>
                {[l.subject, l.starts_at?.label, l.with].filter(Boolean).join(' · ')}
              </option>
            ))}
          </Select>
        </Field>
      ) : null}
      <Field label={t('subject')} error={errors.subject?.[0]}>
        <Input name="subject" required minLength={3} maxLength={150} placeholder={t('subjectHint')} />
      </Field>
      <Field label={t('body')} error={errors.body?.[0]}>
        <Textarea name="body" required rows={5} maxLength={5000} />
      </Field>
      <Field label={t('file')} error={errors.file?.[0]}>
        <input
          type="file"
          accept="image/*,application/pdf"
          className="block w-full text-sm file:me-3 file:rounded-control file:border-0 file:bg-brand-soft file:px-4 file:py-2 file:font-semibold file:text-brand-strong"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        />
      </Field>
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={pending}>
          <Icon name="send" size={18} className="rtl:-scale-x-100" />
          {t('send')}
        </Button>
        <Button type="button" variant="quiet" onClick={() => setOpen(false)} disabled={pending}>
          {common('cancel')}
        </Button>
      </div>
    </form>
  );
}
