import { getLocale, getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Classroom } from '@/components/teach/classroom';
import { get } from '@/lib/api/server';
import { groupOf } from '@/lib/lesson-format';
import { lessonMushaf } from '@/lib/mushaf/server';
import { dataOf, quranRead } from '@/lib/quran/server';
import type { LessonPortion } from '@/lib/quran/types';
import type { Lesson as TeachLesson } from '@/lib/teach/types';
import { clock } from '@/lib/time';
import { viewerTimeFormat } from '@/lib/viewer';

/**
 * A lesson's room, the lesson read by its id (today's, one still on since
 * before midnight, or another day's alike; not the teacher's: not found).
 * The seat (LiveKit's address and a token for this person, or an outside
 * link) is asked for only when the teacher chooses to go in, never while the
 * page loads or is prefetched. A Quran lesson of a course the hifz map
 * belongs to brings its shared mushaf, at the page the teacher has open;
 * one with a Quran plan, the way to its panel (the portion and its record).
 */
export default async function Lesson({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, f, q, locale, format, { data: lesson }, mushaf, quran] = await Promise.all([
    getTranslations('teach'),
    getTranslations('format'),
    getTranslations('quran'),
    getLocale(),
    viewerTimeFormat(),
    get<{ data: TeachLesson }>('/v1/teacher/lessons/{session}', { params: { session: id } }),
    lessonMushaf(id),
    quranRead<LessonPortion>('/v1/quran/lessons/{session}/portion', { params: { session: id } }).then(dataOf),
  ]);
  // A Quran lesson with a plan: its portion and record on its own page (a lesson without one, or the Quran off, shows nothing).
  const portion = quran?.plan ? quran : null;
  const recorded = portion ? portion.students.every((s) => portion.results.some((r) => r.student_id === s.id)) : false;
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
      {portion ? (
        <Link href={`/teach/lessons/${id}/quran`} className="lift mb-4 flex items-center gap-3 rounded-card border border-brand-line bg-white p-4" data-quran-panel>
          <span className="grid size-11 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true">
            <Icon name="book" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-semibold">{q('lesson.panel')}</span>
            <span className="block truncate text-sm text-muted">
              {recorded ? q('lesson.allRecorded') : [portion.plan?.portion?.new?.ranges.map((r) => r.title).join('، '), portion.plan?.portion?.near ? q('parts.near') : null, portion.plan?.portion?.far ? q('parts.far') : null].filter(Boolean).join(' · ') || q('lesson.panelLead')}
            </span>
          </span>
          <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
        </Link>
      ) : null}
      <Classroom id={id} back={{ href: '/teach', label: t('backToToday') }} mushaf={mushaf} title={title} subtitle={lesson.subject} />
    </div>
  );
}
