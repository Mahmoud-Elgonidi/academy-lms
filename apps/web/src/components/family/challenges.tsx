'use client';

import { useTranslations } from 'next-intl';
import { createContext, useContext, useState, useTransition, type ReactNode } from 'react';
import { cheer, cheersSeen, claimChallenge } from '@/app/[locale]/family/actions';
import { Icon, type IconName } from '@/components/icons';
import { Button, join } from '@/components/ui';
import type { Challenge, Challenges, Claimed } from '@/lib/family/types';

export const CHEERS: { kind: string; icon: IconName }[] = [
  { kind: 'clap', icon: 'clap' },
  { kind: 'star', icon: 'star' },
  { kind: 'heart', icon: 'heart' },
  { kind: 'fire', icon: 'flame' },
];

const Celebrate = createContext<(got: Claimed) => void>(() => {});

/**
 * The board around the challenges: what a collected reward brought shows here,
 * over the tab bar, and stays until closed (a challenge from last week leaves
 * the list once collected, so its row cannot hold it).
 */
export function ChallengeBoard({ children }: { children: ReactNode }) {
  const t = useTranslations('family');
  const [got, setGot] = useState<Claimed | null>(null);
  return (
    <Celebrate.Provider value={setGot}>
      {children}
      {got ? (
        <div role="status" className="pop fixed inset-x-4 bottom-24 z-40 mx-auto flex max-w-md items-center gap-3 rounded-card border border-reward bg-reward-soft p-4 shadow-lg lg:bottom-8">
          <span className="grid size-11 shrink-0 place-items-center rounded-full bg-reward text-white">
            <Icon name="star" size={24} fill="currentColor" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-heading text-lg font-bold">{t('gotReward', { reward: got.reward_label })}</span>
            {got.sticker ? <span className="block text-sm">{t('gotSticker', { name: got.sticker.name })}</span> : null}
            {got.ranked_up ? <span className="block text-sm font-semibold text-brand-strong">{t('rankedUp', { rank: got.rank.label })}</span> : null}
          </span>
          <button type="button" onClick={() => setGot(null)} className="grid size-9 shrink-0 place-items-center rounded-control hover:bg-white/60" aria-label={t('close')}>
            <Icon name="x" size={18} />
          </button>
        </div>
      ) : null}
    </Celebrate.Provider>
  );
}

/**
 * The end of a challenge's row: collect it when it is finished (the student
 * only); otherwise how far along and its state.
 */
export function ChallengeEnd({ challenge, canClaim, dark = false }: { challenge: Pick<Challenge, 'key' | 'period' | 'state' | 'state_label' | 'progress' | 'target' | 'reward_label'>; canClaim: boolean; dark?: boolean }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const celebrate = useContext(Celebrate);
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();

  if (canClaim && challenge.state === 'done') {
    return (
      <div className="flex flex-col items-stretch gap-1 sm:items-end">
        <Button
          type="button"
          className="nudge min-h-11 bg-reward px-4 text-ink hover:bg-reward sm:min-h-10 sm:text-sm"
          disabled={pending}
          onClick={() =>
            start(async () => {
              setMessage(undefined);
              const done = await claimChallenge(challenge.key, challenge.period, crypto.randomUUID());
              if (done.ok && done.claimed) celebrate(done.claimed);
              else setMessage(done.message ?? common('problem'));
            })
          }
        >
          <Icon name="chest" size={18} />
          {t('collectReward', { reward: challenge.reward_label })}
        </Button>
        {message ? <span className="text-xs text-danger">{message}</span> : null}
      </div>
    );
  }

  const chip =
    challenge.state === 'claimed'
      ? 'bg-brand-soft text-brand-strong'
      : challenge.state === 'done'
        ? 'bg-reward-soft text-ink'
        : challenge.state === 'missed'
          ? 'bg-attention-soft text-attention'
          : null;
  return (
    <div className="flex flex-col items-end gap-1 text-sm">
      <b className="tabular-nums" dir="ltr">
        {challenge.progress}/{challenge.target}
      </b>
      {chip ? <span className={join('whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', chip)}>{challenge.state === 'done' ? t('doneMark') : challenge.state_label}</span> : <span className={join('whitespace-nowrap', dark ? 'text-white/75' : 'text-muted')}>{challenge.reward_label}</span>}
    </div>
  );
}

/** The guardians' cheers the student has not seen yet; thanking marks them seen. */
export function CheersReceived({ cheers }: { cheers: Challenges['cheers'] }) {
  const t = useTranslations('family');
  const [hidden, setHidden] = useState(false);
  const [pending, start] = useTransition();
  if (hidden || cheers.length === 0) return null;
  const names = [...new Set(cheers.map((c) => c.from).filter(Boolean))].join('، ');
  const kinds = [...new Set(cheers.map((c) => c.kind))];
  return (
    <section className="pop mb-4 flex flex-wrap items-center gap-3 rounded-card border border-brand-line bg-brand-soft p-4" aria-live="polite">
      <span className="flex gap-1 text-brand-strong">
        {kinds.map((k) => (
          <Icon key={k} name={CHEERS.find((c) => c.kind === k)?.icon ?? 'clap'} size={28} />
        ))}
      </span>
      <p className="min-w-0 flex-1 font-heading text-lg font-bold">{t('cheerFromMany', { names })}</p>
      <Button
        type="button"
        className="min-h-10 px-4 text-sm"
        disabled={pending}
        onClick={() =>
          start(async () => {
            await cheersSeen();
            setHidden(true);
          })
        }
      >
        {t('cheersThanks')}
      </Button>
    </section>
  );
}

/** A guardian cheers their child on: four kinds, a few a day (the API counts). */
export function CheerOn({ learnerId, name, left }: { learnerId: string; name: string; left: number }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [message, setMessage] = useState<{ ok: boolean; text: string }>();
  const [pending, start] = useTransition();
  return (
    <section className="mt-6 rounded-card border border-line bg-white p-5" aria-labelledby="cheer-on">
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
