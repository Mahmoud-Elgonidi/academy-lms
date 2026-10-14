'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { CreatureArt } from '@/components/games/art';
import type { PlayTarget } from '@/lib/games/types';
import { CourseState } from '@/components/family/course-state';
import { Icon } from '@/components/icons';
import { Progress, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { courseOf, findStation, hasHomework, hasStars, hereIsland, markOf, missionsOf, missionsOn, starsToNext } from '@/lib/journey/journey';
import type { JourneyIsland, JourneyMap, OpenedChest } from '@/lib/journey/types';
import { ChestCard, ChestOpened, CheersCard, CourseChips, EggCard, EggHatch, islandNames, LastLessonStars, MissionList, Note, PlayLayer, StationSheet, useJourneyPlay } from './parts';

/**
 * Middle school's journey (docs/architecture/02, section 18), in their look:
 * the stars as a bar to the next rank, the lessons attended in a row, the
 * missions, the week's chest and the egg, and each level as a path of its
 * lessons; a lesson opens with its missions, the race against yourself and
 * the games that play its items.
 */
export function MiddleJourney({ map, learnerName, autoplay }: { map: JourneyMap; learnerName: string; autoplay: PlayTarget | null }) {
  const t = useTranslations('journey');
  const stars = hasStars(map);
  const layer = stars ? map.games : null;
  const [chosen, setChosen] = useState<string | null>(null);
  const [sheet, setSheet] = useState<string | null>(null);
  const [hatching, setHatching] = useState(false);
  const [opened, setOpened] = useState<OpenedChest | null>(null);
  const { play, player, note, setNote } = useJourneyPlay(map, autoplay);
  const course = courseOf(map.courses, chosen);
  const here = hereIsland(course);
  const missions = missionsOf(map.missions, course?.enrollment_id ?? null);
  const rank = map.stars.rank;
  const left = starsToNext(rank);
  const at = findStation(map, sheet);

  return (
    <div className="stagger space-y-5">
      <header className="rounded-card border border-brand-line bg-gradient-to-br from-brand-soft to-white p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <h1 className="text-2xl font-bold">{t('hello', { name: learnerName })}</h1>
            {stars ? <p className="text-sm font-semibold text-brand-strong">{rank.label}</p> : null}
          </div>
          {layer ? (
            <Link href="/family/games/album" className="flex shrink-0 flex-col items-center text-xs font-semibold text-brand-strong hover:underline">
              <span className="grid size-12 place-items-center overflow-hidden rounded-full border border-brand-line bg-white">
                <CreatureArt look={layer.buddy?.look ?? here?.creatures[0]?.look ?? {}} size={40} />
              </span>
              {t('album')}
            </Link>
          ) : null}
        </div>
        {stars ? (
          <div className="mt-3">
            <p className="flex items-center gap-1.5 font-heading text-3xl font-extrabold tabular-nums">
              <Icon name="star" size={26} fill="var(--color-reward)" className="text-reward" />
              {map.stars.total}
              <span className="text-base font-semibold text-muted">{t('yourStars')}</span>
            </p>
            <div className="mt-2">
              <div className="mb-1.5 flex justify-between gap-2 text-sm">
                <span className="font-semibold">{rank.next_label ? t('toRank', { rank: rank.next_label }) : t('topRank')}</span>
                {left !== null ? <span className="text-muted">{t('starsLeft', { n: left })}</span> : null}
              </div>
              <Progress value={rank.next === null ? 100 : rank.progress} label={rank.label} />
            </div>
          </div>
        ) : null}
        <div className="mt-3 flex flex-wrap gap-2">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-attention-soft px-3 py-1 text-sm font-semibold text-attention">
            <Icon name="flame" size={16} />
            {t('streak', { n: map.streak })}
          </span>
        </div>
      </header>

      {map.last_lesson ? <LastLessonStars last={map.last_lesson} stars={stars} /> : null}
      <CheersCard cheers={map.cheers} />
      <Note text={note} onClose={() => setNote('')} />
      <CourseChips courses={map.courses} chosen={course?.enrollment_id ?? null} onChoose={setChosen} />
      {course ? <CourseState course={course} stage="middle" block /> : null}

      <MissionList missions={missions} map={map} player={player} stars={stars} onStation={setSheet} />

      {stars && (layer || map.chest) ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {layer ? <EggCard egg={layer.egg} onHatch={() => setHatching(true)} /> : null}
          {map.chest ? <ChestCard chest={map.chest} onOpened={setOpened} /> : null}
        </div>
      ) : null}

      <section aria-labelledby="j-levels">
        <h2 id="j-levels" className="mb-2 font-heading text-lg font-bold">
          {t('levels')}
        </h2>
        {!course || course.islands.length === 0 ? (
          <p className="rounded-card border border-dashed border-line bg-white/60 p-5 text-center text-muted">{t('noCurriculum')}</p>
        ) : (
          <div className="space-y-4">
            {course.islands.map((island) => (
              <Level key={island.id} island={island} stars={stars} onStation={setSheet} />
            ))}
          </div>
        )}
      </section>

      {at ? <StationSheet station={at.station} map={map} missions={missionsOn(map.missions, at.station.id)} player={player} stars={stars} onClose={() => setSheet(null)} /> : null}
      <PlayLayer play={play} look="middle" race self islandName={islandNames(map)} />
      {hatching ? <EggHatch map={map} island={here?.art?.key ?? null} onLeave={() => setHatching(false)} /> : null}
      {opened ? <ChestOpened opened={opened} onClose={() => setOpened(null)} /> : null}
    </div>
  );
}

