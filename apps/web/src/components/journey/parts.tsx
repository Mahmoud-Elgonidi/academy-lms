'use client';

import { useCallback, useEffect, useRef, useState, useSyncExternalStore, useTransition, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { cheersSeen, openChest } from '@/app/[locale]/family/actions';
import { hatchEgg } from '@/app/[locale]/family/games/actions';
import { CHEERS } from '@/components/family/cheers';
import { EggIcon } from '@/components/games/art';
import { Hatch } from '@/components/games/results';
import { GameRound } from '@/components/games/round';
import { useMuted } from '@/components/games/use-muted';
import { usePlay, type Played } from '@/components/games/use-play';
import type { PlayTarget } from '@/lib/games/types';
import { Icon, type IconName } from '@/components/icons';
import { useClock } from '@/components/time-format';
import { Badge, Button, buttonLook, join, Progress, type Tone } from '@/components/ui';
import { Link, usePathname, useRouter } from '@/i18n/navigation';
import { chestShare, countdown, findStation, hasStars, homeworkGame, markOf, playsOnly, SEEN_LESSON_KEY } from '@/lib/journey/journey';
import type { Chest, HatchedCreature, HomeworkMission, JourneyCourse, JourneyMap, LastLesson, LessonMission, Mission, OpenedChest, Station } from '@/lib/journey/types';
import '@/components/games/games.css';
import '@/components/games/islands.css';

/*
 * The journey's parts every look shares (docs/architecture/02, section 18), in
 * the academy's colours: the course chips, a mission's row, a station's sheet,
 * the week's chest, the egg, the last lesson's stars and the guardians'
 * cheers. The kids' map draws its own in the world's art (journey/kids.tsx).
 */

/** What a play button needs from the page: whether a round may start now (and why not), and how to start one. */
export type Player = { may: boolean; refusal: string | null; start: (target: PlayTarget) => void; pending: boolean };

/** The courses, when there is more than one: the journey shows one at a time. */
export function CourseChips({ courses, chosen, onChoose, className }: { courses: JourneyCourse[]; chosen: string | null; onChoose: (id: string) => void; className?: string }) {
  const t = useTranslations('journey');
  if (courses.length < 2) return null;
  return (
    <div role="group" aria-label={t('courses')} className={join('flex gap-2 overflow-x-auto pb-1', className)}>
      {courses.map((c) => {
        const on = c.enrollment_id === chosen;
        return (
          <button
            key={c.enrollment_id}
            type="button"
            aria-pressed={on}
            onClick={() => onChoose(c.enrollment_id)}
            className={join('min-h-10 shrink-0 rounded-full border px-4 text-sm font-semibold transition-colors', on ? 'border-brand bg-brand text-white' : 'border-line bg-white hover:bg-brand-soft')}
          >
            {c.program ?? c.subject}
          </button>
        );
      })}
    </div>
  );
}

const minute = () => Math.floor(Date.now() / 60_000);
const tick = (changed: () => void) => {
  const every = setInterval(changed, 20_000);
  return () => clearInterval(every);
};

/** How long until a moment, in words ("in 2 hours"); nothing on the server or once it came (the page refreshes then). */
export function Countdown({ utc, className }: { utc: string; className?: string }) {
  const t = useTranslations('journey');
  const now = useSyncExternalStore(tick, minute, () => null);
  if (now === null) return null;
  const left = countdown(utc, now * 60_000);
  if (!left) return null;
  const text = left.days > 0 ? t('inDays', { n: left.days }) : left.hours > 0 ? t('inHours', { n: left.hours }) : t('inMinutes', { n: Math.max(1, left.minutes) });
  return <span className={className}>{text}</span>;
}

/** The way into a lesson: its room once open, else when it opens. */
export function LessonJoin({ mission, className }: { mission: LessonMission; className?: string }) {
  const t = useTranslations('journey');
  const { clock } = useClock();
  if (mission.join.open) {
    return (
      <Link href={`/family/lessons/${mission.lesson_id}`} className={join(buttonLook('primary'), 'min-h-10 px-4 text-sm', className)}>
        <Icon name="video" size={18} />
        {t('join')}
      </Link>
    );
  }
  return mission.join.opens_at ? (
    <span className={join('text-sm text-muted', className)}>
      {t('opensAt', { time: clock(mission.join.opens_at) })} <Countdown utc={mission.join.opens_at.utc} className="font-semibold text-ink" />
    </span>
  ) : null;
}

const KIND_ICON: Record<Mission['kind'], IconName> = { lesson: 'video', homework: 'book', review: 'spark' };
const KIND_TONE: Record<Mission['kind'], string> = { lesson: 'bg-brand-soft text-brand-strong', homework: 'bg-info-soft text-info', review: 'bg-reward-soft text-ink' };

/** A homework's state as a badge: handed in, late, or nothing while open (its due time says it). */
function homeworkTone(m: HomeworkMission): { tone: Tone; key: 'handedIn' | 'late' } | null {
  if (m.state === 'done') return { tone: 'brand', key: 'handedIn' };
  if (m.state === 'late') return { tone: 'attention', key: 'late' };
  return null;
}

/**
 * One mission as a row: the next lesson (its time and the way in), the
 * teacher's homework (due, "play" when it asks for practice, "hand in"
 * otherwise, its result), a review due (opens its station). Stars it brings,
 * when the journey has stars.
 */
export function MissionRow({ mission: m, map, player, stars, onStation }: { mission: Mission; map: Pick<JourneyMap, 'courses'>; player: Player | null; stars: boolean; onStation?: (stationId: string) => void }) {
  const t = useTranslations('journey');
  const station = findStation(map, m.station_id);
  // A review is its lesson already; the others say which lesson they are on.
  const where = station && m.kind !== 'review' ? t('onStation', { title: station.station.title }) : null;
  let line: ReactNode = where;
  let action: ReactNode = null;
  let badge: { tone: Tone; text: string } | null = null;

  if (m.kind === 'lesson') {
    line = (
      <>
        {m.starts_at.label}
        {where ? ` · ${where}` : ''}
      </>
    );
    action = <LessonJoin mission={m} />;
  } else if (m.kind === 'homework') {
    const game = player ? homeworkGame(m, map) : null;
    const state = homeworkTone(m);
    badge = state ? { tone: state.tone, text: t(state.key) } : null;
    line = (
      <>
        {m.due_at ? t('dueBy', { when: m.due_at.label }) : where}
        {m.result ? <span className="block text-brand-strong">{t('practiceResult', { correct: m.result.correct, total: m.result.total, stars: m.result.stars })}</span> : null}
      </>
    );
    if (m.state !== 'done') {
      action = (
        <span className="flex flex-wrap items-center gap-2">
          {game && player ? (
            <Button type="button" className="min-h-10 px-4 text-sm" disabled={!player.may || player.pending} onClick={() => player.start({ game, homework: m.homework_id })}>
              <Icon name="play" size={16} />
              {t('play')}
            </Button>
          ) : null}
          {!playsOnly(m) ? (
            <Link href={`/family/homework#hw-${m.homework_id}`} className={join(buttonLook(game ? 'quiet' : 'primary'), 'min-h-10 px-4 text-sm')}>
              {t('handIn')}
            </Link>
          ) : null}
        </span>
      );
    }
  } else if (station && onStation) {
    action = (
      <Button type="button" variant="quiet" className="min-h-10 px-4 text-sm" onClick={() => onStation(station.station.id)}>
        <Icon name="spark" size={16} />
        {t('review')}
      </Button>
    );
  }

  const brings = stars && m.kind !== 'review' && m.state !== 'done' ? m.stars : null;
  return (
    <li className="flex flex-wrap items-center gap-3 rounded-card border border-line bg-white p-4">
      <span className={join('grid size-11 shrink-0 place-items-center rounded-control', KIND_TONE[m.kind])} aria-hidden="true">
        <Icon name={KIND_ICON[m.kind]} />
      </span>
      <span className="min-w-0 flex-1">
        <span className="flex flex-wrap items-center gap-1.5">
          <span className="text-xs font-semibold text-muted">{m.kind_label}</span>
          {badge ? <Badge tone={badge.tone}>{badge.text}</Badge> : null}
          {brings ? (
            <span className="inline-flex items-center gap-0.5 text-xs font-semibold text-ink" aria-label={t('brings', { n: brings })}>
              +{brings}
              <Icon name="star" size={13} fill="var(--color-reward)" className="text-reward" />
            </span>
          ) : null}
        </span>
        <span className="block font-semibold">{m.kind === 'review' ? t('reviewOf', { title: m.title ?? '' }) : (m.title ?? m.kind_label)}</span>
        {line ? <span className="block text-sm text-muted">{line}</span> : null}
      </span>
      {action ? <span className="w-full sm:w-auto">{action}</span> : null}
    </li>
  );
}

/** The missions to do now, as a list (an empty one says so). */
export function MissionList({ missions, map, player, stars, onStation }: { missions: Mission[]; map: Pick<JourneyMap, 'courses'>; player: Player | null; stars: boolean; onStation?: (stationId: string) => void }) {
  const t = useTranslations('journey');
  return (
    <section aria-labelledby="j-missions">
      <h2 id="j-missions" className="mb-2 font-heading text-lg font-bold">
        {t('missions')}
      </h2>
      {missions.length === 0 ? (
        <p className="rounded-card border border-dashed border-line bg-white/60 p-5 text-center text-muted">{t('noMissions')}</p>
      ) : (
        <ul className="stagger space-y-2.5">
          {missions.map((m) => (
            <MissionRow key={m.key} mission={m} map={map} player={player} stars={stars} onStation={onStation} />
          ))}
        </ul>
      )}
    </section>
  );
}

const MARK_TONE: Record<ReturnType<typeof markOf>, Tone> = { done: 'brand', review: 'attention', here: 'info', todo: 'muted' };

/** A station's state in words, as a badge. */
export function StationBadge({ station }: { station: Pick<Station, 'state' | 'due_review'> }) {
  const t = useTranslations('journey');
  const mark = markOf(station);
  return <Badge tone={MARK_TONE[mark]}>{t(`mark.${mark}`)}</Badge>;
}


/**
 * A station opened (in the academy's colours): the lesson, its state, its
 * missions, the race against yourself, and the games that play its own items
 * (a round on the station). A lesson still ahead says when it opens.
 */
export function StationSheet({ station, unitOf, map, missions, player, stars, onClose }: {
  station: Station;
  unitOf?: string | null;
  map: Pick<JourneyMap, 'courses'>;
  missions: Mission[];
  player: Player | null;
  stars: boolean;
  onClose: () => void;
}) {
  const t = useTranslations('journey');
  const common = useTranslations('common');
  const close = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    close.current?.focus({ preventScroll: true });
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);
  const games = station.playable ? station.games : [];
  const race = station.race;
  const secs = (s: number | null) => (s === null ? '—' : t('seconds', { s }));
  return (
    <div className="fixed inset-0 z-40 flex items-end justify-center bg-ink/40 sm:items-center sm:p-6" role="presentation" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <section role="dialog" aria-modal="true" aria-labelledby="j-station" className="appear max-h-[88dvh] w-full max-w-lg overflow-y-auto rounded-t-card bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-xl sm:rounded-card">
        <header className="flex items-start gap-3">
          <div className="min-w-0 flex-1">
            {unitOf ?? station.unit ? <p className="text-sm text-muted">{unitOf ?? station.unit}</p> : null}
            <h2 id="j-station" className="font-heading text-xl font-bold">
              {station.title}
            </h2>
            <div className="mt-1.5 flex flex-wrap items-center gap-2">
              <StationBadge station={station} />
              {station.practice && station.practice.rounds > 0 ? <span className="text-sm text-muted">{t('practised', { rounds: station.practice.rounds, percent: station.practice.percent })}</span> : null}
            </div>
          </div>
          <button ref={close} type="button" onClick={onClose} aria-label={common('close')} className="grid size-10 shrink-0 place-items-center rounded-control hover:bg-paper">
            <Icon name="x" size={20} />
          </button>
        </header>

        {missions.length > 0 ? (
          <ul className="mt-4 space-y-2">
            {missions.map((m) => (
              <MissionRow key={m.key} mission={m} map={map} player={player} stars={stars} />
            ))}
          </ul>
        ) : null}

        {race && stars ? (
          <div className="mt-4 rounded-card bg-paper p-4" aria-label={t('race')}>
            <p className="font-semibold">{t('race')}</p>
            <dl className="mt-2 grid grid-cols-3 gap-2 text-center text-sm">
              <div>
                <dt className="text-muted">{t('raceBest')}</dt>
                <dd className="font-heading text-lg font-bold tabular-nums">{secs(race.best_seconds)}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('raceLast')}</dt>
                <dd className="font-heading text-lg font-bold tabular-nums">{secs(race.last_seconds)}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('raceRounds')}</dt>
                <dd className="font-heading text-lg font-bold tabular-nums">{race.rounds}</dd>
              </div>
            </dl>
          </div>
        ) : null}

        {player && games.length > 0 ? (
          <div className="mt-4">
            <h3 className="mb-2 text-sm font-semibold text-muted">{t('games')}</h3>
            {!player.may && player.refusal ? <p className="mb-2 rounded-control bg-attention-soft px-3 py-2 text-sm text-attention">{player.refusal}</p> : null}
            <ul className="space-y-2">
              {games.map((g) => (
                <li key={g.id} className="flex items-center gap-3 rounded-card border border-line p-3">
                  <span className="grid size-10 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true">
                    <Icon name="gamepad" size={20} />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-1.5">
                      <b>{g.title}</b>
                      {g.chosen ? <Badge tone="info">{t('teacherChose')}</Badge> : null}
                    </span>
                    <span className="block text-sm text-muted">{g.summary}</span>
                  </span>
                  <Button type="button" className="min-h-10 shrink-0 px-4 text-sm" disabled={!player.may || player.pending} onClick={() => player.start({ game: g.id, station: station.id, bestBefore: race?.best_seconds ?? null })}>
                    {t('play')}
                  </Button>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        {station.state === 'todo' && !station.playable ? <p className="mt-4 rounded-card bg-paper p-4 text-sm text-muted">{t('stationAhead')}</p> : null}
        {player && station.playable && games.length === 0 ? <p className="mt-4 text-sm text-muted">{t('noGames')}</p> : null}
      </section>
    </div>
  );
}

/**
 * The week's chest: how many of the week's missions are done, then (for the
 * student) the chest to open, with its stars and the kids' sticker; missed
 * and opened ones say so. A guardian only follows it.
 */
export function ChestCard({ chest, kids = false, onOpened }: { chest: Chest; kids?: boolean; onOpened?: (opened: OpenedChest) => void }) {
  const t = useTranslations('journey');
  const common = useTranslations('common');
  const [pending, begin] = useTransition();
  const [message, setMessage] = useState('');
  const key = useRef<string | null>(null);
  const ready = chest.state === 'done';
  const open = () =>
    begin(async () => {
      setMessage('');
      key.current ??= crypto.randomUUID();
      const done = await openChest(chest.period, key.current);
      if (done.ok && done.opened) onOpened?.(done.opened);
      else setMessage(done.message ?? common('problem'));
    });
  return (
    <section
      aria-labelledby="j-chest"
      className={join('rounded-card border p-4', ready && chest.can_open ? 'nudge border-reward bg-reward-soft' : chest.state === 'opened' ? 'border-brand-line bg-brand-soft' : 'border-line bg-white')}
    >
      <div className="flex items-center gap-3">
        <span className={join('grid size-12 shrink-0 place-items-center rounded-control', ready ? 'bg-reward text-white' : 'bg-paper text-muted')} aria-hidden="true">
          <Icon name="chest" size={26} />
        </span>
        <div className="min-w-0 flex-1">
          <h2 id="j-chest" className="font-semibold">
            {t('chest')}
          </h2>
          <p className="text-sm text-muted">
            {chest.state === 'opened'
              ? chest.sticker && kids
                ? t('chestOpenedSticker', { name: chest.sticker.name })
                : t('chestOpened')
              : chest.state === 'missed'
                ? t('chestMissed')
                : ready
                  ? chest.can_open
                    ? t('chestReady', { n: chest.stars })
                    : t('chestReadyChild')
                  : t('chestProgress', { done: chest.progress, total: chest.target })}
          </p>
        </div>
        {ready && chest.can_open ? (
          <Button type="button" className="shrink-0 bg-reward text-ink hover:bg-reward" disabled={pending} onClick={open}>
            {t('openChest')}
          </Button>
        ) : null}
      </div>
      {chest.state === 'active' || chest.state === 'missed' ? (
        <div className="mt-3">
          <Progress value={chestShare(chest)} label={t('chestProgress', { done: chest.progress, total: chest.target })} />
        </div>
      ) : null}
      {message ? (
        <p role="alert" className="mt-2 text-sm text-danger">
          {message}
        </p>
      ) : null}
    </section>
  );
}

/** What an opened chest brought, over the page until closed: its stars, the kids' sticker, a new rank. */
export function ChestOpened({ opened, onClose }: { opened: OpenedChest; onClose: () => void }) {
  const t = useTranslations('journey');
  const common = useTranslations('common');
  return (
    <div role="status" className="pop fixed inset-x-4 bottom-24 z-40 mx-auto flex max-w-md items-center gap-3 rounded-card border border-reward bg-reward-soft p-4 shadow-lg lg:bottom-8">
      <span className="grid size-12 shrink-0 place-items-center rounded-full bg-reward text-white" aria-hidden="true">
        <Icon name="star" size={26} fill="currentColor" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-heading text-lg font-bold">{t('chestGot', { n: opened.stars })}</span>
        {opened.sticker ? <span className="block text-sm">{t('chestSticker', { name: opened.sticker.name })}</span> : null}
        {opened.ranked_up ? <span className="block text-sm font-semibold text-brand-strong">{t('rankedUp', { rank: opened.rank.label })}</span> : null}
      </span>
      <button type="button" onClick={onClose} className="grid size-9 shrink-0 place-items-center rounded-control hover:bg-white/60" aria-label={common('close')}>
        <Icon name="x" size={18} />
      </button>
    </div>
  );
}

const seenStore = {
  subscribe(changed: () => void) {
    window.addEventListener('storage', changed);
    document.addEventListener(SEEN_LESSON_KEY, changed);
    return () => {
      window.removeEventListener('storage', changed);
      document.removeEventListener(SEEN_LESSON_KEY, changed);
    };
  },
  read(): string | null {
    try {
      return window.localStorage.getItem(SEEN_LESSON_KEY);
    } catch {
      return null;
    }
  },
  mark(id: string) {
    try {
      window.localStorage.setItem(SEEN_LESSON_KEY, id);
    } catch {
      // A private window: it shows again next time, nothing more.
    }
    document.dispatchEvent(new Event(SEEN_LESSON_KEY));
  },
};

/** Whether this lesson's stars were shown on this device already (the server says yes, so nothing jumps in). */
export function useLessonSeen(id: string | null): [boolean, () => void] {
  const seen = useSyncExternalStore(seenStore.subscribe, seenStore.read, () => id);
  const mark = useCallback(() => {
    if (id) seenStore.mark(id);
  }, [id]);
  return [!id || seen === id, mark];
}

/** The stars of a lesson as a row of stars. */
export function StarRow({ n, size = 18, label }: { n: number; size?: number; label: string }) {
  return (
    <span className="inline-flex items-center gap-0.5" role="img" aria-label={label}>
      {Array.from({ length: Math.max(0, n) }, (_, i) => (
        <Icon key={i} name="star" size={size} fill="var(--color-reward)" className="text-reward" />
      ))}
    </span>
  );
}

/**
 * The last lesson's stars, once per lesson on this device: how many, on time
 * or how late, and staying to the end, as the API words them.
 */
export function LastLessonStars({ last, stars }: { last: LastLesson; stars: boolean }) {
  const t = useTranslations('journey');
  const common = useTranslations('common');
  const [seen, mark] = useLessonSeen(last.id);
  if (seen) return null;
  return (
    <section className="pop flex items-center gap-3 rounded-card border border-reward bg-reward-soft p-4" aria-labelledby="j-last">
      <div className="min-w-0 flex-1">
        <h2 id="j-last" className="font-semibold">
          {t('lastLesson')}
          {last.starts_at ? <span className="ms-1.5 text-sm font-normal text-muted">{last.starts_at.label}</span> : null}
        </h2>
        <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
          {stars && last.stars > 0 ? <StarRow n={last.stars} label={t('starsN', { n: last.stars })} /> : null}
          <span className="text-sm font-semibold">{last.labels.join(' · ')}</span>
        </p>
      </div>
      <button type="button" onClick={mark} className="grid size-9 shrink-0 place-items-center rounded-control hover:bg-white/60" aria-label={common('close')}>
        <Icon name="x" size={18} />
      </button>
    </section>
  );
}

/** The guardians' cheers the student has not seen yet; thanking marks them seen. */
export function CheersCard({ cheers }: { cheers: JourneyMap['cheers'] }) {
  const t = useTranslations('journey');
  const [hidden, setHidden] = useState(false);
  const [pending, begin] = useTransition();
  if (hidden || cheers.length === 0) return null;
  const kinds = [...new Set(cheers.map((c) => c.kind))];
  return (
    <section className="pop flex flex-wrap items-center gap-3 rounded-card border border-brand-line bg-brand-soft p-4" aria-live="polite">
      <span className="flex gap-1 text-brand-strong" aria-hidden="true">
        {kinds.map((k) => (
          <Icon key={k} name={CHEERS.find((c) => c.kind === k)?.icon ?? 'clap'} size={28} />
        ))}
      </span>
      <p className="min-w-0 flex-1 font-heading text-lg font-bold">{t('cheered', { n: cheers.length })}</p>
      <Button
        type="button"
        className="min-h-10 px-4 text-sm"
        disabled={pending}
        onClick={() =>
          begin(async () => {
            await cheersSeen();
            setHidden(true);
          })
        }
      >
        {t('thanks')}
      </Button>
    </section>
  );
}

/** A round's result in the academy's colours (middle school, teens, adults, a guardian playing together): stars, first-time right, the race, homework handed in, the egg. */
export function RoundResult({ played, race, onAgain, onLeave }: { played: Played; race: boolean; onAgain: () => void; onLeave: () => void }) {
  const t = useTranslations('journey');
  const games = useTranslations('games');
  const r = played.result;
  const again = useRef<HTMLButtonElement>(null);
  useEffect(() => again.current?.focus({ preventScroll: true }), []);
  const before = played.target.bestBefore ?? null;
  // The race is a lesson's (a homework's round has none).
  const record = race && played.target.station && r.seconds !== null ? (before === null ? t('raceFirst') : r.seconds < before ? t('raceBeat') : t('raceClose')) : null;
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-4 sm:items-center" role="presentation">
      <section role="dialog" aria-modal="true" aria-labelledby="j-result" className="pop w-full max-w-sm rounded-card bg-white p-5 text-center shadow-xl">
        {record ? <p className="text-sm font-semibold text-brand-strong">{record}</p> : null}
        <h2 id="j-result" className="font-heading text-2xl font-bold">
          {games(`result.${Math.min(3, r.stars)}` as 'result.0')}
        </h2>
        <p className="mt-2 flex justify-center">
          <StarRow n={r.stars} size={30} label={games('stars', { n: r.stars })} />
        </p>
        <p className="mt-2 text-muted">
          {games('result.score', { correct: r.correct, total: r.total })}
          {r.seconds !== null ? ` · ${t('seconds', { s: r.seconds })}` : ''}
        </p>
        {r.homework?.handed_in ? <p className="mt-2 rounded-control bg-brand-soft px-3 py-2 text-sm font-semibold text-brand-strong">{games('homeworkDone', { title: r.homework.title })}</p> : null}
        {r.creature ? <p className="mt-2 text-sm font-semibold">{games('hatched', { name: r.creature.name })}</p> : null}
        <p className="mt-2 text-sm text-muted">{games('egg', { have: r.egg.have, need: r.egg.need })}</p>
        <div className="mt-4 grid gap-2">
          <button ref={again} type="button" className={buttonLook('primary')} onClick={onAgain}>
            {games('again')}
          </button>
          <Button type="button" variant="quiet" onClick={onLeave}>
            {t('back')}
          </Button>
        </div>
      </section>
    </div>
  );
}

/** A small note in the page (a refusal, a round that did not start). */
export function Note({ text, onClose }: { text: string; onClose: () => void }) {
  const common = useTranslations('common');
  if (!text) return null;
  return (
    <div role="status" className="flex items-center gap-3 rounded-card bg-attention-soft px-4 py-3 text-sm text-attention">
      <span className="flex-1">{text}</span>
      <button type="button" onClick={onClose} aria-label={common('close')} className="grid size-8 place-items-center rounded-control hover:bg-white/60">
        <Icon name="x" size={16} />
      </button>
    </div>
  );
}

/**
 * Playing from a journey in the academy's colours: the round's player for the
 * buttons (null in the plain style or without games), a homework's practice
 * started on arrival (?homework=, then forgotten), and what to say when a
 * round does not start.
 */
export function useJourneyPlay(map: JourneyMap, autoplay: PlayTarget | null) {
  const games = useTranslations('games');
  const router = useRouter();
  const path = usePathname();
  const [note, setNote] = useState('');
  const say = useCallback((text: string) => setNote(text), []);
  const play = usePlay(map.learner_id, say);
  const layer = hasStars(map) ? map.games : null;
  const refusal = layer && !layer.allowance.may ? (layer.allowance.reason_label ?? (layer.allowance.reason ? games(`refused.${layer.allowance.reason}`) : null)) : null;
  const start = play.start;
  const begin = useCallback(
    (target: PlayTarget) => {
      setNote('');
      start(target);
    },
    [start],
  );
  const player: Player | null = layer ? { may: layer.allowance.may, refusal, start: begin, pending: play.pending } : null;

  const arrived = useRef(false);
  useEffect(() => {
    if (arrived.current || !autoplay) return;
    arrived.current = true;
    begin(autoplay);
    router.replace(path);
  }, [autoplay, begin, router, path]);

  return { play, player, note, setNote };
}

/**
 * The round over the page and what comes after it: the result (with the race
 * for middle school), or a creature hatching for the student to name. A
 * guardian playing together sees the result only (naming is the child's).
 */
export function PlayLayer({ play, look, race = false, self, islandName }: {
  play: ReturnType<typeof usePlay>;
  look: string;
  race?: boolean;
  self: boolean;
  islandName: (key: string) => string;
}) {
  const muted = useMuted();
  const r = play.played?.result;
  return (
    <>
      {play.round ? <GameRound round={play.round} look={look} muted={muted} onFinish={play.finish} onClose={play.leave} /> : null}
      {r && play.played ? (
        r.creature && self ? (
          <Hatch creature={r.creature as HatchedCreature} need={r.egg.need} islandName={islandName} onLeave={play.leave} />
        ) : (
          <RoundResult played={play.played} race={race} onAgain={play.again} onLeave={play.leave} />
        )
      ) : null}
    </>
  );
}

/** Where a creature lives, by its island's key, from the journey's own islands. */
export function islandNames(map: Pick<JourneyMap, 'courses'>): (key: string) => string {
  return (key) => map.courses.flatMap((c) => c.islands).find((i) => i.art?.key === key)?.art?.name ?? '';
}

/**
 * The egg the journey's stars fill (in the academy's colours): ready, a
 * button that opens the hatching (three taps, then the API hatches it);
 * otherwise how many stars to the next.
 */
export function EggCard({ egg, onHatch }: { egg: { have: number; need: number; ready: boolean }; onHatch: () => void }) {
  const t = useTranslations('journey');
  if (egg.ready) {
    return (
      <button type="button" onClick={onHatch} className="nudge flex w-full items-center gap-3 rounded-card border border-reward bg-reward-soft p-4 text-start">
        <span className="gw-egg-wob shrink-0" aria-hidden="true">
          <EggIcon width={36} height={44} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block font-semibold">{t('eggReady')}</span>
          <span className="block text-sm text-muted">{t('eggTap')}</span>
        </span>
        <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
      </button>
    );
  }
  return (
    <section className="flex items-center gap-3 rounded-card border border-line bg-white p-4" aria-label={t('egg', { have: egg.have, need: egg.need })}>
      <span className="shrink-0" aria-hidden="true">
        <EggIcon width={30} height={37} />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{t('egg', { have: egg.have, need: egg.need })}</span>
        <span className="mt-2 block">
          <Progress value={(100 * egg.have) / Math.max(1, egg.need)} label={t('egg', { have: egg.have, need: egg.need })} />
        </span>
      </span>
    </section>
  );
}

/** The hatching of the egg the journey's stars filled, on the island the learner is on. */
export function EggHatch({ map, island, onLeave }: { map: JourneyMap; island: string | null; onLeave: () => void }) {
  const games = useTranslations('games');
  const key = useRef<string | null>(null);
  if (!map.games) return null;
  return (
    <Hatch
      creature={null}
      need={map.games.egg.need}
      islandName={islandNames(map)}
      crack={async () => {
        key.current ??= crypto.randomUUID();
        const done = await hatchEgg(island, key.current);
        return done.ok ? { creature: done.creature } : { failed: done.message ?? games('failed') };
      }}
      onLeave={onLeave}
    />
  );
}
