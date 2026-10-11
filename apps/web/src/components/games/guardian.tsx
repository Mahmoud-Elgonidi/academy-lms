'use client';

import { useCallback, useState, useTransition, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { cheer, chooseLearner } from '@/app/[locale]/family/actions';
import { saveLimits } from '@/app/[locale]/family/games/actions';
import { Icon } from '@/components/icons';
import { Notice, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { sound } from '@/lib/games/sound';
import type { GameLimits, World } from '@/lib/games/types';
import { minuteChoices, usedShare } from '@/lib/games/world';
import { CreatureArt } from './art';
import { GameRound } from './round';
import { useMuted } from './use-muted';
import { usePlay } from './use-play';
import './games.css';
import './islands.css';

/**
 * A guardian's view of their children's games (the approved design,
 * Guardian), in the academy's colours: which child, today's play time
 * against the limit with the minutes a day and the night lock, what the
 * child practised this week, a creature hatched today to cheer, the safety
 * promise, and the quests to play together. Pay and KPIs never appear here.
 */
export function GuardianGames({ world, kids, current }: { world: World; kids: { id: string; name: string }[]; current: string }) {
  const t = useTranslations('games');
  const common = useTranslations('common');
  const [note, setNote] = useState('');
  const say = useCallback((text: string) => setNote(text), []);
  const play = usePlay(world.learner_id, world.quests, say);
  const muted = useMuted();
  const [switching, startSwitch] = useTransition();
  const glance = world.glance;
  const a = world.allowance;

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-3">
      <header className="flex min-h-14 flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-bold">{t('guardian.title')}</h1>
        {kids.length > 1 ? (
          <div role="tablist" aria-label={t('guardian.children')} className="flex flex-wrap justify-end gap-1.5">
            {kids.map((child) => (
              <button
                key={child.id}
                type="button"
                role="tab"
                aria-selected={child.id === current}
                disabled={switching}
                onClick={() => startSwitch(() => chooseLearner(child.id))}
                className={join(
                  'min-h-10 rounded-full border px-4 text-[14.5px] transition-colors',
                  child.id === current ? 'border-brand bg-brand font-semibold text-white' : 'border-line bg-white hover:bg-brand-soft',
                )}
              >
                {child.name}
              </button>
            ))}
          </div>
        ) : null}
      </header>

      {note ? <Notice tone="attention">{note}</Notice> : null}

      <PlayTime world={world} />

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

      {glance?.hatched ? <Hatched hatched={glance.hatched} learnerId={world.learner_id} /> : null}

      <div className="flex items-start gap-2.5 rounded-card bg-brand-soft px-3 py-2.5 text-[13.5px] leading-relaxed text-brand-strong">
        <Icon name="shield" size={20} className="mt-0.5 shrink-0" />
        <span>{t('guardian.safety')}</span>
      </div>

      {world.quests.length > 0 ? (
        <section className="rounded-card border border-line bg-white p-4" aria-labelledby="gw-together">
          <h2 id="gw-together" className="mb-2 font-semibold">
            {t('guardian.together')}
          </h2>
          <ul className="flex flex-col gap-2">
            {world.quests.map((q) => (
              <li key={q.key} className="flex items-center gap-2.5">
                <span className="grid size-9 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true">
                  <Icon name="gamepad" size={18} />
                </span>
                <span className="min-w-0 flex-1">
                  <b className="block truncate">{q.title || t(`reasons.${q.reason}`)}</b>
                  <small className="text-muted">
                    {t(`reasons.${q.reason}`)} · {q.game.title}
                  </small>
                </span>
                <button
                  type="button"
                  className="min-h-10 shrink-0 rounded-control bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-strong disabled:opacity-50"
                  onClick={() => {
                    setNote('');
                    play.start(q.game.id, q.key);
                  }}
                  disabled={!a.may || play.pending}
                >
                  {t('play')}
                </button>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {play.played ? (
        <section className="appear rounded-card border border-line bg-white p-4" role="status">
          <h2 className="font-semibold">
            {t(`result.${Math.min(3, play.played.result.stars)}` as 'result.0')} <span className="text-reward">{'★'.repeat(play.played.result.stars)}</span>
          </h2>
          <p className="text-sm text-muted">{t('result.score', { correct: play.played.result.correct, total: play.played.result.total })}</p>
          <button type="button" className="mt-2 text-sm font-semibold text-brand-strong hover:underline" onClick={play.leave}>
            {common('close')}
          </button>
        </section>
      ) : null}

      <Link href="/family/games/album" className="text-center text-sm font-semibold text-brand-strong hover:underline">
        {t('guardian.album', { name: kids.find((c) => c.id === current)?.name ?? '' })}
      </Link>

      {play.round ? <GameRound round={play.round} look="classic" muted={muted} onFinish={play.finish} onClose={play.leave} /> : null}
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
function PlayTime({ world }: { world: World }) {
  const t = useTranslations('games');
  const limits = world.limits!;
  const a = world.allowance;
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
      const done = await saveLimits(world.learner_id, next);
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
function Hatched({ hatched, learnerId }: { hatched: NonNullable<NonNullable<World['glance']>['hatched']>; learnerId: string }) {
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
