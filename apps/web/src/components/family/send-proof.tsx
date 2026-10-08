'use client';

import { useTranslations } from 'next-intl';
import { useRef, useState, useTransition } from 'react';
import { sendProof } from '@/app/[locale]/family/actions';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Textarea, join } from '@/components/ui';
import type { Invoice, ManualPayment } from '@/lib/family/types';

const MAX_BYTES = 10 * 1024 * 1024;

/** Today on this person's own calendar, as a date field writes it. */
function today(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Paid by hand (a bank transfer, InstaPay, a wallet): the academy's own words
 * on how, then the receipt and what it says. It waits for the academy, which
 * checks the money arrived (one key per submit, so a retry sends it once).
 */
export function SendProof({ invoice, manual, primary }: { invoice: Invoice; manual: ManualPayment; primary: boolean }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [method, setMethod] = useState(manual.methods[0]?.value ?? 'bank_transfer');
  const [file, setFile] = useState<File | null>(null);
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand'; text: string }>();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, start] = useTransition();
  const key = useRef<string | null>(null);

  if (!open) {
    return (
      <div className="space-y-2">
        {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
        <Button type="button" variant={primary ? 'primary' : 'quiet'} onClick={() => setOpen(true)} className="w-full sm:w-auto">
          <Icon name="file" size={18} />
          {t('proofAction')}
        </Button>
      </div>
    );
  }

  const submit = (form: FormData) => {
    if (!file) {
      setMessage({ tone: 'danger', text: t('proofNeedsReceipt') });
      return;
    }
    if (file.size > MAX_BYTES) {
      setMessage({ tone: 'danger', text: t('proofTooBig') });
      return;
    }
    form.set('method', method);
    form.set('file', file, file.name);
    key.current ??= crypto.randomUUID();
    start(async () => {
      setMessage(undefined);
      const done = await sendProof(invoice.id, form, key.current!);
      if (!done.ok) {
        setErrors(done.errors ?? {});
        setMessage({ tone: 'danger', text: done.message ?? common('problem') });
        key.current = null;
        return;
      }
      key.current = null;
      setOpen(false);
      setMessage({ tone: 'brand', text: t('proofSent') });
    });
  };

  return (
    <form action={submit} className="space-y-4 rounded-card border border-line bg-white p-4">
      <div>
        <p className="font-heading font-semibold">{t('proofTitle')}</p>
        <p className="text-sm text-muted">{t('proofHint')}</p>
      </div>
      {manual.note ? (
        <div className="rounded-control bg-brand-soft px-4 py-3 text-sm">
          <p className="mb-1 font-semibold">{t('proofHowTo')}</p>
          <p className="whitespace-pre-line break-words">{manual.note}</p>
        </div>
      ) : null}
      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{t('proofMethod')}</legend>
        <div className="flex flex-wrap gap-2">
          {manual.methods.map((m) => (
            <label
              key={m.value}
              className={join(
                'inline-flex min-h-10 cursor-pointer items-center rounded-full border px-4 text-sm',
                method === m.value ? 'border-brand bg-brand-soft font-semibold' : 'border-line bg-white hover:border-brand-line',
              )}
            >
              <input type="radio" name="method-choice" value={m.value} checked={method === m.value} onChange={() => setMethod(m.value)} className="sr-only" />
              {m.label}
            </label>
          ))}
        </div>
      </fieldset>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('proofAmount')} error={errors.amount?.[0]}>
          <Input name="amount" type="number" inputMode="decimal" step="any" min="0" required defaultValue={invoice.outstanding_decimal} />
        </Field>
        <Field label={t('proofPaidOn')} error={errors.paid_on?.[0]}>
          <Input name="paid_on" type="date" required max={today()} defaultValue={today()} />
        </Field>
      </div>
      <Field label={t('proofReference')} hint={t('proofReferenceHint')} error={errors.reference?.[0]}>
        <Input name="reference" maxLength={120} dir="auto" />
      </Field>
      <div>
        <span className="mb-1.5 block text-sm font-semibold">{t('proofReceipt')}</span>
        <label className={join('inline-flex min-h-11 max-w-full cursor-pointer items-center gap-2 rounded-control border border-line bg-white px-5 font-semibold hover:border-brand-line', pending && 'opacity-50')}>
          <Icon name="file" size={18} />
          <span className="truncate">{file ? file.name : t('proofPickReceipt')}</span>
          <input type="file" className="sr-only" accept="image/*,application/pdf" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        </label>
        <span className={join('mt-1 block', errors.file ? 'text-sm text-danger' : 'text-xs text-muted')}>{errors.file?.[0] ?? t('proofReceiptHint')}</span>
      </div>
      <Field label={t('proofNote')} error={errors.note?.[0]}>
        <Textarea name="note" rows={2} maxLength={500} />
      </Field>
      {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={pending}>
          {pending ? common('loading') : t('proofSend')}
        </Button>
        <Button type="button" variant="quiet" disabled={pending} onClick={() => setOpen(false)}>
          {common('cancel')}
        </Button>
      </div>
    </form>
  );
}
