import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Progress } from '@/components/ui';
import { ReportEditor } from '@/components/teach/report-editor';
import { get } from '@/lib/api/server';
import type { ReportForm } from '@/lib/teach/types';

/** One report: today's plan to fill (or the absence note), the family's words, and homework. */
export default async function Report({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, nav, report] = await Promise.all([getTranslations('teach'), getTranslations('nav'), get<{ data: ReportForm }>('/v1/teacher/reports/{report}', { params: { report: id } })]);
  const form = report.data;
  const roadmap = form.roadmap;

  return (
    <div>
      <Link href="/teach/reports" className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {nav('reports')}
      </Link>
      <header className="mb-5">
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="text-2xl font-bold">{form.student?.name}</h1>
          <Badge tone={form.status === 'overdue' ? 'danger' : form.status === 'due' ? 'attention' : 'brand'}>{form.status_label}</Badge>
        </div>
        <p className="mt-1 text-muted">
          {form.kind === 'absence' ? t('reportAbsence') : t('reportLesson')}
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
      <ReportEditor form={form} />
    </div>
  );
}
