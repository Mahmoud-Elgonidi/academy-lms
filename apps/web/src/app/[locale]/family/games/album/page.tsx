import { getTranslations } from 'next-intl/server';
import { Album } from '@/components/games/album';
import { Empty } from '@/components/ui';
import { AreaOff } from '@/components/area-off';
import { get } from '@/lib/api/server';
import { areaGet } from '@/lib/modules-server';
import { family } from '@/lib/family/context';
import type { AlbumCreature } from '@/lib/games/types';
import { firstName } from '@/lib/games/world';
import type { JourneyMap } from '@/lib/journey/types';

/**
 * The learner's album of creatures: the whole world's, hatched ones in
 * colour. The learner names theirs and picks a buddy; a guardian looks.
 * ?island= shows one island's, ?new= opens one just named. While the
 * academy has its games (or the curriculum they live on) off, a calm word.
 */
export default async function AlbumPage({ searchParams }: { searchParams: Promise<{ island?: string; new?: string }> }) {
  const [t, fam, asked] = await Promise.all([getTranslations('games'), family(), searchParams]);
  const learner = fam.current;
  if (!learner) return <Empty>{t('noLearner')}</Empty>;
  const found = await areaGet<{ data: AlbumCreature[] }>('/v1/games/creatures', { query: { learner_id: learner.id } });
  if (found.off) return <AreaOff title={t('album.title')} by={found.by} icon="spark" home="/family" />;
  const journey = await areaGet<{ data: JourneyMap }>('/v1/learning/journey/map', { query: { learner_id: learner.id } });
  if (journey.off) return <AreaOff title={t('album.title')} by={journey.by} icon="spark" home="/family" />;
  const album = found.data;
  const island = asked.island && album.data.some((c) => c.island === asked.island) ? asked.island : null;
  return (
    <Album
      key={learner.id}
      creatures={album.data}
      need={journey.data.data.games?.egg.need ?? journey.data.data.stars.per_egg}
      guardian={!learner.is_self}
      learnerName={firstName(learner.name)}
      island={island}
      open={asked.new ?? null}
    />
  );
}
