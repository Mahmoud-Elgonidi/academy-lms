import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Classroom } from '@/components/teach/classroom';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { FamilyLessons } from '@/lib/family/types';
import { lessonMushaf } from '@/lib/mushaf/server';
import { clock } from '@/lib/time';
import { viewerTimeFormat } from '@/lib/viewer';

/**
 * The learner's lesson room: the seat is asked for only when they choose to
 * go in. A Quran lesson of a course the hifz map belongs to brings its shared
 * mushaf, following the teacher's page.
 */
export default async function LessonRoom({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, locale, format, fam, mushaf] = await Promise.all([getTranslations('family'), getLocale(), viewerTimeFormat(), family(), lessonMushaf(id)]);
  const lessons = fam.current ? await get<FamilyLessons>('/v1/family/lessons', { query: { learner_id: fam.current.id } }) : null;
  const lesson = lessons?.data.find((l) => l.id === id);
  return (
    <div>
      <Link href="/family/lessons" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {t('backToLessons')}
      </Link>
      <header className="mb-5">
        <h1 className="text-2xl font-bold">{lesson ? t('withTeacher', { name: lesson.with }) : t('joinLesson')}</h1>
        {lesson ? (
          <p className="text-muted">
            {lesson.subject} · <span className="whitespace-nowrap tabular-nums">{clock(lesson.starts_at, format, locale)}–{clock(lesson.ends_at, format, locale)}</span>
          </p>
        ) : null}
      </header>
      <Classroom id={id} back={{ href: '/family/lessons', label: t('backToLessons') }} mushaf={mushaf} />
    </div>
  );
}
