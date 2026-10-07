import { getTranslations } from 'next-intl/server';
import { AssessForm } from '@/components/teach/assess-form';
import { Badge, Card, Empty, Heading, SectionTitle } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { Trial, Trials } from '@/lib/teach/types';

/** Trial lessons: the ones to assess after they took place (no lesson report for a trial), what is coming, and what was assessed. */
export default async function TrialsPage() {
  const [t, nav, trials] = await Promise.all([getTranslations('teach'), getTranslations('nav'), get<Trials>('/v1/teacher/trials')]);
  const none = trials.to_assess.length + trials.upcoming.length + trials.done.length === 0;
  return (
    <div>
      <Heading title={nav('trials')} />
      {none ? <Empty>{t('noTrials')}</Empty> : null}
      {trials.to_assess.length > 0 ? (
        <>
          <SectionTitle trailing={trials.to_assess.length}>{t('toAssess')}</SectionTitle>
          <ul className="stagger space-y-4">
            {trials.to_assess.map((trial) => (
              <li key={trial.id}>
                <Card as="article">
                  <About trial={trial} />
                  <div className="mt-4 border-t border-line pt-4">
                    <AssessForm trial={trial} plans={trials.plans} />
                  </div>
                </Card>
              </li>
            ))}
          </ul>
        </>
      ) : null}
      {trials.upcoming.length > 0 ? (
        <>
          <SectionTitle>{t('upcomingTrials')}</SectionTitle>
          <ul className="space-y-2.5">
            {trials.upcoming.map((trial) => (
              <li key={trial.id}>
                <Card as="article">
                  <About trial={trial} />
                </Card>
              </li>
            ))}
          </ul>
        </>
      ) : null}
      {trials.done.length > 0 ? (
        <>
          <SectionTitle>{t('assessedTrials')}</SectionTitle>
          <ul className="space-y-2.5">
            {trials.done.map((trial) => (
              <li key={trial.id}>
                <Card as="article">
                  <About trial={trial} />
                  <details className="mt-3">
                    <summary className="cursor-pointer text-sm font-semibold text-brand-strong">{t('assess')}</summary>
                    <div className="mt-3">
                      <AssessForm trial={trial} plans={trials.plans} />
                    </div>
                  </details>
                </Card>
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </div>
  );
}

async function About({ trial }: { trial: Trial }) {
  const t = await getTranslations('teach');
  return (
    <div>
      <div className="flex flex-wrap items-center gap-2">
        <p className="font-semibold">{trial.name}</p>
        {trial.age ? <Badge>{trial.age}</Badge> : null}
        {trial.level ? <Badge tone="brand">{trial.level}</Badge> : null}
      </div>
      <p className="text-sm text-muted">
        {trial.subject} · {trial.starts_at.label}
      </p>
      {trial.notes ? (
        <p className="mt-2 text-sm">
          <span className="font-semibold">{t('leadNotes')}: </span>
          {trial.notes}
        </p>
      ) : null}
      {trial.assessment ? <p className="mt-2 whitespace-pre-line text-sm text-muted">{trial.assessment}</p> : null}
    </div>
  );
}
