import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { FormatBadge } from '@/components/lesson-format';
import { Badge, Empty, Heading, Notice, SectionTitle, join } from '@/components/ui';
import { get } from '@/lib/api/server';
import { reportRows, type ReportRow } from '@/lib/teach/group-report';
import type { ReportSummary } from '@/lib/teach/types';

/**
 * Reports still to write first (the oldest due first), then the ones sent in
 * the last two weeks. A group lesson is one line (its students named) that
 * opens the class's form, where every student's report goes in together.
 */
export default async function Reports({ searchParams }: { searchParams: Promise<{ sent?: string }> }) {
  const [{ sent }, t, nav, reports] = await Promise.all([
    searchParams,
    getTranslations('teach'),
    getTranslations('nav'),
    get<{ data: ReportSummary[] }>('/v1/teacher/reports'),
  ]);
  const rows = reportRows(reports.data);
  const open = rows.filter((r) => r.status === 'due' || r.status === 'overdue');
  const done = rows.filter((r) => r.status === 'submitted' || r.status === 'reviewed');

  return (
    <div>
      <Heading title={nav('reports')} />
      {sent ? (
        <div className="mb-4">
          <Notice tone="brand">{t('reportSent')}</Notice>
        </div>
      ) : null}
      <section aria-labelledby="reports-open">
        <SectionTitle id="reports-open" trailing={open.length || undefined}>
          {t('reportsOpen')}
        </SectionTitle>
        {open.length === 0 ? <Empty>{t('noReportsWaiting')}</Empty> : <List reports={open} />}
      </section>
      <section aria-labelledby="reports-sent">
        <SectionTitle id="reports-sent">{t('reportsSent')}</SectionTitle>
        {done.length === 0 ? <Empty>{t('noReportsSent')}</Empty> : <List reports={done} />}
      </section>
    </div>
  );
}

async function List({ reports }: { reports: ReportRow<ReportSummary>[] }) {
  const [t, f] = await Promise.all([getTranslations('teach'), getTranslations('format')]);
  const tone = { overdue: 'danger', due: 'attention', submitted: 'brand', reviewed: 'muted' } as const;
  return (
    <ul className="stagger space-y-2.5">
      {reports.map(({ report: r, students, pressing, status, late }) => (
        <li key={r.id}>
          <Link
            href={`/teach/reports/${r.id}`}
            data-format={students ? 'group' : 'private'}
            className={join('lift flex items-center gap-3 rounded-card border bg-white p-4', students ? 'border-group-line border-s-4 border-s-group' : 'border-line')}
          >
            <span className="min-w-0 flex-1">
              <span className="flex flex-wrap items-center gap-2">
                <span className="font-semibold">{students ? t('groupReport') : r.student?.name}</span>
                {students ? <FormatBadge format="group" /> : null}
                <Badge tone={tone[status]}>{pressing.status_label}</Badge>
                {!students && r.kind === 'absence' ? <Badge>{t('reportAbsence')}</Badge> : null}
                {late ? <Badge tone="attention">{t('late')}</Badge> : null}
              </span>
              {students ? <span className="mt-0.5 block truncate text-sm text-ink">{students.map((s) => s.name).join(f('listJoin'))}</span> : null}
              <span className="mt-0.5 block text-sm text-muted">
                {r.lesson_at ? t('lessonOn', { when: r.lesson_at.label }) : null}
                {r.submitted_at
                  ? ` · ${t('sentOn', { when: r.submitted_at.label })}`
                  : r.due_at
                    ? ` · ${t('reportDueBy', { when: r.due_at.label })}`
                    : null}
              </span>
            </span>
            <span className="hidden text-sm font-semibold text-brand-strong sm:inline">
              {status === 'due' || status === 'overdue' ? t('write') : t('open')}
            </span>
            <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
          </Link>
        </li>
      ))}
    </ul>
  );
}
