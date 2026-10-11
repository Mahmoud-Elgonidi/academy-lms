import type { GameCard, GameLimits, Island, World } from './types';

/**
 * The islands world's layout and reckoning (the approved design, as the
 * panel's games::filament.family.kids and the app draw it), as plain logic
 * the pages and their tests share.
 */

/** Where each island sits on the 390-wide sea: left, top, width, height, its centre (the zoom), and its bob's delay. */
export type Spot = { x: number; y: number; w: number; h: number; cx: number; cy: number; delay: string };

const SPOTS: Record<string, Spot> = {
  volcano: { x: 35, y: 40, w: 170, h: 150, cx: 120, cy: 150, delay: 'gw-d2' },
  penguins: { x: 194, y: 120, w: 180, h: 140, cx: 284, cy: 215, delay: '' },
  monkeys: { x: 13, y: 210, w: 190, h: 170, cx: 108, cy: 330, delay: 'gw-d3' },
  turtles: { x: 187, y: 390, w: 190, h: 130, cx: 282, cy: 470, delay: 'gw-d2' },
  birds: { x: 18, y: 470, w: 200, h: 190, cx: 118, cy: 610, delay: '' },
};

/** The map's width (the design's phone). */
export const MAP_WIDTH = 390;

/** The world's hidden island, as the design keeps it. */
export const SECRET_ISLAND = 'volcano';

/** Each island's spot; an island the studio adds later goes below the others, side to side. */
export function spotsOf(islands: readonly Pick<Island, 'key'>[]): Record<string, Spot> {
  const spots: Record<string, Spot> = { ...SPOTS };
  let extra = 0;
  for (const island of islands) {
    if (spots[island.key]) continue;
    const x = extra % 2 === 0 ? 190 : 18;
    const y = 700 + 160 * extra;
    spots[island.key] = { x, y, w: 180, h: 140, cx: x + 90, cy: y + 95, delay: extra % 2 ? 'gw-d2' : 'gw-d3' };
    extra++;
  }
  return spots;
}

/** How tall the sea is: the lowest island and a margin, never less than the design's. */
export function mapHeight(spots: Record<string, Spot>): number {
  return Math.max(760, ...Object.values(spots).map((s) => s.y + s.h + 40));
}

/** The island of the first quest (where the learner is now), else the first open one. */
export function currentIsland(world: Pick<World, 'quests' | 'islands'>): string | null {
  const first = world.quests[0]?.game.island;
  return world.islands.find((i) => i.key === first)?.key ?? world.islands.find((i) => i.open)?.key ?? null;
}

export type TileArt = 'gates' | 'balloons' | 'cards' | 'bridge' | 'lock';

/** A game's tile picture: its own for the first games, else by what it plays. */
export function tileArt(game: Pick<GameCard, 'key' | 'kinds' | 'playable'>): TileArt {
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

/** The badge on a game's card: homework first, then "soon" for one without practice, "new" before the first round, else its best stars. */
export function badgeOf(game: Pick<GameCard, 'playable' | 'plays' | 'quest'>): 'homework' | 'soon' | 'new' | 'stars' {
  if (game.quest?.reason === 'homework') return 'homework';
  if (!game.playable) return 'soon';
  return (game.plays ?? 0) === 0 ? 'new' : 'stars';
}

/** Who lives in an opened island's scene: up to three hatched, else its first creature waiting there. */
export function residentsOf(island: Pick<Island, 'creatures'>) {
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

/** The week's constellation (the teen review): fourteen stars, joined in order. */
export const CONSTELLATION: readonly [number, number][] = [
  [20, 120], [58, 88], [98, 112], [136, 66], [176, 92], [208, 44], [244, 76],
  [284, 30], [314, 92], [276, 130], [236, 146], [196, 126], [156, 146], [116, 140],
];

/** How many of the constellation's stars the last seven days of rounds light. */
export function litStars(history: World['history'], now: number): number {
  const week = now - 7 * 86_400_000;
  const stars = history.filter((r) => r.at && Date.parse(r.at.utc) > week).reduce((sum, r) => sum + r.stars, 0);
  return Math.min(CONSTELLATION.length, stars);
}

/** The minutes a guardian may choose: never more than the academy's own. */
export function minuteChoices(limits: Pick<GameLimits, 'choices' | 'academy_minutes'>): number[] {
  return limits.choices.filter((m) => limits.academy_minutes === null || m <= limits.academy_minutes);
}

/** The guardian's ring: the share of today's minutes used (0 without a limit). */
export function usedShare(used: number, limit: number | null): number {
  return limit ? Math.min(1, used / Math.max(1, limit)) : 0;
}

/** The first word of a name ("Yousef's world"). */
export function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name;
}
