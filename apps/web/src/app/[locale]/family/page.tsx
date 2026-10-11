import { getTranslations } from 'next-intl/server';
import { ConsentBanner } from '@/components/family/consent-banner';
import { ClassicHome } from '@/components/family/homes';
import { JourneyScreen } from '@/components/journey/screen';
import { RefreshAt } from '@/components/live-refresh';
import { Empty } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { momentsOf } from '@/lib/live';
import { isGuardian, seesMoney } from '@/lib/family/rules';
import type { Balance, Consents, FamilyLessons, Homework, Invoices } from '@/lib/family/types';
import type { JourneyMap } from '@/lib/journey/types';
import { me } from '@/lib/viewer';

/**
 * The family's home, in the look of the person signed in: a student's is
 * their journey (one map, the same as /family/journey); a guardian's or an
 * adult's is the classic home with the journey's card.
 */
export default async function FamilyHome() {
  const [t, fam, person] = await Promise.all([getTranslations('family'), family(), me()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  if (fam.stage !== 'classic') return <JourneyScreen />;
  const who = { learner_id: learner.id };

  const [lessons, journey, homework, balance, invoices, consents] = await Promise.all([
    get<FamilyLessons>('/v1/family/lessons', { query: who }),
    get<{ data: JourneyMap }>('/v1/learning/journey/map', { query: who }),
    get<{ data: Homework[] }>('/v1/learning/homework', { query: { student_id: learner.id } }),
    get<Balance>('/v1/family/balance', { query: who }),
    seesMoney(fam.stage) ? get<Invoices>('/v1/family/invoices') : Promise.resolve(null),
    isGuardian(fam.learners) ? get<Consents>('/v1/family/consents') : Promise.resolve(null),
  ]);
  const waiting = consents?.children.filter((c) => c.state === 'waiting').map((c) => c.student.name) ?? [];
  return (
    <>
      {/* The next lesson's room opens and closes by the clock alone. */}
      <RefreshAt moments={momentsOf(lessons.data)} />
      {waiting.length > 0 ? <ConsentBanner names={waiting} /> : null}
      <ClassicHome
        name={person.name}
        learner={learner}
        lessons={lessons.data}
        notices={lessons.notices}
        balanceLessons={balance.data.reduce((n, b) => n + (b.sessions ?? 0), 0)}
        invoices={invoices}
        homework={homework.data}
        journey={journey.data}
      />
    </>
  );
}
