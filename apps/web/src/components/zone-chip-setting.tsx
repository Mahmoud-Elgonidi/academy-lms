'use client';

import { useTranslations } from 'next-intl';
import { useId, useState, useTransition } from 'react';
import { Card, Notice, join } from '@/components/ui';
import { saveZoneChip } from '@/lib/actions/shared';

/**
 * Whether the floating zone chip shows (as "مؤشّر التوقيت" on My settings in
 * the panels and the app's account page): a switch saved on the account at
 * once (`PATCH /v1/me`), so the chip comes back, or goes, everywhere.
 */
export function ZoneChipSetting({ shown }: { shown: boolean }) {
  const t = useTranslations('zone.chipSetting');
  const title = useId();
  const hint = useId();
  const [on, setOn] = useState(shown);
  const [failed, setFailed] = useState<string>();
  const [pending, start] = useTransition();

  const flip = () => {
    const next = !on;
    setOn(next);
    setFailed(undefined);
    start(async () => {
      const done = await saveZoneChip(next);
      if (!done.ok) {
        setOn(!next);
        setFailed(done.message ?? t('failed'));
      }
    });
  };

  return (
    <Card>
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <h2 id={title} className="font-heading text-lg font-bold">
            {t('title')}
          </h2>
          <p id={hint} className="mt-1 text-sm text-muted">
            {t('hint')}
          </p>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={on}
          aria-labelledby={title}
          aria-describedby={hint}
          data-testid="zone-chip-switch"
          disabled={pending}
          onClick={flip}
          className={join('relative mt-1 h-7 w-12 shrink-0 rounded-full transition-colors disabled:opacity-60', on ? 'bg-brand' : 'bg-line')}
        >
          <span aria-hidden="true" className={join('absolute start-1 top-1 size-5 rounded-full bg-white shadow motion-safe:transition-transform', on && 'translate-x-5 rtl:-translate-x-5')} />
        </button>
      </div>
      {failed ? (
        <div className="mt-3">
          <Notice tone="danger">{failed}</Notice>
        </div>
      ) : null}
    </Card>
  );
}
