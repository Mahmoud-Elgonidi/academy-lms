'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { CheerOn } from '@/components/family/cheers';
import { GuardianGames } from '@/components/games/guardian';
import type { PlayTarget } from '@/lib/games/types';
import { Icon } from '@/components/icons';
import { Button } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { courseOf, findStation, hasStars, missionsOf, missionsOn, practiceStations } from '@/lib/journey/journey';
import type { JourneyMap, LessonMission } from '@/lib/journey/types';
import { LevelCard, LevelsList, NextLessonCard } from './calm';
import { ChestCard, CourseChips, islandNames, MissionList, Note, PlayLayer, StationBadge, StationSheet, useJourneyPlay } from './parts';

/**
 * A guardian's look at their child's journey (the classic look, in the
 * academy's colours): the level and how far, the next lesson and how the last
 * one went (on time, stayed to the end, its stars), the missions and their
 * states, the stars, the week's chest (the child opens it), the games' limits
 * and the week at a glance, cheering, and playing a lesson together. Never
 * pay or KPIs.
 */
export function GuardianJourney({ map, learnerName, autoplay }: { map: JourneyMap; learnerName: string; autoplay: PlayTarget | null }) {
  const t = useTranslations('journey');
  const stars = hasStars(map);
  const layer = stars ? map.games : null;
  const [chosen, setChosen] = useState<string | null>(null);
  const [sheet, setSheet] = useState<string | null>(null);
  const { play, player, note, setNote } = useJourneyPlay(map, autoplay);
  const course = courseOf(map.courses, chosen);
  const missions = missionsOf(map.missions, course?.enrollment_id ?? null);
  const lesson = (missions.find((m) => m.kind === 'lesson') as LessonMission | undefined) ?? null;
  const together = player ? practiceStations(course).slice(0, 4) : [];
  const at = findStation(map, sheet);

  return (
    <div className="stagger space-y-5">
      <h1 className="text-2xl font-bold">{t('journeyOf', { name: learnerName })}</h1>
      <Note text={note} onClose={() => setNote('')} />
      <CourseChips courses={map.courses} chosen={course?.enrollment_id ?? null} onChoose={setChosen} />

      {course ? <LevelCard course={course} /> : null}
      {stars ? (
        <section className="grid grid-cols-2 gap-3" aria-label={t('starsRank', { n: map.stars.total, rank: map.stars.rank.label })}>
          <div className="rounded-card border border-line bg-white p-4">
            <p className="flex items-center gap-1.5 font-heading text-2xl font-bold tabular-nums">
              <Icon name="star" size={22} fill="var(--color-reward)" className="text-reward" />
              {map.stars.total}
            </p>
            <p className="text-sm text-muted">{t('childStars', { rank: map.stars.rank.label })}</p>
          </div>
          <div className="rounded-card border border-line bg-white p-4">
            <p className="flex items-center gap-1.5 font-heading text-2xl font-bold tabular-nums">
              <Icon name="flame" size={22} className="text-attention" />
              {map.streak}
            </p>
            <p className="text-sm text-muted">{t('streakLabel')}</p>
          </div>
        </section>
      ) : null}
      <NextLessonCard lesson={lesson} last={map.last_lesson} stars={stars} />
      <MissionList missions={missions.filter((m) => m !== lesson)} map={map} player={player} stars={stars} onStation={setSheet} />
      {stars && map.chest ? <ChestCard chest={map.chest} /> : null}

      {together.length > 0 && player ? (
        <section className="rounded-card border border-line bg-white p-4" aria-labelledby="j-together">
          <h2 id="j-together" className="font-semibold">
            {t('together')}
          </h2>
          <p className="mb-2 text-sm text-muted">{t('togetherLead', { name: learnerName })}</p>
          {!player.may && player.refusal ? <p className="mb-2 rounded-control bg-attention-soft px-3 py-2 text-sm text-attention">{player.refusal}</p> : null}
          <ul className="space-y-2">
            {together.map((s) => (
              <li key={s.id} className="flex items-center gap-2.5">
                <button type="button" onClick={() => setSheet(s.id)} className="min-w-0 flex-1 text-start">
                  <b className="block truncate">{s.title}</b>
                  <span className="text-sm text-muted">{s.games[0]?.title}</span>
                </button>
                <StationBadge station={s} />
                <Button type="button" className="min-h-10 shrink-0 px-4 text-sm" disabled={!player.may || player.pending} onClick={() => player.start({ game: s.games[0]!.id, station: s.id, bestBefore: s.race?.best_seconds ?? null })}>
                  {t('play')}
                </Button>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {layer ? <GuardianGames learnerId={map.learner_id} allowance={layer.allowance} limits={layer.limits} glance={layer.glance} /> : null}
      <CheerOn learnerId={map.learner_id} name={learnerName} left={map.cheers_left} />
      {course ? <LevelsList course={course} onStation={setSheet} /> : null}

      <footer className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
        {course ? (
          <Link href={`/family/progress/${course.enrollment_id}`} className="font-semibold text-brand-strong hover:underline">
            {t('roadmapReports')}
          </Link>
        ) : null}
        {layer ? (
          <Link href="/family/games/album" className="font-semibold text-brand-strong hover:underline">
            {t('albumOf', { name: learnerName })}
          </Link>
        ) : null}
      </footer>

      {at ? <StationSheet station={at.station} map={map} missions={missionsOn(map.missions, at.station.id)} player={player} stars={stars} onClose={() => setSheet(null)} /> : null}
      <PlayLayer play={play} look="classic" self={false} islandName={islandNames(map)} />
    </div>
  );
}
