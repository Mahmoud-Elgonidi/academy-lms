import { notFound } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { QuranOff } from '@/components/quran/off';
import { PlanProgress, PortionCards, RecentResults } from '@/components/quran/parts';
import { QuranLessonForm } from '@/components/quran/recorder';
import { Badge, Card, Empty, Notice, SectionTitle, buttonLook } from '@/components/ui';
import { partsOf } from '@/lib/quran/quran';
import { quranRead } from '@/lib/quran/server';
import type { LessonPortion, PartKey } from '@/lib/quran/types';

/**
 * A Quran lesson's panel for its teacher (docs/architecture/02, section 26):
 * today's portion from the student's (or group's) plan as three cards (new,
 * near review, far review), the way to the lesson's shared mushaf, and the
 * record of what each student recited: mistakes by kind with quick counters
 * (or the words marked in the mushaf), a grade for each part, sent once with
 * an idempotency key. A student already recorded shows their result. A
 * course without a plan has no portion; the plan paused or over waits.
 */
export default async function QuranLesson({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const [t, teach] = await Promise.all([getTranslations('quran'), getTranslations('teach')]);
  const found = await quranRead<LessonPortion>('/v1/quran/lessons/{session}/portion', { params: { session: id } });
  if (found.state === 'off') return <QuranOff title={t('lesson.title')} />;
  if (found.state === 'missing') notFound();
  const lesson = found.data;
  const plan = lesson.plan;
  const recorded = new Set(lesson.results.map((r) => r.student_id));
  const waiting = lesson.students.filter((s) => !recorded.has(s.id));
  const parts = partsOf(plan?.portion);
  const partLabels = Object.fromEntries((['new', 'near', 'far'] as const).map((k) => [k, t(`parts.${k}`)])) as Record<PartKey, string>;
  const names = new Map(lesson.students.map((s) => [s.id, s.name]));

  return (
    <div className="space-y-5">
      <Link href={`/teach/lessons/${id}`} className="inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
        <Icon name="chevron" size={16} className="ltr:-scale-x-100" />
        {teach('backToLesson')}
      </Link>
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('lesson.title')}</h1>
          <p className="mt-1 text-muted">
            {[plan?.program.name, plan?.program.track_label, lesson.students.map((s) => s.name).join(t('listJoin'))].filter(Boolean).join(' · ')}
          </p>
        </div>
        <Link href={`/teach/lessons/${id}`} className={buttonLook('quiet')} data-open-mushaf>
          <Icon name="book" size={18} />
          {t('lesson.openMushaf')}
        </Link>
      </header>

      {!plan ? (
        <Empty>{t('lesson.noPlan')}</Empty>
      ) : (
        <>
          {plan.frozen ? <Notice tone="attention">{t('lesson.frozen', { why: plan.frozen_label ?? t(`frozen.${plan.frozen}`) })}</Notice> : null}
          <Card className="space-y-4">
            {plan.position ? (
              <p className="flex flex-wrap items-center gap-2">
                <Icon name="map" size={18} className="text-brand" />
                {t('position')} <strong data-position>{plan.position.title}</strong>
                {lesson.group ? <Badge tone="info">{t('lesson.groupPlan')}</Badge> : null}
              </p>
            ) : null}
            <PlanProgress plan={plan} />
          </Card>

          <section aria-labelledby="portion">
            <SectionTitle id="portion">{t('lesson.portion')}</SectionTitle>
            {plan.portion ? <PortionCards portion={plan.portion} /> : <Empty>{t('finished')}</Empty>}
          </section>

          {lesson.results.length > 0 ? (
            <section aria-labelledby="recorded">
              <SectionTitle id="recorded">{t('lesson.recorded')}</SectionTitle>
              {lesson.results.map((r) => (
                <div key={r.id} className="mb-3">
                  {lesson.group ? <p className="mb-1 text-sm font-semibold">{names.get(r.student_id)}</p> : null}
                  <RecentResults results={[r]} teacher />
                </div>
              ))}
            </section>
          ) : null}

          {waiting.length > 0 && parts.length > 0 ? (
            <section aria-labelledby="record">
              <SectionTitle id="record">{t('lesson.record')}</SectionTitle>
              <p className="-mt-1 mb-3 text-sm text-muted">{t('lesson.recordLead')}</p>
              <QuranLessonForm key={waiting.map((s) => s.id).join()} session={id} students={waiting} parts={parts} partLabels={partLabels} types={lesson.mistake_types} grades={lesson.grades} />
            </section>
          ) : waiting.length === 0 && lesson.results.length > 0 ? (
            <Notice tone="brand">{t('lesson.allRecorded')}</Notice>
          ) : null}
        </>
      )}
    </div>
  );
}
