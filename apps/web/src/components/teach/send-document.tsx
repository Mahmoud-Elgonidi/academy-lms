'use client';

import { useTranslations } from 'next-intl';
import { useRef, useState, useTransition } from 'react';
import { sendDocument } from '@/app/[locale]/teach/actions';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Select } from '@/components/ui';

const MAX_BYTES = 10 * 1024 * 1024;

/** Sending a document for the academy to check: which one, its number and dates, and a clear photo or the PDF. One key per submit. */
export function SendDocument({ kinds }: { kinds: { key: string; label: string }[] }) {
  const t = useTranslations('documents');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand'; text: string }>();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, start] = useTransition();
  const key = useRef<string | null>(null);

  if (!open) {
    return (
      <div className="space-y-2">
        {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
        <Button type="button" onClick={() => setOpen(true)}>
          <Icon name="plus" size={18} />
          {t('send')}
        </Button>
      </div>
    );
  }

  const submit = (form: FormData) => {
    const file = form.get('file');
    if (file instanceof File && file.size > MAX_BYTES) {
      setMessage({ tone: 'danger', text: t('tooBig') });
      return;
    }
    key.current ??= crypto.randomUUID();
    start(async () => {
      const done = await sendDocument(form, key.current!);
      key.current = null;
      if (!done.ok) {
        setErrors(done.errors ?? {});
        setMessage({ tone: 'danger', text: Object.values(done.errors ?? {})[0]?.[0] ?? done.message ?? common('problem') });
        return;
      }
      setErrors({});
      setOpen(false);
      setMessage({ tone: 'brand', text: t('sent') });
    });
  };

  return (
    <form action={submit} className="space-y-4 rounded-card border border-line bg-white p-4 sm:p-5">
      <p className="text-sm text-muted">{t('sendHint')}</p>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('kind')} error={errors.kind?.[0]}>
          <Select name="kind" defaultValue={kinds[0]?.key}>
            {kinds.map((k) => (
              <option key={k.key} value={k.key}>
                {k.label}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t('reference')} error={errors.reference?.[0]}>
          <Input name="reference" maxLength={120} />
        </Field>
        <Field label={t('issuedOn')} error={errors.issued_on?.[0]}>
          <Input name="issued_on" type="date" />
        </Field>
        <Field label={t('expiresOn')} hint={t('expiresHint')} error={errors.expires_on?.[0]}>
          <Input name="expires_on" type="date" />
        </Field>
      </div>
      <Field label={t('file')} error={errors.file?.[0]}>
        <input
          name="file"
          type="file"
          required
          accept="image/jpeg,image/png,image/webp,application/pdf"
          className="block w-full text-sm file:me-3 file:rounded-control file:border-0 file:bg-brand-soft file:px-4 file:py-2 file:font-semibold file:text-brand-strong"
        />
      </Field>
      {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={pending}>
          <Icon name="send" size={18} className="rtl:-scale-x-100" />
          {t('submit')}
        </Button>
        <Button type="button" variant="quiet" onClick={() => setOpen(false)} disabled={pending}>
          {common('cancel')}
        </Button>
      </div>
    </form>
  );
}
