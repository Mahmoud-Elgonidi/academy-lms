import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Badge, Empty, Heading, Notice, SectionTitle } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { ReportSummary } from '@/lib/teach/types';

/** Reports still to write first (the oldest due first), then the ones sent in the last two weeks. */
export default async function Reports({ searchParams }: { searchParams: Promise<{ sent?: string }> }) {
  const [{ sent }, t, nav, reports] = await Promise.all([
    searchParams,
    getTranslations('teach'),
    getTranslations('nav'),
    get<{ data: ReportSummary[] }>('/v1/teacher/reports'),
  ]);
  const open = reports.data.filter((r) => r.status === 'due' || r.status === 'overdue');
  const done = reports.data.filter((r) => r.status === 'submitted' || r.status === 'reviewed');

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

async function List({ reports }: { reports: ReportSummary[] }) {
  const t = await getTranslations('teach');
  const tone = { overdue: 'danger', due: 'attention', submitted: 'brand', reviewed: 'muted' } as const;
  return (
    <ul className="stagger space-y-2.5">
      {reports.map((r) => (
        <li key={r.id}>
          <Link href={`/teach/reports/${r.id}`} className="lift flex items-center gap-3 rounded-card border border-line bg-white p-4">
            <span className="min-w-0 flex-1">
              <span className="flex flex-wrap items-center gap-2">
                <span className="font-semibold">{r.student?.name}</span>
                <Badge tone={tone[r.status]}>{r.status_label}</Badge>
                {r.kind === 'absence' ? <Badge>{t('reportAbsence')}</Badge> : null}
                {r.late ? <Badge tone="attention">{t('late')}</Badge> : null}
              </span>
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
              {r.status === 'due' || r.status === 'overdue' ? t('write') : t('open')}
            </span>
            <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
          </Link>
        </li>
      ))}
    </ul>
  );
}
