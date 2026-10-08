import { getTranslations } from 'next-intl/server';
import { Badge, Card, Progress, Stat, join, type Tone } from '@/components/ui';
import type { Roadmap, RoadmapLesson } from '@/lib/teach/types';

const stateTone: Record<string, Tone> = { done: 'brand', here: 'attention', review: 'info', todo: 'muted' };

/**
 * A course's roadmap (RoadmapQuery): how far along, where the student is, the
 * finish at this pace, then each level and unit with every lesson done, here,
 * or still to come. Teachers and families see the same.
 */
export async function RoadmapView({ map }: { map: Roadmap }) {
  const t = await getTranslations('roadmap');
  return (
    <div>
      <div className="stagger grid grid-cols-2 gap-3 sm:grid-cols-3">
        <Stat value={`${map.percent}%`} label={t('lessonsMastered', { done: map.lessons.mastered, total: map.lessons.total })} />
        {map.current ? <Stat value={<span className="text-lg">{map.current.title}</span>} label={t('state_here')} /> : null}
        <Stat
          value={<span className="text-lg">{map.finished ? t('finished') : map.eta_weeks !== null ? t('etaWeeks', { n: Math.round(map.eta_weeks) }) : '—'}</span>}
          label={map.pace !== null ? t('pace', { pace: map.pace }) : t('roadmap')}
        />
      </div>
      <div className="mt-4">
        <Progress value={map.percent} label={map.program.name} />
      </div>
      <ol className="mt-6 space-y-4">
        {map.levels.map((level) => (
          <li key={level.id}>
            <Card className={join(level.has_current && 'border-brand-line')}>
              <div className="flex items-baseline justify-between gap-3">
                <h2 className="font-semibold">{level.title}</h2>
                <span className="text-sm tabular-nums text-muted">{level.percent}%</span>
              </div>
              <Lessons lessons={level.lessons} />
              {level.units.map((unit) => (
                <div key={unit.id} className="mt-4">
                  <div className="mb-2 flex items-baseline justify-between gap-3">
                    <h3 className="text-sm font-semibold text-muted">{unit.title}</h3>
                    <span className="text-xs tabular-nums text-muted">{unit.percent}%</span>
                  </div>
                  <Lessons lessons={unit.lessons} />
                </div>
              ))}
            </Card>
          </li>
        ))}
      </ol>
    </div>
  );
}

async function Lessons({ lessons }: { lessons: RoadmapLesson[] }) {
  const t = await getTranslations('roadmap');
  if (lessons.length === 0) return null;
  return (
    <ul className="divide-y divide-line">
      {lessons.map((lesson) => (
        <li key={lesson.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
          <span className={join('size-2.5 shrink-0 rounded-full', lesson.state === 'done' ? 'bg-brand' : lesson.state === 'here' ? 'bg-attention' : 'bg-line')} aria-hidden="true" />
          <span className={join('min-w-0 flex-1', lesson.state === 'here' && 'font-semibold')}>{lesson.title}</span>
          <span className="text-xs text-muted">{t('seenTimes', { n: lesson.times_seen })}</span>
          {lesson.last_outcome ? (
            <Badge tone={stateTone[lesson.state] ?? 'muted'}>{t(`outcome_${lesson.last_outcome}`)}</Badge>
          ) : (
            <Badge>{t(`state_${lesson.state in stateTone ? lesson.state : 'todo'}`)}</Badge>
          )}
        </li>
      ))}
    </ul>
  );
}
