'use client';

import { useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import type { PlayTarget } from '@/lib/games/types';
import { Icon } from '@/components/icons';
import { Progress, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { dayLabel } from '@/lib/format';
import { courseOf, findStation, hasStars, hereIsland, markOf, missionsOf, missionsOn } from '@/lib/journey/journey';
import type { JourneyCourse, JourneyMap, LastLesson, LessonMission, OpenedChest } from '@/lib/journey/types';
import { ChestCard, ChestOpened, CheersCard, CourseChips, EggHatch, islandNames, LessonJoin, MissionList, Note, PlayLayer, StarRow, StationBadge, StationSheet, useJourneyPlay } from './parts';

/**
 * The calm journey (docs/architecture/02, section 18): teens and adult
 * students, and everyone when the academy chose the calm style. The level
 * they are on and how far, the next lesson with the last one's stars, the
 * missions as a list, the week's chest, and the levels as an accordion of
 * their lessons; practising a lesson in a game is there, never in the way.
 */
export function CalmJourney({ map, title, autoplay, look }: { map: JourneyMap; title: string; autoplay: PlayTarget | null; look: string }) {
  const t = useTranslations('journey');
  const stars = hasStars(map);
  const layer = stars ? map.games : null;
  const [chosen, setChosen] = useState<string | null>(null);
  const [sheet, setSheet] = useState<string | null>(null);
  const [hatching, setHatching] = useState(false);
  const [opened, setOpened] = useState<OpenedChest | null>(null);
  const { play, player, note, setNote } = useJourneyPlay(map, autoplay);
  const course = courseOf(map.courses, chosen);
  const missions = missionsOf(map.missions, course?.enrollment_id ?? null);
  const lesson = (missions.find((m) => m.kind === 'lesson') as LessonMission | undefined) ?? null;
  const at = findStation(map, sheet);

  return (
    <div className="stagger space-y-5">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="text-2xl font-bold">{title}</h1>
        {stars ? (
          <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-muted">
            <Icon name="star" size={16} fill="var(--color-reward)" className="text-reward" />
            {t('starsRank', { n: map.stars.total, rank: map.stars.rank.label })}
          </span>
        ) : null}
      </header>

      <CheersCard cheers={map.cheers} />
      <Note text={note} onClose={() => setNote('')} />
      <CourseChips courses={map.courses} chosen={course?.enrollment_id ?? null} onChoose={setChosen} />

      {course ? <LevelCard course={course} /> : null}
      <NextLessonCard lesson={lesson} last={map.last_lesson} stars={stars} />
      <MissionList missions={missions.filter((m) => m !== lesson)} map={map} player={player} stars={stars} onStation={setSheet} />
      {stars && map.chest ? <ChestCard chest={map.chest} onOpened={setOpened} /> : null}
      {course ? <LevelsList course={course} onStation={setSheet} /> : null}

      <footer className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
        {course ? (
          <Link href={`/family/progress/${course.enrollment_id}`} className="font-semibold text-brand-strong hover:underline">
            {t('roadmapReports')}
          </Link>
        ) : null}
        {layer ? (
          <Link href="/family/games/album" className="font-semibold text-brand-strong hover:underline">
            {t('albumCount', { found: layer.creatures_found, total: layer.creatures_total })}
          </Link>
        ) : null}
        {layer?.egg.ready ? (
          <button type="button" onClick={() => setHatching(true)} className="font-semibold text-brand-strong hover:underline">
            {t('eggReadySmall')}
          </button>
        ) : null}
      </footer>

      {at ? <StationSheet station={at.station} map={map} missions={missionsOn(map.missions, at.station.id)} player={player} stars={stars} onClose={() => setSheet(null)} /> : null}
      <PlayLayer play={play} look={look} self={map.viewer === 'self'} islandName={islandNames(map)} />
      {hatching ? <EggHatch map={map} island={hereIsland(course)?.art?.key ?? null} onLeave={() => setHatching(false)} /> : null}
      {opened ? <ChestOpened opened={opened} onClose={() => setOpened(null)} /> : null}
    </div>
  );
}

/** The level a course is on: which of how many, how far, lessons mastered, and the finish at this pace. */
export function LevelCard({ course }: { course: JourneyCourse }) {
  const t = useTranslations('journey');
  const locale = useLocale();
  const island = hereIsland(course);
  const name = course.program ?? course.subject ?? '';
  return (
    <section className="rounded-card border border-line bg-white p-5" aria-labelledby={`lvl-${course.enrollment_id}`}>
      <p className="text-sm text-muted">
        {course.subject}
        {course.teacher ? ` · ${course.teacher}` : ''}
      </p>
      <h2 id={`lvl-${course.enrollment_id}`} className="font-heading text-xl font-bold">
        {name}
      </h2>
      {!course.has_curriculum ? (
        <p className="mt-2 text-sm text-muted">{t('noCurriculum')}</p>
      ) : (
        <>
          {island ? <p className="mt-2 font-semibold">{t('levelOf', { n: island.index, total: course.islands.length, title: island.title })}</p> : null}
          {course.percent !== null ? (
            <div className="mt-2">
              <Progress value={course.percent} label={name} />
              <p className="mt-1.5 flex flex-wrap justify-between gap-2 text-sm text-muted">
                <span>{t('percentDone', { p: course.percent })}</span>
                {course.lessons ? <span>{t('mastered', { done: course.lessons.mastered, total: course.lessons.total })}</span> : null}
              </p>
            </div>
          ) : null}
          {course.finished ? (
            <p className="mt-2 font-semibold text-brand-strong">{t('finished')}</p>
          ) : course.finish_on ? (
            <p className="mt-2 text-sm">
              <span className="text-muted">{t('finishOn')} </span>
              <span className="font-semibold">{dayLabel(course.finish_on.local.slice(0, 10), locale, { day: 'numeric', month: 'long' })}</span>
            </p>
          ) : null}
          {course.current ? <p className="mt-1 text-sm text-muted">{t('now', { title: course.current.title })}</p> : null}
        </>
      )}
    </section>
  );
}

/** The next lesson (the way in when it opens), and the stars the last one brought. */
export function NextLessonCard({ lesson, last, stars }: { lesson: LessonMission | null; last: LastLesson | null; stars: boolean }) {
  const t = useTranslations('journey');
  if (!lesson && !last) return null;
  return (
    <section className="rounded-card border border-brand-line bg-white p-5" aria-labelledby="j-next">
      <h2 id="j-next" className="text-sm font-semibold text-muted">
        {t('nextLesson')}
      </h2>
      {lesson ? (
        <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="font-heading text-lg font-bold">{lesson.title}</p>
            <p className="text-sm text-muted">{lesson.starts_at.label}</p>
            {stars ? <p className="text-xs text-muted">{t('lessonStars')}</p> : null}
          </div>
          <LessonJoin mission={lesson} />
        </div>
      ) : (
        <p className="mt-1 text-muted">{t('noLesson')}</p>
      )}
      {last ? (
        <p className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-line pt-3 text-sm">
          <span className="text-muted">{t('lastLesson')}:</span>
          {stars && last.stars > 0 ? <StarRow n={last.stars} size={15} label={t('starsN', { n: last.stars })} /> : null}
          <span className="font-semibold">{last.labels.join(' · ')}</span>
        </p>
      ) : null}
    </section>
  );
}

/** The course's levels, each its lessons with their states; the level they are on open. A lesson opens its sheet. */
export function LevelsList({ course, onStation }: { course: JourneyCourse; onStation: (id: string) => void }) {
  const t = useTranslations('journey');
  if (course.islands.length === 0) return null;
  return (
    <section aria-labelledby="j-levels">
      <h2 id="j-levels" className="mb-2 font-heading text-lg font-bold">
        {t('levels')}
      </h2>
      <div className="space-y-2">
        {course.islands.map((island) => (
          <details key={island.id} open={island.state === 'here'} className="group rounded-card border border-line bg-white">
            <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
              <span className={join('size-3 shrink-0 rounded-full', island.state === 'done' ? 'bg-brand' : island.state === 'here' ? 'bg-reward' : 'bg-line')} aria-hidden="true" />
              <span className="min-w-0 flex-1">
                <span className="block text-xs text-muted">{t('levelN', { n: island.index })}</span>
                <span className={join('block font-semibold', island.state === 'ahead' && 'text-muted')}>{island.title}</span>
              </span>
              <span className="shrink-0 text-sm tabular-nums text-muted">{t('percent', { p: island.percent })}</span>
              <Icon name="chevron" size={18} className="shrink-0 text-muted transition-transform group-open:rotate-90 rtl:-scale-x-100 rtl:group-open:-rotate-90" />
            </summary>
            <ul className="divide-y divide-line border-t border-line">
              {island.stations.map((s) => {
                const mark = markOf(s);
                return (
                  <li key={s.id}>
                    <button type="button" onClick={() => onStation(s.id)} data-station={s.id} data-games={s.playable ? s.games.length : 0} className="flex min-h-12 w-full items-center gap-3 px-4 py-2.5 text-start hover:bg-paper">
                      <span className="min-w-0 flex-1">
                        {s.unit ? <span className="block text-xs text-muted">{s.unit}</span> : null}
                        <span className={join('block', mark === 'here' ? 'font-semibold' : mark === 'todo' && 'text-muted')}>{s.title}</span>
                      </span>
                      <StationBadge station={s} />
                      {s.playable && s.games.length > 0 ? (
                        <span className="shrink-0 text-brand">
                          <Icon name="gamepad" size={18} />
                          <span className="sr-only">{t('canPractise')}</span>
                        </span>
                      ) : null}
                    </button>
                  </li>
                );
              })}
            </ul>
          </details>
        ))}
      </div>
    </section>
  );
}
