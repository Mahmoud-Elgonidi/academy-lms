'use client';

import { useTranslations } from 'next-intl';
import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Icon } from '@/components/icons';

/** A modal dialog (the browser's own, so focus and Escape behave). */
export function Dialog({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const t = useTranslations('common');
  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);
  return (
    <dialog
      ref={ref}
      onClose={onClose}
      aria-labelledby={titleId}
      className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-card border border-line bg-white p-0 text-ink backdrop:bg-black/40"
    >
      <div className="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
        <h2 id={titleId} className="font-semibold">
          {title}
        </h2>
        <button type="button" onClick={onClose} className="grid size-9 place-items-center rounded-control text-muted hover:bg-paper" aria-label={t('close')}>
          <Icon name="x" size={18} />
        </button>
      </div>
      <div className="space-y-4 p-5">{children}</div>
    </dialog>
  );
}
