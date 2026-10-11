'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { cheer } from '@/app/[locale]/family/actions';
import { Icon, type IconName } from '@/components/icons';
import { join } from '@/components/ui';

/** The four cheers a guardian sends (Learning's cheers), each with its icon. */
export const CHEERS: { kind: string; icon: IconName }[] = [
  { kind: 'clap', icon: 'clap' },
  { kind: 'star', icon: 'star' },
  { kind: 'heart', icon: 'heart' },
  { kind: 'fire', icon: 'flame' },
];

/** A guardian cheers their child on: four kinds, a few a day (the API counts). */
export function CheerOn({ learnerId, name, left }: { learnerId: string; name: string; left: number }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [message, setMessage] = useState<{ ok: boolean; text: string }>();
  const [pending, start] = useTransition();
  return (
    <section className="rounded-card border border-line bg-white p-5" aria-labelledby="cheer-on">
      <h2 id="cheer-on" className="mb-3 font-semibold">
        {t('cheerOn', { name })}
      </h2>
      <div className="grid grid-cols-4 gap-2">
        {CHEERS.map((c) => (
          <button
            key={c.kind}
            type="button"
            disabled={pending || left === 0}
            onClick={() =>
              start(async () => {
                const done = await cheer(learnerId, c.kind);
                setMessage(done.ok ? { ok: true, text: t('cheerSent', { name }) } : { ok: false, text: done.message ?? common('problem') });
              })
            }
            className="grid min-h-20 justify-items-center gap-1 rounded-card border border-line bg-white p-2 text-sm font-semibold hover:border-brand-line hover:bg-brand-soft disabled:opacity-45 disabled:hover:border-line disabled:hover:bg-white"
          >
            <Icon name={c.icon} size={28} className="text-brand" />
            {t(`cheer_${c.kind}`)}
          </button>
        ))}
      </div>
      <p className="mt-2 text-center text-sm text-muted">{t('cheersLeft', { n: left })}</p>
      {message ? (
        <p className={join('mt-2 text-center text-sm font-semibold', message.ok ? 'text-brand-strong' : 'text-danger')} role="status">
          {message.text}
        </p>
      ) : null}
    </section>
  );
}
