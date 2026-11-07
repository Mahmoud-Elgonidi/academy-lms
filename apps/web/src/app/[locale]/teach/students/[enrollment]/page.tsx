import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { TeacherHifzSection } from '@/components/hifz/teacher';
import { Icon } from '@/components/icons';
import { RefreshOn } from '@/components/live-refresh';
import { MistakesNotebook } from '@/components/mushaf/notebook';
import { RoadmapView } from '@/components/roadmap-view';
import { call, get } from '@/lib/api/server';
import type { TeacherHifz } from '@/lib/hifz/types';
import { teacherNotebook } from '@/lib/mushaf/server';
import type { Roadmap, Student } from '@/lib/teach/types';
import { areaGet } from '@/lib/modules-server';
import { dataOf, quranRead } from '@/lib/quran/server';
import type { StudentPlan } from '@/lib/quran/types';

/**
 * The student's hifz map, on a course it follows (the curriculum reads the Quran, or pages are on the map already). An extra on the
 * roadmap's page, so a map that cannot be read leaves the page as it was.
 */
async function hifzOf(enrollment: string): Promise<TeacherHifz | null> {
  const response = await call('get', '/v1/teacher/students/{enrollment}/hifz', { params: { enrollment } }).catch(() => null);
  if (!response?.ok) return null;
  const { data } = (await response.json()) as { data: TeacherHifz };
  return data.pages_total > 0 && data.follows ? data : null;
}

/**
 * A student's roadmap: the program's levels and units, each lesson done,
 * here, or still to come; then their hifz map and, with it, their mistakes
 * notebook (the words marked in their lessons' shared mushaf); a course
 * with a Quran plan leads to it (its portion, pace and results).
 */
export default async function StudentRoadmap({ params }: { params: Promise<{ enrollment: string }> }) {
  const { enrollment } = await params;
  const [t, m, q, nav, roadmap, students, hifz, book, quran] = await Promise.all([
    getTranslations('teach'),
    getTranslations('mushaf'),
    getTranslations('quran'),
    getTranslations('nav'),
    // The roadmap is the curriculum's: while the academy has it off, none (the rest of the page stays).
    areaGet<{ data: Roadmap | null }>('/v1/learning/enrollments/{enrollment}/roadmap', { params: { enrollment } }).then((r) => (r.off ? null : r.data.data)),
    get<{ data: Student[] }>('/v1/teacher/students'),
    hifzOf(enrollment),
    teacherNotebook(enrollment),
    // The course's Quran plan, when it has one (the Quran off, or another subject: nothing).
    quranRead<StudentPlan>('/v1/quran/students/{enrollment}/plan', { params: { enrollment } }).then(dataOf),
  ]);
  const plan = quran?.plan ?? null;
  const student = students.data.find((s) => s.enrollment_id === enrollment);
  const map = roadmap;

  return (
    <div>
      <Link href="/teach/students" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {nav('students')}
      </Link>
      <header className="mb-5">
        <h1 className="text-2xl font-bold">{student?.student ?? t('roadmap')}</h1>
        <p className="text-muted">{[student?.subject, map?.program.name].filter(Boolean).join(' · ')}</p>
      </header>
      {plan ? (
        <Link href={`/teach/students/${enrollment}/quran`} className="lift mb-5 flex items-center gap-3 rounded-card border border-brand-line bg-white p-4" data-quran-plan>
          <span className="grid size-11 shrink-0 place-items-center rounded-control bg-brand-soft text-brand-strong" aria-hidden="true">
            <Icon name="book" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block font-semibold">{q('student.title')}</span>
            <span className="block truncate text-sm text-muted">{[plan.position?.title, q('percent', { n: Math.round(plan.progress * 100) })].filter(Boolean).join(' · ')}</span>
          </span>
          <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
        </Link>
      ) : null}
      {map ? <RoadmapView map={map} /> : null}
      {hifz ? <TeacherHifzSection enrollment={enrollment} hifz={hifz} /> : null}
      {hifz && book ? (
        <>
          <MistakesNotebook book={book} title={m('book')} lead={m('bookTeacherLead')} empty={m('bookEmpty')} teacher />
          <RefreshOn signals={['mushaf.changed']} />
        </>
      ) : null}
    </div>
  );
}
