import { getTranslations } from 'next-intl/server';
import { ConsentBanner } from '@/components/family/consent-banner';
import { ClassicHome } from '@/components/family/homes';
import { JourneyScreen } from '@/components/journey/screen';
import { RefreshAt } from '@/components/live-refresh';
import { Empty } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { momentsOf } from '@/lib/live';
import { balanceMoments } from '@/lib/family/balance';
import { isGuardian, seesMoney } from '@/lib/family/rules';
import type { Balance, Consents, CourseBalance, FamilyLessons, Homework, Invoices } from '@/lib/family/types';
import type { JourneyMap } from '@/lib/journey/types';
import { hasModule, modulesOf } from '@/lib/modules';
import { me } from '@/lib/viewer';

/**
 * The family's home, in the look of the person signed in: a student's is
 * their journey (one map, the same as /family/journey); a guardian's or an
 * adult's is the classic home with the journey's card. While the academy has
 * its curriculum off there is no journey: everyone gets the plain home (the
 * next lesson, the courses' balances, homework, notices), without its card.
 */
export default async function FamilyHome() {
  const [t, fam, person] = await Promise.all([getTranslations('family'), family(), me()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const curriculum = hasModule(modulesOf(person), 'curriculum');
  if (fam.stage !== 'classic' && curriculum) return <JourneyScreen />;
  const who = { learner_id: learner.id };

  const [lessons, journey, homework, balance, invoices, consents] = await Promise.all([
    get<FamilyLessons>('/v1/family/lessons', { query: who }),
    curriculum ? get<{ data: JourneyMap }>('/v1/learning/journey/map', { query: who }) : Promise.resolve(null),
    get<{ data: Homework[] }>('/v1/learning/homework', { query: { student_id: learner.id } }),
    get<Balance>('/v1/family/balance', { query: who }),
    seesMoney(fam.stage) ? get<Invoices>('/v1/family/invoices') : Promise.resolve(null),
    isGuardian(fam.learners) ? get<Consents>('/v1/family/consents') : Promise.resolve(null),
  ]);
  const waiting = consents?.children.filter((c) => c.state === 'waiting').map((c) => c.student.name) ?? [];
  // Each course's own balance (J5a): the lessons left, the plan, expiry, and the invoice waiting or coming.
  const courses = await Promise.all(
    balance.data.map(async (b) => ({
      subject: b.subject,
      balance: (await get<{ data: CourseBalance }>('/v1/family/courses/{enrollment}/balance', { params: { enrollment: b.enrollment_id } })).data,
    })),
  );
  return (
    <>
      {/* The next lesson's room opens and closes by the clock alone; an invoice comes due by it too. */}
      <RefreshAt moments={[...momentsOf(lessons.data), ...courses.flatMap((c) => balanceMoments(c.balance))]} />
      {waiting.length > 0 ? <ConsentBanner names={waiting} /> : null}
      <ClassicHome
        name={person.name}
        learner={learner}
        lessons={lessons.data}
        notices={lessons.notices}
        courses={courses}
        guardian={isGuardian(fam.learners)}
        invoices={invoices}
        homework={homework.data}
        journey={journey?.data ?? null}
      />
    </>
  );
}
