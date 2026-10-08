'use client';

import { useTranslations } from 'next-intl';
import { useRef, useState, useTransition } from 'react';
import { buyExtraLessons } from '@/app/[locale]/family/actions';
import { Icon } from '@/components/icons';
import { Button, Notice, join } from '@/components/ui';
import type { ExtraOffer } from '@/lib/family/types';

/**
 * Extra lessons on a course, for whoever pays for it: how many, at a
 * lesson's price; it makes the invoice (with the family discount, if any),
 * which then waits above with the others to be paid. One key per press.
 */
export function ExtraLessons({ enrollment, learnerId, subject, offer }: { enrollment: string; learnerId: string; subject: string; offer: ExtraOffer }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [count, setCount] = useState(1);
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand'; text: string }>();
  const [pending, start] = useTransition();
  const key = useRef<string | null>(null);

  if (offer.waiting_invoice) {
    return <p className="mt-3 border-t border-dashed border-line pt-3 text-sm text-muted">{t('extraWaiting', { number: offer.waiting_invoice.number })}</p>;
  }

  const make = () => {
    key.current ??= crypto.randomUUID();
    start(async () => {
      setMessage(undefined);
      const made = await buyExtraLessons(enrollment, learnerId, count, key.current!);
      key.current = null;
      if (!made.ok) {
        setMessage({ tone: 'danger', text: made.message ?? common('problem') });
        return;
      }
      setOpen(false);
      setMessage({ tone: 'brand', text: t('extraDone', { number: made.number ?? '', total: made.total ?? '' }) });
    });
  };

  return (
    <div className="mt-3 space-y-3 border-t border-dashed border-line pt-3">
      {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      {!open ? (
        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-sm text-muted">{t('extraHint', { price: offer.price, minutes: offer.minutes })}</p>
          <Button type="button" variant="quiet" onClick={() => setOpen(true)}>
            <Icon name="plus" size={18} />
            {t('extraAction')}
          </Button>
        </div>
      ) : (
        <div className="space-y-3">
          <div>
            <p className="font-heading font-semibold">{t('extraTitle', { subject })}</p>
            <p className="text-sm text-muted">{t('extraBody')}</p>
          </div>
          <fieldset>
            <legend className="mb-2 text-sm font-semibold">{t('extraCount')}</legend>
            <div className="flex flex-wrap gap-2">
              {Array.from({ length: offer.max }, (_, i) => i + 1).map((n) => (
                <button
                  key={n}
                  type="button"
                  aria-pressed={count === n}
                  onClick={() => setCount(n)}
                  className={join(
                    'min-h-10 min-w-10 rounded-control border px-3 text-sm font-semibold tabular-nums',
                    count === n ? 'border-brand bg-brand text-white' : 'border-line bg-white hover:border-brand-line',
                  )}
                >
                  {n}
                </button>
              ))}
            </div>
          </fieldset>
          <p className="text-sm tabular-nums text-muted">{t('extraEach', { n: count, price: offer.price })}</p>
          <div className="flex flex-wrap gap-2">
            <Button type="button" onClick={make} disabled={pending}>
              {t('extraSubmit')}
            </Button>
            <Button type="button" variant="quiet" onClick={() => setOpen(false)} disabled={pending}>
              {t('close')}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
