import { getTranslations } from 'next-intl/server';
import { HifzCard } from '@/components/hifz/card';
import { RefreshAt } from '@/components/live-refresh';
import { CourseBalanceCard } from '@/components/family/course-balance';
import { Empty, Notice } from '@/components/ui';
import { AreaOff } from '@/components/area-off';
import { read } from '@/lib/api/server';
import { areaGet } from '@/lib/modules-server';
import { balanceMoments } from '@/lib/family/balance';
import type { CourseBalance } from '@/lib/family/types';
import { family } from '@/lib/family/context';
import { firstName } from '@/lib/games/world';
import { homeworkTarget, journeyMoments, lookOf } from '@/lib/journey/journey';
import type { JourneyMap } from '@/lib/journey/types';
import { CalmJourney } from './calm';
import { GuardianJourney } from './guardian';
import { KidsJourney } from './kids';
import { MiddleJourney } from './middle';

/**
 * The journey (docs/architecture/02, section 18) of the learner on screen,
 * from GET learning/journey/map, in the look of the person signed in: a
 * child's islands, middle school's levels, the calm list (teens, adults,
 * everyone in the calm style), or a guardian's look at their child. It
 * refreshes on the person's signals (lessons, homework, practice) and at the
 * moments the clock alone changes it (a lesson's room opening, the week's
 * chest closing). ?homework= starts that homework's practice; ?island= opens
 * an island (back from the album). With pages on the learner's hifz map, a
 * card says what is left of today's review and opens it. A student's own look
 * has each course's lessons left beside it (J5a: the kids' in a few words).
 * While the academy has its curriculum off there is no journey: a calm word.
 */
export async function JourneyScreen({ homework, island }: { homework?: string; island?: string }) {
  const [t, fam] = await Promise.all([getTranslations('journey'), family()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const found = await areaGet<{ data: JourneyMap }>('/v1/learning/journey/map', { query: { learner_id: learner.id } });
  if (found.off) return <AreaOff title={learner.is_self ? t('mine') : t('journeyOf', { name: firstName(learner.name) })} by={found.by} icon="map" home="/family" />;
  const map = found.data.data;
  // The name keeps its own direction inside the sentence (an Arabic name in English words, and back).
  const name = `\u2068${firstName(learner.name)}\u2069`;
  const autoplay = homeworkTarget(map, homework);
  const look = lookOf(map, fam.stage);
  const clock = <RefreshAt moments={journeyMoments(map)} />;
  // A student's own balance (never a guardian's look at their child: the classic home has it).
  const balances = fam.stage === 'classic' ? [] : await studentBalances(map);
  const hifz = (
    <>
      {map.hifz ? <HifzCard hifz={map.hifz} kids={look === 'kids'} /> : null}
      {balances.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2" data-student-balance>
          <RefreshAt moments={balances.flatMap((b) => balanceMoments(b.balance))} />
          {balances.map((b) => (
            <CourseBalanceCard key={b.balance.enrollment_id} balance={b.balance} subject={b.subject} />
          ))}
        </div>
      ) : null}
    </>
  );
  return (
    <>
      {clock}
      {homework && !autoplay ? (
        <div className="mb-4">
          <Notice tone="attention">{t('practiceUnavailable')}</Notice>
        </div>
      ) : null}
      {look === 'kids' ? (
        <KidsJourney key={learner.id} map={map} learnerName={name} autoplay={autoplay} openIsland={island ?? null} hifz={hifz} />
      ) : look === 'middle' ? (
        <MiddleJourney key={learner.id} map={map} learnerName={name} autoplay={autoplay} hifz={hifz} />
      ) : look === 'guardian' ? (
        <GuardianJourney key={learner.id} map={map} learnerName={name} autoplay={autoplay} hifz={hifz} />
      ) : (
        <CalmJourney key={learner.id} map={map} title={t('mine')} autoplay={autoplay} look={fam.stage} hifz={hifz} />
      )}
    </>
  );
}

/** Each course on the map that is not over, with its balance as the student's look reads it (a course with none is left out). */
async function studentBalances(map: JourneyMap): Promise<{ subject: string | null; balance: CourseBalance }[]> {
  const courses = map.courses.filter((c) => c.status !== 'ended');
  const found = await Promise.all(
    courses.map(async (c) => {
      const got = await read<{ data: CourseBalance }>('/v1/family/courses/{enrollment}/balance', { params: { enrollment: c.enrollment_id } });
      return got.ok && got.data.data.lessons_left !== null ? { subject: c.subject, balance: got.data.data } : null;
    }),
  );
  return found.filter((b): b is { subject: string | null; balance: CourseBalance } => b !== null);
}
