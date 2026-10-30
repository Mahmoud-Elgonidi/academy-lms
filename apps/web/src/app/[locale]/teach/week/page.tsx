import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { LessonCard } from '@/components/teach/lesson-card';
import { Heading, buttonLook, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { addDays, dayLabel, hoursOf } from '@/lib/format';
import type { Lesson, LessonDays } from '@/lib/teach/types';
import { me } from '@/lib/viewer';

/** Seven days on the teacher's calendar, from today or from the day asked (?from=YYYY-MM-DD). */
export default async function Week({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<{ from?: string }> }) {
  const [{ locale }, { from }] = await Promise.all([params, searchParams]);
  const asked = from && /^\d{4}-\d{2}-\d{2}$/.test(from) ? from : undefined;
  const [t, common, person, week] = await Promise.all([
    getTranslations('teach'),
    getTranslations('common'),
    me(),
    get<LessonDays>('/v1/teacher/lessons', { query: { from: asked, days: 7 } }),
  ]);

  const byDay = new Map<string, Lesson[]>();
  for (let i = 0; i < 7; i++) byDay.set(addDays(week.from, i), []);
  for (const lesson of week.data) byDay.get(lesson.starts_at.local.slice(0, 10))?.push(lesson);

  const short = { day: 'numeric', month: 'short' } as const;
  const nav = (day: string | undefined, label: string, icon: 'prev' | 'next') => (
    <Link href={day ? `/teach/week?from=${day}` : '/teach/week'} className={join(buttonLook('quiet'), 'min-h-10 px-3 text-sm')} aria-label={label} title={label}>
      <Icon name="chevron" size={18} className={icon === 'prev' ? 'ltr:-scale-x-100' : 'rtl:-scale-x-100'} />
    </Link>
  );

  return (
    <div>
      <Heading
        title={t('weekRange', { from: dayLabel(week.from, locale, short), to: dayLabel(week.to, locale, short) })}
        hint={`${common('lessonsHours', { lessons: common('lessons', { n: week.count }), hours: common('hoursAmount', { h: hoursOf(week.minutes) }) })} · ${common('onClock', { zone: person.timezone_label })}`}
        action={
          <div className="flex items-center gap-2">
            {nav(addDays(week.from, -7), t('prevWeek'), 'prev')}
            {asked ? (
              <Link href="/teach/week" className={join(buttonLook('quiet'), 'min-h-10 px-3 text-sm')}>
                {t('thisWeek')}
              </Link>
            ) : null}
            {nav(addDays(week.from, 7), t('nextWeek'), 'next')}
          </div>
        }
      />
      <ol className="stagger space-y-4">
        {[...byDay].map(([day, lessons]) => (
          <li key={day}>
            {lessons.length === 0 ? (
              <p className="flex items-baseline justify-between gap-3 border-b border-dashed border-line pb-2 text-sm">
                <span className="font-semibold text-muted">{dayLabel(day, locale)}</span>
                <span className="text-muted/80">{t('noLessonsDay')}</span>
              </p>
            ) : (
              <>
              <h2 className="mb-2 text-sm font-semibold text-muted">{dayLabel(day, locale)}</h2>
              <ul className="space-y-2.5">
                {lessons.map((lesson) => (
                  <li key={lesson.id}>
                    <LessonCard lesson={lesson} />
                  </li>
                ))}
              </ul>
              </>
            )}
          </li>
        ))}
      </ol>
    </div>
  );
}