const MARK_LOOK = {
  done: 'border-brand bg-brand text-white',
  review: 'border-attention bg-attention-soft text-attention',
  here: 'border-reward bg-reward-soft text-ink',
  todo: 'border-dashed border-line bg-white text-muted',
} as const;

/** A level: its own island's name, how far along, and its lessons as a path down. */
function Level({ island, stars, onStation }: { island: JourneyIsland; stars: boolean; onStation: (id: string) => void }) {
  const t = useTranslations('journey');
  return (
    <section className={join('rounded-card border bg-white p-4', island.state === 'here' ? 'border-brand-line' : 'border-line', island.state === 'ahead' && 'opacity-80')} aria-labelledby={`lv-${island.id}`}>
      <header className="flex items-center gap-3">
        <span className="grid size-11 shrink-0 place-items-center rounded-control bg-paper text-2xl" aria-hidden="true">
          {island.state === 'ahead' ? '🔒' : island.state === 'done' ? '🏆' : (island.art?.emoji ?? '📍')}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold text-muted">{t('levelN', { n: island.index })}</p>
          <h3 id={`lv-${island.id}`} className="truncate font-semibold">
            {island.title}
          </h3>
        </div>
        <span className="shrink-0 text-sm font-semibold tabular-nums text-muted">{t('percent', { p: island.percent })}</span>
      </header>
      <div className="mt-2">
        <Progress value={island.percent} label={island.title} />
      </div>
      <ol className="relative mt-3 space-y-1 ps-6 before:absolute before:inset-y-3 before:start-[1.35rem] before:w-0.5 before:bg-line">
        {island.stations.map((s) => {
          const mark = markOf(s);
          return (
            <li key={s.id} className="relative">
              <button type="button" onClick={() => onStation(s.id)} data-station={s.id} data-games={s.playable ? s.games.length : 0} className="flex w-full items-center gap-3 rounded-control px-2 py-1.5 text-start hover:bg-paper">
                <span className={join('relative z-[1] -ms-6 grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm font-bold', MARK_LOOK[mark])} aria-hidden="true">
                  {mark === 'done' ? <Icon name="check" size={16} /> : mark === 'review' ? '↺' : mark === 'here' ? <Icon name="flag" size={16} /> : <Icon name="lock" size={14} />}
                </span>
                <span className="min-w-0 flex-1">
                  {s.unit ? <span className="block truncate text-xs text-muted">{s.unit}</span> : null}
                  <span className={join('block truncate', mark === 'here' ? 'font-bold' : mark === 'todo' ? 'text-muted' : 'font-medium')}>{s.title}</span>
                </span>
                {hasHomework(s) ? (
                  <span className="shrink-0 text-info">
                    <Icon name="book" size={16} />
                    <span className="sr-only">{t('hasHomework')}</span>
                  </span>
                ) : null}
                {stars && s.race?.best_seconds != null ? <span className="shrink-0 text-xs font-semibold tabular-nums text-muted">⏱ {t('seconds', { s: s.race.best_seconds })}</span> : null}
                {s.playable && s.games.length > 0 ? <Icon name="gamepad" size={18} className="shrink-0 text-brand" aria-hidden="true" /> : null}
              </button>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
