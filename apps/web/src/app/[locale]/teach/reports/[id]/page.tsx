import { getLocale, getTranslations } from 'next-intl/server';
import { Link, redirect } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Badge, Progress } from '@/components/ui';
import { ReportEditor } from '@/components/teach/report-editor';
import { get } from '@/lib/api/server';
import type { Surah } from '@/lib/teach/recitation';
import type { ReportForm } from '@/lib/teach/types';

/**
 * One report: today's plan to fill (or the absence note), the family's words,
 * and homework. A group lesson's is the class's: opened from a student who was
 * absent, it opens from one who came, whose report carries the lesson's plan.
 */
export default async function Report({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, f, nav, locale, report] = await Promise.all([
    getTranslations('teach'),
    getTranslations('format'),
    getTranslations('nav'),
    getLocale(),
    get<{ data: ReportForm }>('/v1/teacher/reports/{report}', { params: { report: id } }),
  ]);
  const form = report.data;
  const group = form.group && form.group.students.length > 0 ? form.group : null;
  const came = group?.students.find((s) => s.kind === 'lesson' && s.status !== 'reviewed');
  if (group && form.kind === 'absence' && came && came.report_id !== form.id) redirect({ href: `/teach/reports/${came.report_id}`, locale });
  const roadmap = form.roadmap;
  // The surahs to choose what homework asks to recite, when the form may ask it.
  const surahs = form.assist?.recitation_check || form.homework?.expected ? (await get<{ data: Surah[] }>('/v1/teacher/texts/quran')).data : [];

  return (
    <div>
      <Link href="/teach/reports" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {nav('reports')}
      </Link>
      <header className="mb-5">
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="text-2xl font-bold">{group ? t('groupReport') : form.student?.name}</h1>
          {group ? <FormatBadge format="group" size="md" /> : null}
          <Badge tone={form.status === 'overdue' ? 'danger' : form.status === 'due' ? 'attention' : 'brand'}>{form.status_label}</Badge>
        </div>
        {group ? <p className="mt-1 text-sm text-ink">{group.students.map((s) => s.name).join(f('listJoin'))}</p> : null}
        <p className="mt-1 text-muted">
          {group ? t('reportLesson') : form.kind === 'absence' ? t('reportAbsence') : t('reportLesson')}
          {form.lesson_at ? ` · ${form.lesson_at.label}` : null}
          {form.due_at && (form.status === 'due' || form.status === 'overdue') ? ` · ${t('reportDueBy', { when: form.due_at.label })}` : null}
        </p>
        {roadmap ? (
          <div className="mt-4 max-w-md">
            <p className="mb-1.5 text-sm text-muted">{t('roadmapHere', { program: roadmap.program.name, percent: roadmap.percent })}</p>
            <Progress value={roadmap.percent} label={roadmap.program.name} />
          </div>
        ) : null}
      </header>
      <ReportEditor form={form} surahs={surahs} />
    </div>
  );
}
