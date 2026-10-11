import { getTranslations } from 'next-intl/server';
import { RefreshAt } from '@/components/live-refresh';
import { Empty, Notice } from '@/components/ui';
import { get } from '@/lib/api/server';
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
 * an island (back from the album).
 */
export async function JourneyScreen({ homework, island }: { homework?: string; island?: string }) {
  const [t, fam] = await Promise.all([getTranslations('journey'), family()]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const { data: map } = await get<{ data: JourneyMap }>('/v1/learning/journey/map', { query: { learner_id: learner.id } });
  // The name keeps its own direction inside the sentence (an Arabic name in English words, and back).
  const name = `\u2068${firstName(learner.name)}\u2069`;
  const autoplay = homeworkTarget(map, homework);
  const look = lookOf(map, fam.stage);
  const clock = <RefreshAt moments={journeyMoments(map)} />;
  return (
    <>
      {clock}
      {homework && !autoplay ? (
        <div className="mb-4">
          <Notice tone="attention">{t('practiceUnavailable')}</Notice>
        </div>
      ) : null}
      {look === 'kids' ? (
        <KidsJourney key={learner.id} map={map} learnerName={name} autoplay={autoplay} openIsland={island ?? null} />
      ) : look === 'middle' ? (
        <MiddleJourney key={learner.id} map={map} learnerName={name} autoplay={autoplay} />
      ) : look === 'guardian' ? (
        <GuardianJourney key={learner.id} map={map} learnerName={name} autoplay={autoplay} />
      ) : (
        <CalmJourney key={learner.id} map={map} title={t('mine')} autoplay={autoplay} look={fam.stage} />
      )}
    </>
  );
}
