import { getTranslations } from 'next-intl/server';
import { ClassicHome, KidsHome, MiddleHome, TeenHome } from '@/components/family/homes';
import { Empty } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { seesMoney } from '@/lib/family/rules';
import type { Balance, FamilyLessons, Homework, Invoices, Journey } from '@/lib/family/types';
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

  if (fam.stage === 'kids') return <KidsHome learner={learner} journey={journey.data} lessons={lessons.data} homework={homework.data} />;
  if (fam.stage === 'middle') return <MiddleHome learner={learner} journey={journey.data} lessons={lessons.data} homework={homework.data} />;
  if (fam.stage === 'teen') return <TeenHome learner={learner} journey={journey.data} lessons={lessons.data} homework={homework.data} />;

  const [balance, invoices] = await Promise.all([
    get<Balance>('/v1/family/balance', { query: who }),
    seesMoney(fam.stage) ? get<Invoices>('/v1/family/invoices') : Promise.resolve(null),
  ]);
  return (
    <ClassicHome
      name={person.name}
      learner={learner}
      lessons={lessons.data}
      notices={lessons.notices}
      balanceLessons={balance.data.reduce((n, b) => n + b.sessions, 0)}
      invoices={invoices}
      homework={homework.data}
      courses={journey.data.courses}
    />
  );
}
