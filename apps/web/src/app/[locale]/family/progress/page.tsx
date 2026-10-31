import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { CourseState } from '@/components/family/course-state';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Empty, Heading, Progress, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { JourneyMap } from '@/lib/journey/types';
import { courseGroup } from '@/lib/lesson-format';

/**
 * The learner's courses (from the journey), each with how far along and
 * where now; each opens its roadmap and reports. A group course says so, with
 * its seats and the learner's own, as the journey's course says it.
 */
export default async function ProgressPage() {
  const [t, f, fam] = await Promise.all([getTranslations('family'), getTranslations('format'), family()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const journey = await get<{ data: JourneyMap }>('/v1/learning/journey/map', { query: { learner_id: learner.id } });
  const courses = journey.data.courses;
  return (
    <div>
      <Heading title={t('progressOf', { name: learner.name })} />
      {courses.length === 0 ? (
        <Empty>{t('noCourses')}</Empty>
      ) : (
        <ul className="stagger space-y-3">
          {courses.map((c) => {
            const group = courseGroup(c);
            return (
            <li key={c.enrollment_id}>
              <Link
                href={`/family/progress/${c.enrollment_id}`}
                data-format={group ? 'group' : 'private'}
                className={join('lift block rounded-card border bg-white p-5', group ? 'border-group-line border-s-4 border-s-group' : 'border-line')}
              >
                <span className="flex items-start justify-between gap-3">
                  <span>
                    <span className="block font-heading text-lg font-bold">{c.program ?? c.subject}</span>
                    <span className="block text-sm text-muted">
                      {c.subject}
                      {c.teacher ? ` · ${c.teacher}` : ''}
                    </span>
                    {group ? (
                      <span className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <FormatBadge format="group" seats={{ taken: group.taken, capacity: group.capacity }} />
                        <span className="text-ink">
                          {f('groupOf', { subject: c.subject ?? '' })}
                          {group.seat ? <> · <span className="font-semibold text-group-strong">{f('seatNumber', { seat: group.seat, capacity: group.capacity })}</span></> : null}
                        </span>
                      </span>
                    ) : null}
                    <span className="mt-1 block">
                      <CourseState course={c} stage={fam.stage} />
                    </span>
                  </span>
                  <Icon name="chevron" size={18} className="mt-1 shrink-0 text-muted rtl:-scale-x-100" />
                </span>
                {c.percent !== null ? (
                  <span className="mt-3 block">
                    <Progress value={c.percent} label={c.program ?? c.subject ?? ''} />
                    <span className="mt-1.5 flex flex-wrap justify-between gap-2 text-sm text-muted">
                      <span>{t('percentDone', { p: c.percent })}</span>
                      {c.current?.title ? <span>{t('hereNow', { title: c.current.title })}</span> : null}
                    </span>
                  </span>
                ) : (
                  <span className="mt-2 block text-sm text-muted">{t('noProgram')}</span>
                )}
              </Link>
            </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
