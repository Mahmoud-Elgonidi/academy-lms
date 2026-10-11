'use client';

import { useState, useTransition, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { cheer } from '@/app/[locale]/family/actions';
import { saveLimits } from '@/app/[locale]/family/games/actions';
import { Icon } from '@/components/icons';
import { join } from '@/components/ui';
import { sound } from '@/lib/games/sound';
import type { Allowance, GameLimits, Glance } from '@/lib/games/types';
import { minuteChoices, usedShare } from '@/lib/games/world';
import { CreatureArt } from './art';
import './games.css';
import './islands.css';

/**
 * A guardian's look at their child's games (the approved design, Guardian),
 * in the academy's colours, as parts of the child's journey: today's play
 * time against the limit with the minutes a day and the night lock, what the
 * child practised this week, a creature hatched today to cheer, and the
 * safety promise. Pay and KPIs never appear here.
 */
export function GuardianGames({ learnerId, allowance, limits, glance }: { learnerId: string; allowance: Allowance; limits: GameLimits | null; glance: Glance | null }) {
  const t = useTranslations('games');
  return (
    <div className="flex flex-col gap-3">
      {limits ? <PlayTime learnerId={learnerId} allowance={allowance} limits={limits} /> : null}

      <section className="rounded-card border border-line bg-white p-4" aria-labelledby="gw-learned">
        <h2 id="gw-learned" className="mb-2 font-semibold">
          {t('guardian.learned')}
        </h2>
        <div className="flex flex-col gap-2 text-[14.5px]">
          {glance?.practised ? (
            <Line tone="bg-brand-soft text-brand-strong" icon="check">
              {t('guardian.practised', {
                title: glance.practised.title,
                rounds: t('guardian.rounds', { n: glance.practised.rounds }),
                games: t('guardian.gamesCount', { n: glance.practised.games }),
              })}
            </Line>
          ) : null}
          {glance?.hardest ? (
            <Line tone="bg-attention-soft text-attention" icon="alert">
              {t('guardian.hardest', { text: glance.hardest.text.length > 60 ? `${glance.hardest.text.slice(0, 57)}...` : glance.hardest.text })}
            </Line>
          ) : null}
          {glance?.best ? (
            <Line tone="bg-info-soft text-info" icon="star">
              {t('guardian.best', glance.best)}
            </Line>
          ) : null}
          {!glance?.practised && !glance?.hardest && !glance?.best ? <p className="text-sm text-muted">{t('guardian.quiet')}</p> : null}
        </div>
      </section>

      {glance?.hatched ? <Hatched hatched={glance.hatched} learnerId={learnerId} /> : null}

      <div className="flex items-start gap-2.5 rounded-card bg-brand-soft px-3 py-2.5 text-[13.5px] leading-relaxed text-brand-strong">
        <Icon name="shield" size={20} className="mt-0.5 shrink-0" />
        <span>{t('guardian.safety')}</span>
      </div>
    </div>
  );
}

function Line({ tone, icon, children }: { tone: string; icon: 'check' | 'alert' | 'star'; children: ReactNode }) {
  return (
    <div className="flex items-center gap-2.5">
      <span className={join('grid size-9 shrink-0 place-items-center rounded-control', tone)} aria-hidden="true">
        <Icon name={icon} size={18} />
      </span>
      <span>{children}</span>
    </div>
  );
}

/** Today's play time against the limit, and the guardian's choices: minutes a day, the night lock, games on or off. */
function PlayTime({ learnerId, allowance: a, limits }: { learnerId: string; allowance: Allowance; limits: GameLimits }) {
  const t = useTranslations('games');
  const [saved, setSaved] = useState('');
  const [pending, begin] = useTransition();
  const [shown, setShown] = useState<GameLimits>(limits);
  // A refreshed page brings the saved limits; until then the choice just made shows.
  const [from, setFrom] = useState(limits);
  if (from !== limits) {
    setFrom(limits);
    setShown(limits);
  }
  const used = a.used;
  const circ = 2 * Math.PI * 34;
  const share = usedShare(used, a.limit);
  const choices = minuteChoices(limits);

  const save = (change: Partial<Pick<GameLimits, 'enabled' | 'daily_minutes' | 'night_lock'>>) => {
    const next = { ...shown, ...change };
    setShown(next);
    setSaved('');
    begin(async () => {
      const done = await saveLimits(learnerId, next);
      setSaved(done.ok ? t('guardian.saved') : (done.message ?? t('failed')));
      if (!done.ok) setShown(limits);
    });
  };

  return (
    <section className="rounded-card border border-line bg-white p-4" aria-labelledby="gw-time">
      <div className="flex items-center gap-3.5">
        <svg width="84" height="84" viewBox="0 0 84 84" role="img" aria-label={t('guardian.ring', { used, limit: a.limit ?? '∞' })} className="shrink-0">
          <circle cx="42" cy="42" r="34" fill="none" stroke="var(--color-line)" strokeWidth="10" />
          <circle
            className="gw-ring"
            cx="42"
            cy="42"
            r="34"
            fill="none"
            stroke="var(--color-brand)"
            strokeWidth="10"
            strokeLinecap="round"
            strokeDasharray={`${(circ * share).toFixed(1)} ${circ.toFixed(1)}`}
            transform="rotate(-90 42 42)"
          />
          <text x="42" y="40" textAnchor="middle" fontSize="20" fontWeight="700" fill="currentColor">
            {used}
          </text>
          <text x="42" y="56" textAnchor="middle" fontSize="10" fill="var(--color-muted)">
            {t('guardian.minutes')}
          </text>
        </svg>
        <div className="min-w-0 flex-1">
          <h2 id="gw-time" className="font-semibold">
            {t('guardian.time')}
          </h2>
          <p className="text-[13.5px] text-muted">{a.limit ? t('guardian.timeText', { used, limit: a.limit, left: a.left ?? 0 }) : t('guardian.timeOpen', { used })}</p>
          {!a.may && a.reason ? <p className="text-[13.5px] font-semibold text-attention">{t(`refused.${a.reason}`)}</p> : null}
        </div>
      </div>
      <p className="mt-3 text-[13.5px] font-semibold">{t('guardian.max')}</p>
      <div role="radiogroup" aria-label={t('guardian.maxLabel')} className="mt-1.5 grid gap-1.5" style={{ gridTemplateColumns: `repeat(${Math.min(choices.length + 1, 5)}, minmax(0, 1fr))` }}>
        {[...choices, null].map((m) => {
          const on = shown.daily_minutes === m;
          return (
            <button
              key={m ?? 'academy'}
              type="button"
              role="radio"
              aria-checked={on}
              disabled={pending}
              onClick={() => save({ daily_minutes: m })}
              className={join('min-h-11 rounded-control border text-sm font-semibold transition-colors', on ? 'border-brand bg-brand text-white' : 'border-line bg-white hover:bg-brand-soft')}
            >
              {m === null ? t('guardian.academy') : t('guardian.choice', { n: m })}
            </button>
          );
        })}
      </div>
      <Switch label={t('guardian.night')} on={shown.night_lock} disabled={pending} onChange={(on) => save({ night_lock: on })} />
      <Switch label={t('guardian.enabled')} on={shown.enabled} disabled={pending} onChange={(on) => save({ enabled: on })} />
      {saved ? (
        <p role="status" className="mt-2 text-sm text-muted">
          {saved}
        </p>
      ) : null}
    </section>
  );
}

function Switch({ label, on, disabled, onChange }: { label: string; on: boolean; disabled: boolean; onChange: (on: boolean) => void }) {
  return (
    <label className="mt-2 flex min-h-12 cursor-pointer items-center justify-between gap-2.5 border-t border-line text-sm">
      <span>{label}</span>
      <input type="checkbox" className="size-5 accent-[var(--color-brand)]" checked={on} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
    </label>
  );
}

/** A creature hatched today: the child's, to cheer (Learning's cheers, a few a day). */
function Hatched({ hatched, learnerId }: { hatched: NonNullable<Glance['hatched']>; learnerId: string }) {
  const t = useTranslations('games');
  const [hearts, setHearts] = useState(false);
  const [said, setSaid] = useState('');
  const [pending, begin] = useTransition();
  return (
    <section className="flex items-center gap-3 rounded-card border border-line bg-white p-4" aria-labelledby="gw-hatched">
      <span className="relative grid h-[72px] w-[76px] shrink-0 place-items-center rounded-card bg-reward-soft">
        <span className="gw-hover block">
          <CreatureArt look={hatched.look} size={62} motion line="#24124F" />
        </span>
        {hearts ? (
          <span aria-hidden="true" className="gw-hearts">
            <i>💗</i>
            <i>💖</i>
            <i>💗</i>
          </span>
        ) : null}
      </span>
      <div className="min-w-0 flex-1">
        <h2 id="gw-hatched" className="text-[15px] font-semibold">
          {t('guardian.hatched', { name: hatched.name })}
        </h2>
        <p className="text-[13px] text-muted">{said || (hatched.source === 'mastery' ? t('guardian.hatchedMastery', { found: hatched.found }) : t('guardian.hatchedStars', { found: hatched.found }))}</p>
      </div>
      <button
        type="button"
        className="min-h-11 shrink-0 rounded-control bg-brand px-4 text-[14.5px] font-semibold text-white hover:bg-brand-strong disabled:opacity-50"
        disabled={pending}
        onClick={() => {
          setHearts(true);
          sound.play('win');
          begin(async () => {
            const done = await cheer(learnerId, 'heart');
            setSaid(done.ok ? t('guardian.cheered') : (done.message ?? t('failed')));
          });
        }}
      >
        {t('guardian.cheer')}
      </button>
    </section>
  );
}
