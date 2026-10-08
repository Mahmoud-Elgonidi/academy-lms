import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { RoadmapView } from '@/components/roadmap-view';
import { get } from '@/lib/api/server';
import type { Roadmap, Student } from '@/lib/teach/types';

/** A student's roadmap: the program's levels and units, each lesson done, here, or still to come. */
export default async function StudentRoadmap({ params }: { params: Promise<{ enrollment: string }> }) {
  const { enrollment } = await params;
  const [t, nav, roadmap, students] = await Promise.all([
    getTranslations('teach'),
    getTranslations('nav'),
    get<{ data: Roadmap | null }>('/v1/learning/enrollments/{enrollment}/roadmap', { params: { enrollment } }),
    get<{ data: Student[] }>('/v1/teacher/students'),
  ]);
  const student = students.data.find((s) => s.enrollment_id === enrollment);
  const map = roadmap.data;

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
      {map ? <RoadmapView map={map} /> : null}
    </div>
  );
}
