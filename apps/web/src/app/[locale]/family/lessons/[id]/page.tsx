import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Classroom } from '@/components/teach/classroom';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { FamilyLessons } from '@/lib/family/types';
import { groupOf } from '@/lib/lesson-format';
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
  const [t, f, locale, format, fam, mushaf] = await Promise.all([getTranslations('family'), getTranslations('format'), getLocale(), viewerTimeFormat(), family(), lessonMushaf(id)]);
  const lessons = fam.current ? await get<FamilyLessons>('/v1/family/lessons', { query: { learner_id: fam.current.id } }) : null;
  const lesson = lessons?.data.find((l) => l.id === id);
  // A group lesson: its seats and the classmates as the family may see them (first names; the kids' look, how many).
  const group = lesson ? groupOf(lesson) : null;
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
        {group ? (
          <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <FormatBadge format="group" size="md" seats={{ taken: group.taken, capacity: group.capacity }} />
            {group.seatNumber ? <span className="font-semibold text-group-strong">{f('seatNumber', { seat: group.seatNumber, capacity: group.capacity })}</span> : null}
            {group.classmatesLabel ? (
              <span>{group.classmates && group.classmates.length > 0 ? f('named', { label: group.classmatesLabel, names: group.classmates.join(f('listJoin')) }) : group.classmatesLabel}</span>
            ) : null}
          </p>
        ) : null}
      </header>
      <Classroom
        id={id}
        back={{ href: '/family/lessons', label: t('backToLessons') }}
        mushaf={mushaf}
        title={lesson ? t('withTeacher', { name: lesson.with }) : undefined}
        subtitle={lesson?.subject}
      />
    </div>
  );
}
