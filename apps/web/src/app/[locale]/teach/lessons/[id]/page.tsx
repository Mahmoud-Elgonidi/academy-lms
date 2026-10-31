import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Classroom } from '@/components/teach/classroom';
import { get } from '@/lib/api/server';
import { groupOf } from '@/lib/lesson-format';
import { lessonMushaf } from '@/lib/mushaf/server';
import type { Lesson as TeachLesson } from '@/lib/teach/types';
import { clock } from '@/lib/time';
import { viewerTimeFormat } from '@/lib/viewer';

/**
 * A lesson's room, the lesson read by its id (today's, one still on since
 * before midnight, or another day's alike; not the teacher's: not found).
 * The seat (LiveKit's address and a token for this person, or an outside
 * link) is asked for only when the teacher chooses to go in, never while the
 * page loads or is prefetched. A Quran lesson of a course the hifz map
 * belongs to brings its shared mushaf, at the page the teacher has open.
 */
export default async function Lesson({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, f, locale, format, { data: lesson }, mushaf] = await Promise.all([
    getTranslations('teach'),
    getTranslations('format'),
    getLocale(),
    viewerTimeFormat(),
    get<{ data: TeachLesson }>('/v1/teacher/lessons/{session}', { params: { session: id } }),
    lessonMushaf(id),
  ]);
  // A group lesson: the class by its subject, its seats, and everyone seated (the room shows each one's tile).
  const group = groupOf(lesson);
  const title = group ? f('groupOf', { subject: lesson.subject }) : t('withStudent', { name: lesson.with });
  return (
    <div>
      <Link href="/teach" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {t('backToToday')}
      </Link>
      <header className="mb-5">
        <h1 className="text-2xl font-bold">{title}</h1>
        <p className="text-muted">
          {lesson.subject} · <span className="whitespace-nowrap tabular-nums">{clock(lesson.starts_at, format, locale)}–{clock(lesson.ends_at, format, locale)}</span>
        </p>
        {group ? (
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <FormatBadge format="group" size="md" seats={{ taken: group.taken, capacity: group.capacity }} />
            {group.students.length > 0 ? (
              <p className="text-sm text-muted">
                <span className="sr-only">{t('roster')}: </span>
                {group.students.map((s) => s.name).join(f('listJoin'))}
              </p>
            ) : null}
          </div>
        ) : null}
      </header>
      <Classroom id={id} back={{ href: '/teach', label: t('backToToday') }} mushaf={mushaf} title={title} subtitle={lesson.subject} />
    </div>
  );
}
