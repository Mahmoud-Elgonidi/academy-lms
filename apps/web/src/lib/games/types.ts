import type { LocalTime } from '../time';
import type { Look } from './art';

/**
 * What the games endpoints answer (GET /v1/games/world, the rounds, the
 * album), as the pages read them. The world, its islands and creatures are
 * the platform's; the quests, stars and creatures found are the learner's.
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

export type GameCard = {
  id: string;
  key: string;
  island: string;
  title: string;
  summary: string;
  how_to_play: string;
  /** The kinds of practice it plays (choice, order, pair). */
  kinds: string[];
  /** Whether the learner has enough items of those kinds. */
  playable: boolean;
  plays?: number;
  /** The best stars on it. */
  best?: number;
  /** The quest it plays now, if any. */
  quest?: { key: string; reason: string; title: string; items: number; done: boolean } | null;
};

export type Quest = {
  key: string;
  reason: 'homework' | 'last_lesson' | 'review' | 'current' | 'free' | string;
  reason_label: string;
  title: string;
  homework_id: string | null;
  due_at: LocalTime | null;
  done: boolean;
  items: number;
  game: GameCard;
  rounds: number;
  best_seconds: number | null;
  last_seconds: number | null;
};

export type Island = {
  key: string;
  name: string;
  story: string;
  emoji: string;
  colors: Record<string, string>;
  /** Its scale for the music (major, pentatonic, dorian, lydian, phrygian). */
  music: string;
  open: boolean;
  fresh: boolean;
  /** Its games play this week's homework. */
  homework: boolean;
  creatures: Creature[];
  games: GameCard[];
  /** The next creature to hatch there (its look for the egg), or null when all have. */
  next: { key: string; look: Look; rarity: string } | null;
};

export type GameLimits = { enabled: boolean; daily_minutes: number | null; night_lock: boolean; academy_minutes: number | null; choices: number[] };

export type Glance = {
  rounds: number;
  practised: { title: string; rounds: number; games: number } | null;
  hardest: { text: string; wrong: number } | null;
  best: { correct: number; total: number; game: string } | null;
  hatched: { key: string; name: string; look: Look; island: string; source: string; found: number } | null;
};

export type World = {
  learner_id: string;
  stage: 'kids' | 'middle' | 'teen' | 'classic';
  allowance: Allowance;
  stars: number;
  egg: { have: number; need: number };
  creatures_found: number;
  creatures_total: number;
  buddy: Creature | null;
  quests: Quest[];
  streak: number;
  islands: Island[];
  recent: { at: LocalTime | null; game: string; stars: number; correct: number; total: number; focus: string }[];
  /** The last ten rounds, oldest first. */
  history: { at: LocalTime | null; share: number; stars: number }[];
  /** A guardian's limits for this child (null for the student). */
  limits: GameLimits | null;
  glance: Glance | null;
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
