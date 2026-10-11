import { getTranslations } from 'next-intl/server';
import { GuardianGames } from '@/components/games/guardian';
import { Hub } from '@/components/games/hub';
import { KidsWorld } from '@/components/games/kids-world';
import { Empty } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { World } from '@/lib/games/types';
import { firstName } from '@/lib/games/world';

/**
 * The games (docs/architecture/02, section 18), in the look of the person
 * signed in, from the same world as the app and the /my panel: a child's
 * islands, the race of middle school, the review of teens and adults, and a
 * guardian's limits and glance at their child. Opened with ?homework= it
 * starts that homework's practice; with ?island= that island is open.
 */
export default async function GamesPage({ searchParams }: { searchParams: Promise<{ homework?: string; island?: string }> }) {
  const [t, fam, asked] = await Promise.all([getTranslations('games'), family(), searchParams]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const { data: world } = await get<{ data: World }>('/v1/games/world', { query: { learner_id: learner.id } });
  const quest = asked.homework ? world.quests.find((q) => q.homework_id === asked.homework && q.game.playable) : undefined;
  const autoplay = quest ? { game: quest.game.id, quest: quest.key } : null;

  if (!learner.is_self) {
    const kids = fam.learners.filter((l) => !l.is_self).map((l) => ({ id: l.id, name: firstName(l.name) }));
    return <GuardianGames key={learner.id} world={world} kids={kids} current={learner.id} />;
  }
  if (fam.stage === 'kids') return <KidsWorld world={world} learnerName={firstName(learner.name)} openIsland={asked.island ?? null} autoplay={autoplay} />;
  return <Hub world={world} look={fam.stage} autoplay={autoplay} />;
}
