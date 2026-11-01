import { notFound } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { QuranOff } from '@/components/quran/off';
import { PlanProgress, PortionCards, RecentResults } from '@/components/quran/parts';
import { PaceForm } from '@/components/quran/recorder';
import { Badge, Card, Empty, Notice, SectionTitle } from '@/components/ui';
import { get } from '@/lib/api/server';
import { quranRead } from '@/lib/quran/server';
import type { StudentPlan } from '@/lib/quran/types';
import type { Student } from '@/lib/teach/types';

/**
 * A student's Quran plan for their teacher: where they are and how far
 * along, the next lesson's portion, their pace (the new amount a lesson,
 * the near and far review; empty goes back to the program's; a group's plan
 * is the office's), and the last lessons' results with the teacher's notes.
 */
export default async function StudentQuranPlan({ params }: { params: Promise<{ enrollment: string }> }) {
  const { enrollment } = await params;
  const [t, found, students] = await Promise.all([
    getTranslations('quran'),
    quranRead<StudentPlan>('/v1/quran/students/{enrollment}/plan', { params: { enrollment } }),
    get<{ data: Student[] }>('/v1/teacher/students'),
  ]);
  if (found.state === 'off') return <QuranOff title={t('student.title')} />;
  if (found.state === 'missing') notFound();
  const { plan, recent } = found.data;
  const student = students.data.find((s) => s.enrollment_id === enrollment);

  return (
    <div className="space-y-5">
      <Link href={`/teach/students/${enrollment}`} className="inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {student?.student ?? t('student.back')}
      </Link>
      <header>
        <h1 className="text-2xl font-bold">{t('student.title')}</h1>
        <p className="mt-1 text-muted">{[student?.student, plan?.program.name, plan?.program.track_label].filter(Boolean).join(' · ')}</p>
      </header>

      {!plan ? (
        <Empty>{t('student.noPlan')}</Empty>
      ) : (
        <>
          {plan.frozen ? <Notice tone="attention">{t('lesson.frozen', { why: plan.frozen_label ?? t(`frozen.${plan.frozen}`) })}</Notice> : null}
          <Card className="space-y-4">
            {plan.position ? (
              <p className="flex flex-wrap items-center gap-2">
                <Icon name="map" size={18} className="text-brand" />
                {t('position')} <strong data-position>{plan.position.title}</strong>
                {plan.finished ? <Badge tone="brand">{t('finishedBadge')}</Badge> : null}
              </p>
            ) : null}
            <PlanProgress plan={plan} />
          </Card>

          <section aria-labelledby="portion">
            <SectionTitle id="portion">{t('portion')}</SectionTitle>
            {plan.portion ? <PortionCards portion={plan.portion} /> : <Empty>{t('finished')}</Empty>}
          </section>

          <section aria-labelledby="pace">
            <SectionTitle id="pace">{t('pace.title')}</SectionTitle>
            <Card>
              {plan.group_id ? (
                <p className="text-sm text-muted">{t('pace.group')}</p>
              ) : (
                <PaceForm key={`${plan.pace.new_amount}-${plan.pace.near_portions}-${plan.pace.far_pages}`} enrollment={enrollment} pace={plan.pace} unit={plan.program.unit_label} />
              )}
            </Card>
          </section>

          <section aria-labelledby="recent">
            <SectionTitle id="recent">{t('recent')}</SectionTitle>
            <RecentResults results={recent} teacher />
          </section>
        </>
      )}
    </div>
  );
}
