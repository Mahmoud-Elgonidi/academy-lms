'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useState } from 'react';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';

/**
 * Where to send the money by hand (an account and its IBAN, an InstaPay or
 * wallet number), in the academy's own words: plain text with its line breaks,
 * selectable, with a button that copies it. `numbers` names the invoices it is
 * for when the payer's invoices go to different places.
 */
export function TransferDetails({ text, numbers = [], compact = false }: { text: string; numbers?: string[]; compact?: boolean }) {
  const t = useTranslations('family');
  const locale = useLocale();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
    } catch {
      setCopied(false);
    }
  };

  return (
    <div className={join('rounded-control bg-brand-soft text-sm', compact ? 'px-4 py-3' : 'px-4 py-4')}>
      <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
        <p className="font-semibold">
          {t('transferTitle')}
          {numbers.length > 0 ? <span className="font-normal text-muted"> · {t('transferFor', { numbers: numbers.join(locale === 'ar' ? '، ' : ', ') })}</span> : null}
        </p>
        <button
          type="button"
          onClick={copy}
          className="inline-flex min-h-10 items-center gap-1.5 rounded-control px-2 font-semibold text-brand-strong hover:bg-white/60"
          aria-live="polite"
        >
          <Icon name={copied ? 'check' : 'copy'} size={16} />
          {copied ? t('transferCopied') : t('transferCopy')}
        </button>
      </div>
      <p dir="auto" className="select-text whitespace-pre-line break-words">
        {text}
      </p>
    </div>
  );
}
