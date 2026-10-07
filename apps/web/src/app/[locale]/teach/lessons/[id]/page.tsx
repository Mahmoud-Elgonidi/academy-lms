import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Classroom } from '@/components/teach/classroom';
import { get } from '@/lib/api/server';
import type { LessonDays } from '@/lib/teach/types';
import { clock } from '@/lib/time';

/**
 * A lesson's room. The seat (LiveKit's address and a token for this person,
 * or an outside link) is asked for only when the teacher chooses to go in,
 * never while the page loads or is prefetched.
 */
export default async function Lesson({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, today] = await Promise.all([getTranslations('teach'), get<LessonDays>('/v1/teacher/lessons', { query: { days: 1 } })]);
  const lesson = today.data.find((l) => l.id === id);
  return (
    <div>
      <Link href="/teach" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {t('backToToday')}
      </Link>
      <header className="mb-5">
        <h1 className="text-2xl font-bold">{lesson ? t('withStudent', { name: lesson.with }) : t('classTitle')}</h1>
        {lesson ? (
          <p className="text-muted">
            {lesson.subject} · <span className="tabular-nums">{clock(lesson.starts_at)}–{clock(lesson.ends_at)}</span>
          </p>
        ) : null}
      </header>
      <Classroom id={id} />
    </div>
  );
}
