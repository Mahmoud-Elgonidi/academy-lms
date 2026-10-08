import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Classroom } from '@/components/teach/classroom';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { FamilyLessons } from '@/lib/family/types';
import { clock } from '@/lib/time';

/** The learner's lesson room: the seat is asked for only when they choose to go in. */
export default async function LessonRoom({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, fam] = await Promise.all([getTranslations('family'), family()]);
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
            {lesson.subject} · <span className="tabular-nums">{clock(lesson.starts_at)}–{clock(lesson.ends_at)}</span>
          </p>
        ) : null}
      </header>
      <Classroom id={id} back={{ href: '/family/lessons', label: t('backToLessons') }} />
    </div>
  );
}
