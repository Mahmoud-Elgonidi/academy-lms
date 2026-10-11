import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { RoadmapView } from '@/components/roadmap-view';
import { Badge, Card, Empty, SectionTitle } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { ReportAnswers } from '@/components/report-answers';
import type { FamilyReport, Roadmap } from '@/lib/family/types';

/** One course: its roadmap, and the teacher's reports after each lesson (newest first, without internal notes). */
export default async function Course({ params }: { params: Promise<{ enrollment: string }> }) {
  const { enrollment } = await params;
  const [t, fam, roadmap, reports] = await Promise.all([
    getTranslations('family'),
    family(),
    get<{ data: Roadmap | null }>('/v1/learning/enrollments/{enrollment}/roadmap', { params: { enrollment } }),
    get<{ data: FamilyReport[] }>('/v1/learning/enrollments/{enrollment}/reports', { params: { enrollment } }),
  ]);
  const nav = await getTranslations(`nav.${fam.stage}`);
  // Back to where it was opened from: the journey in the classic look, the courses list in a student's.
  const back = fam.stage === 'classic' ? { href: '/family/journey', label: nav('journey') } : { href: '/family/progress', label: nav('progress') };
  const map = roadmap.data;
  return (
    <div>
      <Link href={back.href} className="mb-3 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {back.label}
      </Link>
      <h1 className="mb-5 text-2xl font-bold">{map?.program.name ?? t('roadmap')}</h1>
      {map ? <RoadmapView map={map} /> : null}

      <SectionTitle>{t('reports')}</SectionTitle>
      {reports.data.length === 0 ? (
        <Empty>{t('noReports')}</Empty>
      ) : (
        <ul className="space-y-3">
          {reports.data.map((r) => (
            <li key={r.id}>
              <Card as="article">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-semibold">{r.lesson_at?.label}</p>
                  {r.teacher ? <span className="text-sm text-muted">{r.teacher.name}</span> : null}
                </div>
                {r.kind === 'absence' ? (
                  <div className="mt-2">
                    <Badge tone="attention">{t('absence')}</Badge>
                    {r.absence_note ? <p className="mt-2 whitespace-pre-line">{r.absence_note}</p> : null}
                  </div>
                ) : (
                  <>
                    <ul className="mt-3 space-y-1.5">
                      {r.items.map((i) => (
                        <li key={i.node_id} className="flex flex-wrap items-center gap-2">
                          <span>{i.title}</span>
                          <Badge tone="brand">{i.outcome_label}</Badge>
                          {i.score ? <Badge>{t('starsOf', { n: i.score })}</Badge> : null}
                        </li>
                      ))}
                    </ul>
                    <p className="mt-3 text-sm text-muted">
                      {t('engagement')}: {r.engagement ?? '—'} · {t('overall')}: {r.overall ?? '—'}
                    </p>
                    <ReportAnswers answers={r.answers ?? []} />
                    {r.notes_for_student ? (
                      <div className="mt-3 rounded-control bg-paper p-3">
                        <p className="text-xs font-semibold text-muted">{t('teacherSaid')}</p>
                        <p className="mt-1 whitespace-pre-line">{r.notes_for_student}</p>
                      </div>
                    ) : null}
                  </>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
