import type { LocalTime } from '../time';
import type { Look } from './art';

/**
 * What the games endpoints answer (the rounds, hatching, the album, a
 * guardian's limits), as the pages read them; the journey's games layer is
 * in lib/journey/types. The world, its islands and creatures are the
 * platform's; the stars and creatures found are the learner's.
 */

/** Whether the learner may start a round now, and why not (off, paused, night, limit). */
export type Allowance = {
  may: boolean;
  used: number;
  limit: number | null;
  left: number | null;
  reason: 'off' | 'paused' | 'night' | 'limit' | null;
  reason_label: string | null;
  night_lock: boolean;
};

export type Creature = {
  key: string;
  name: string;
  emoji: string;
  rarity: 'common' | 'rare' | 'legendary' | string;
  look: Look;
  story: string;
  found: boolean;
  found_at: LocalTime | null;
  /** How it hatched: stars from rounds, or a lesson mastered. */
  source: 'stars' | 'mastery' | null;
  /** The name the learner gave it. */
  nickname: string | null;
  buddy: boolean;
};

/** A creature in the album: where it lives, and names to choose from once hatched. */
export type AlbumCreature = Creature & { island: string; island_name: string; names: string[] };

export type GameLimits = { enabled: boolean; daily_minutes: number | null; night_lock: boolean; academy_minutes: number | null; choices: number[] };

export type Glance = {
  rounds: number;
  practised: { title: string; rounds: number; games: number } | null;
  hardest: { text: string; wrong: number } | null;
  best: { correct: number; total: number; game: string } | null;
  hatched: { key: string; name: string; look: Look; island: string; source: string; found: number } | null;
};

/** POST /v1/games/plays: the round to play. */
export type Round = {
  play_id: string;
  page_url: string;
  game: { id: string; key: string; title: string; how_to_play: string; island: string };
  focus: { reason: string; title: string } | null;
  round: { items: ({ id: string | number } & Record<string, unknown>)[]; stage: string; locale: string; dir: string; title: string };
  max_seconds: number;
};

/** POST /v1/games/plays/{play}/finish: how it went. */
export type Finished = {
  stars: number;
  correct: number;
  total: number;
  seconds: number | null;
  creature: (Omit<Creature, 'found' | 'found_at' | 'source' | 'nickname' | 'buddy'> & { island: string; names: string[]; found: number; total: number }) | null;
  egg: { have: number; need: number };
  homework: { title: string; handed_in: boolean } | null;
};

/** A try the game reported. */
export type Answer = { item: string; correct: boolean };

/**
 * What a round plays: a game (id or key) on a station of the journey (one
 * lesson the learner may practise now), or for a homework's practice; and
 * the race's best time before it, to say whether it was beaten.
 */
export type PlayTarget = { game: string; station?: string | null; homework?: string | null; bestBefore?: number | null };
