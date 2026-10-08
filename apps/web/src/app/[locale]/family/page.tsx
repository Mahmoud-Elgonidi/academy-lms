import { getTranslations } from 'next-intl/server';
import type { ReactNode } from 'react';
import { ConsentBanner } from '@/components/family/consent-banner';
import { ClassicHome, KidsHome, MiddleHome, TeenHome } from '@/components/family/homes';
import { RefreshAt } from '@/components/live-refresh';
import { Empty } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { momentsOf } from '@/lib/live';
import { isGuardian, seesMoney } from '@/lib/family/rules';
import type { Balance, Consents, FamilyLessons, Homework, Invoices, Journey } from '@/lib/family/types';
import { me } from '@/lib/viewer';

/** The family's home, in the look of the person signed in. */
export default async function FamilyHome() {
  const [t, fam, person] = await Promise.all([getTranslations('family'), family(), me()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const who = { learner_id: learner.id };

  const [lessons, journey, homework] = await Promise.all([
    get<FamilyLessons>('/v1/family/lessons', { query: who }),
    get<{ data: Journey }>('/v1/learning/journey', { query: who }),
    get<{ data: Homework[] }>('/v1/learning/homework', { query: { student_id: learner.id } }),
  ]);

  // The next lesson's room opens and closes by the clock alone.
  const clock = <RefreshAt moments={momentsOf(lessons.data)} />;
  if (fam.stage === 'kids') return withClock(clock, <KidsHome learner={learner} journey={journey.data} lessons={lessons.data} homework={homework.data} />);
  if (fam.stage === 'middle') return withClock(clock, <MiddleHome learner={learner} journey={journey.data} lessons={lessons.data} homework={homework.data} />);
  if (fam.stage === 'teen') return withClock(clock, <TeenHome learner={learner} journey={journey.data} lessons={lessons.data} homework={homework.data} />);

  const [balance, invoices, consents] = await Promise.all([
    get<Balance>('/v1/family/balance', { query: who }),
    seesMoney(fam.stage) ? get<Invoices>('/v1/family/invoices') : Promise.resolve(null),
    isGuardian(fam.learners) ? get<Consents>('/v1/family/consents') : Promise.resolve(null),
  ]);
  const waiting = consents?.children.filter((c) => c.state === 'waiting').map((c) => c.student.name) ?? [];
  return withClock(
    <>
      {clock}
      {waiting.length > 0 ? <ConsentBanner names={waiting} /> : null}
    </>,
    <ClassicHome
      name={person.name}
      learner={learner}
      lessons={lessons.data}
      notices={lessons.notices}
      balanceLessons={balance.data.reduce((n, b) => n + (b.sessions ?? 0), 0)}
      invoices={invoices}
      homework={homework.data}
      courses={journey.data.courses}
    />,
  );
}

function withClock(clock: ReactNode, home: ReactNode) {
  return (
    <>
      {clock}
      {home}
    </>
  );
}
