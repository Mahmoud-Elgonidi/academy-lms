import type { Look } from './art';
import type { GameLimits } from './types';

/**
 * The games' small reckonings the journey's screens and their tests share
 * (as the panel and the app draw them): a game's tile, who lives in an opened
 * island's scene, the race track, and a guardian's minutes.
 */

export type TileArt = 'gates' | 'balloons' | 'cards' | 'bridge' | 'lock';

/** A game's tile picture: its own for the first games, else by what it plays; a lock when it cannot play now. */
export function tileArt(game: { key: string; kinds: readonly string[]; playable: boolean }): TileArt {
  if (!game.playable) return 'lock';
  const own: Record<string, TileArt> = { 'bird-gates': 'gates', 'bird-balloons': 'balloons', 'bird-twins': 'cards', 'turtle-bridge': 'bridge' };
  return own[game.key] ?? (game.kinds.includes('pair') ? 'cards' : game.kinds.includes('choice') ? 'balloons' : 'gates');
}

/** A tile's card and picture colours. */
export const TILE_PAINT: Record<TileArt, [string, string]> = {
  gates: ['#D0EBFF', '#74C0FC'],
  balloons: ['#FFE8CC', '#FFA94D'],
  cards: ['#C3FAE8', '#38D9A9'],
  bridge: ['#C3FAE8', '#63E6BE'],
  lock: ['#F1F3F5', '#DEE2E6'],
};

/** Who lives in an opened island's scene: up to three hatched, else its first creature waiting there. */
export function residentsOf(island: { creatures: readonly { found: boolean; look: Look }[] }): Look[] {
  const found = island.creatures.filter((c) => c.found).slice(0, 3);
  return found.length > 0 ? found.map((c) => c.look) : [island.creatures[0]?.look ?? {}];
}

/** The race against yourself: the track's length in seconds (a little past the slower of the two, at least 30). */
export function raceLength(best: number | null, last: number | null): number {
  return Math.max(30, Math.round(Math.max(best ?? 0, last ?? 0) * 1.3));
}

/** Where a time stands on the track, in percent from the start (no time yet: near the finish). */
export function raceAt(seconds: number | null, length: number): number {
  return seconds === null ? 80 : 6 + 74 * Math.min(1, seconds / length);
}

/** The minutes a guardian may choose: never more than the academy's own. */
export function minuteChoices(limits: Pick<GameLimits, 'choices' | 'academy_minutes'>): number[] {
  return limits.choices.filter((m) => limits.academy_minutes === null || m <= limits.academy_minutes);
}

/** The guardian's ring: the share of today's minutes used (0 without a limit). */
export function usedShare(used: number, limit: number | null): number {
  return limit ? Math.min(1, used / Math.max(1, limit)) : 0;
}

/** The first word of a name ("Yousef's journey"). */
export function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name;
}
