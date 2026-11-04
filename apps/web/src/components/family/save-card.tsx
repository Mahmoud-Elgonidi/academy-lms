'use client';

import { useTranslations } from 'next-intl';
import { useId } from 'react';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';

/**
 * "Save my card and renew automatically" beside paying online (J5b): one box
 * the payer ticks, what it means in a line, and the words they agree to,
 * which open as soon as it is ticked (and can be read before). The card
 * itself is typed only on the gateway's page.
 */
export function SaveCardOption({ checked, onChange, text, disabled }: { checked: boolean; onChange: (on: boolean) => void; text: string; disabled?: boolean }) {
  const t = useTranslations('cards.save');
  const hint = useId();
  return (
    <div className={join('rounded-control border p-3 transition-colors', checked ? 'border-brand bg-brand-soft' : 'border-line bg-white')} data-save-card={checked ? 'on' : 'off'}>
      <label className="flex cursor-pointer items-start gap-3">
        <input
          type="checkbox"
          checked={checked}
          disabled={disabled}
          onChange={(e) => onChange(e.target.checked)}
          aria-describedby={hint}
          className="mt-1 size-5 shrink-0 accent-[var(--color-brand)]"
          data-save-card-box
        />
        <span className="min-w-0">
          <span className="flex items-center gap-1.5 font-semibold">
            <Icon name="card" size={18} className="shrink-0 text-brand-strong" />
            {t('label')}
          </span>
          <span id={hint} className="mt-0.5 block text-sm leading-6 text-ink/80">{t('hint')}</span>
        </span>
      </label>
      <details className="mt-2 ps-8" open={checked || undefined} data-mandate>
        <summary className="inline-flex min-h-9 cursor-pointer list-none items-center gap-1 text-sm font-semibold text-brand-strong [&::-webkit-details-marker]:hidden">
          <Icon name="file" size={16} />
          {t('read')}
        </summary>
        <p className="mt-1 whitespace-pre-line rounded-control bg-white/80 p-3 text-sm leading-6 text-ink/85" data-mandate-text>{text}</p>
      </details>
    </div>
  );
}
