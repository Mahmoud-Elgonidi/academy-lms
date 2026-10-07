import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Empty, Heading, Progress } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Student } from '@/lib/teach/types';

/** The teacher's students, each with where they are in the curriculum and what waits. */
export default async function Students() {
  const [t, nav, students] = await Promise.all([getTranslations('teach'), getTranslations('nav'), get<{ data: Student[] }>('/v1/teacher/students')]);
  return (
    <div>
      <Heading title={nav('students')} />
      {students.data.length === 0 ? (
        <Empty>{t('noStudents')}</Empty>
      ) : (
        <ul className="stagger grid gap-3 sm:grid-cols-2">
          {students.data.map((s) => (
            <li key={s.enrollment_id}>
              <Link href={`/teach/students/${s.enrollment_id}`} className="lift block h-full rounded-card border border-line bg-white p-4">
                <span className="flex items-start gap-2">
                  <span className="min-w-0 flex-1">
                    <span className="block font-semibold">{s.student}</span>
                    <span className="block text-sm text-muted">{s.subject}</span>
                  </span>
                  <Icon name="chevron" size={18} className="mt-1 shrink-0 text-muted rtl:-scale-x-100" />
                </span>
                <span className="mt-2 flex flex-wrap gap-1.5">
                  {s.paused ? <Badge tone="attention">{t('paused')}</Badge> : null}
                  {s.to_mark > 0 ? <Badge tone="info">{t('toMark', { n: s.to_mark })}</Badge> : null}
                </span>
                {s.percent !== null ? (
                  <span className="mt-3 block">
                    <Progress value={s.percent} label={s.subject} />
                    <span className="mt-1.5 flex justify-between gap-2 text-xs text-muted">
                      <span className="truncate">{s.here ? t('hereNow', { title: s.here }) : null}</span>
                      <span className="tabular-nums">{s.percent}%</span>
                    </span>
                  </span>
                ) : null}
                {s.last_report_at ? <span className="mt-2 block text-xs text-muted">{t('lastReportAt', { when: s.last_report_at.label })}</span> : null}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
