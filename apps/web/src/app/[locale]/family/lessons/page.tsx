import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { FamilyLessonCard } from '@/components/family/lesson-card';
import { LessonActions } from '@/components/family/lesson-actions';
import { Empty, Heading, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { lessonActionsFor } from '@/lib/family/rules';
import { dayLabel, zoneName } from '@/lib/format';
import type { FamilyLessons } from '@/lib/family/types';
import { me } from '@/lib/viewer';

/** The learner's lessons: the coming four weeks, or the last four (?when=past), with what the family may do with each. */
export default async function Lessons({ searchParams }: { searchParams: Promise<{ when?: string }> }) {
  const { when } = await searchParams;
  const past = when === 'past';
  const [t, common, locale, fam, person] = await Promise.all([getTranslations('family'), getTranslations('common'), getLocale(), family(), me()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const lessons = await get<FamilyLessons>('/v1/family/lessons', { query: { learner_id: learner.id, when: past ? 'past' : undefined } });

  const tab = (value: 'upcoming' | 'past', label: string) => (
    <Link
      href={value === 'upcoming' ? '/family/lessons' : '/family/lessons?when=past'}
      aria-current={(value === 'past') === past ? 'page' : undefined}
      className={join('min-h-10 rounded-control px-4 py-2 text-sm font-semibold', (value === 'past') === past ? 'bg-white text-brand-strong shadow-sm' : 'text-muted hover:text-ink')}
    >
      {label}
    </Link>
  );

  return (
    <div>
      <Heading title={learner.is_self ? t('lessonsOf', { name: learner.name.split(' ')[0] ?? learner.name }) : t('lessonsOf', { name: learner.name })} hint={common('onClock', { zone: zoneName(person.display_timezone, locale) })} />
      {lessons.notices.length > 0 ? (
        <ul className="mb-4 space-y-2">
          {lessons.notices.map((n) => (
            <li key={n} className="rounded-card bg-info-soft px-4 py-3 text-sm text-info">
              {n}
            </li>
          ))}
        </ul>
      ) : null}
      <nav className="mb-5 inline-flex gap-1 rounded-control bg-line/50 p-1">
        {tab('upcoming', t('upcoming'))}
        {tab('past', t('past'))}
      </nav>
      {lessons.data.length === 0 ? (
        <Empty>{past ? t('noPast') : t('noUpcoming')}</Empty>
      ) : (
        <ul className="stagger space-y-3">
          {lessons.data.map((lesson) => {
            const allowed = lessonActionsFor(lesson, fam.stage);
            return (
              <li key={lesson.id}>
                <FamilyLessonCard
                  lesson={lesson}
                  actions={allowed ? <LessonActions id={lesson.id} allowed={allowed} day={dayLabel(lesson.starts_at.local.slice(0, 10), locale)} /> : undefined}
                />
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
