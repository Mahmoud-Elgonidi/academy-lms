import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Empty, Heading, Progress } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { Journey } from '@/lib/family/types';

/** The learner's courses, each with how far along and where now; each opens its roadmap and reports. */
export default async function ProgressPage() {
  const [t, fam] = await Promise.all([getTranslations('family'), family()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const journey = await get<{ data: Journey }>('/v1/learning/journey', { query: { learner_id: learner.id } });
  const courses = journey.data.courses;
  return (
    <div>
      <Heading title={t('progressOf', { name: learner.name })} />
      {courses.length === 0 ? (
        <Empty>{t('noCourses')}</Empty>
      ) : (
        <ul className="stagger space-y-3">
          {courses.map((c) => (
            <li key={c.enrollment_id}>
              <Link href={`/family/progress/${c.enrollment_id}`} className="lift block rounded-card border border-line bg-white p-5">
                <span className="flex items-start justify-between gap-3">
                  <span>
                    <span className="block font-heading text-lg font-bold">{c.program ?? c.subject}</span>
                    <span className="block text-sm text-muted">
                      {c.subject}
                      {c.teacher ? ` · ${c.teacher}` : ''}
                    </span>
                  </span>
                  <Icon name="chevron" size={18} className="mt-1 shrink-0 text-muted rtl:-scale-x-100" />
                </span>
                {c.percent !== null ? (
                  <span className="mt-3 block">
                    <Progress value={c.percent} label={c.program ?? c.subject} />
                    <span className="mt-1.5 flex flex-wrap justify-between gap-2 text-sm text-muted">
                      <span>{t('percentDone', { p: c.percent })}</span>
                      {c.next[0]?.title ? <span>{t('hereNow', { title: c.next[0].title })}</span> : null}
                    </span>
                  </span>
                ) : (
                  <span className="mt-2 block text-sm text-muted">{t('noProgram')}</span>
                )}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
