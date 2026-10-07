import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Card, Progress, Stat, join, type Tone } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Roadmap, RoadmapLesson, Student } from '@/lib/teach/types';

const stateTone: Record<string, Tone> = { done: 'brand', here: 'attention', review: 'info', todo: 'muted' };

/** A student's roadmap: the program's levels and units, each lesson done, here, or still to come. */
export default async function StudentRoadmap({ params }: { params: Promise<{ enrollment: string }> }) {
  const { enrollment } = await params;
  const [t, nav, roadmap, students] = await Promise.all([
    getTranslations('teach'),
    getTranslations('nav'),
    get<{ data: Roadmap | null }>('/v1/learning/enrollments/{enrollment}/roadmap', { params: { enrollment } }),
    get<{ data: Student[] }>('/v1/teacher/students'),
  ]);
  const student = students.data.find((s) => s.enrollment_id === enrollment);
  const map = roadmap.data;

  return (
    <div>
      <Link href="/teach/students" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {nav('students')}
      </Link>
      <header className="mb-5">
        <h1 className="text-2xl font-bold">{student?.student ?? t('roadmap')}</h1>
        <p className="text-muted">{[student?.subject, map?.program.name].filter(Boolean).join(' · ')}</p>
      </header>
      {map ? (
        <>
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
        </>
      ) : null}
    </div>
  );
}

async function Lessons({ lessons }: { lessons: RoadmapLesson[] }) {
  const t = await getTranslations('teach');
  if (lessons.length === 0) return null;
  return (
    <ul className="divide-y divide-line">
      {lessons.map((lesson) => (
        <li key={lesson.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
          <span className={join('size-2.5 shrink-0 rounded-full', lesson.state === 'done' ? 'bg-brand' : lesson.state === 'here' ? 'bg-attention' : 'bg-line')} aria-hidden="true" />
          <span className={join('min-w-0 flex-1', lesson.state === 'here' && 'font-semibold')}>{lesson.title}</span>
          <span className="text-xs text-muted">{t('seenTimes', { n: lesson.times_seen })}</span>
          {lesson.last_outcome ? <Badge tone={stateTone[lesson.state] ?? 'muted'}>{t(`outcome_${lesson.last_outcome}`)}</Badge> : <Badge>{t(`state_${lesson.state in stateTone ? lesson.state : 'todo'}`)}</Badge>}
        </li>
      ))}
    </ul>
  );
}
