import type { CourseGroupRaw } from '../lesson-format';
import type { Look } from '../games/art';
import type { Allowance, Creature, GameLimits, Glance } from '../games/types';
import type { JourneyHifz } from '../hifz/types';
import type { LocalTime } from '../time';

/**
 * What GET /v1/learning/journey/map answers (docs/architecture/02, section
 * 18), as the pages read it: the academy's own curriculum as islands (its
 * levels) and stations (its lessons), the missions to do now, the stars, the
 * week's chest and, when the academy has games, the games on each lesson the
 * learner may practise. The same answer the app and the /my panel draw.
 */

/** playful: each stage's look; calm: levels and missions for everyone; plain: no stars, chest, egg or games. */
export type JourneyStyle = 'playful' | 'calm' | 'plain';

export type Rank = { key: string; stars: number; next: number | null; next_key: string | null; progress: number; label: string; next_label: string | null };

/** A game that can play a station's own items, best first (the teacher's choice for homework first). */
export type StationGame = {
  id: string;
  key: string;
  island: string;
  title: string;
  summary: string;
  how_to_play: string;
  kinds: string[];
  /** The teacher chose it for homework on this lesson. */
  chosen: boolean;
  /** Items in a round on this lesson. */
  items: number;
};

/** The race against yourself on a station. */
export type Race = { rounds: number; best_seconds: number | null; last_seconds: number | null; best_stars: number };

export type StationState = 'done' | 'review' | 'here' | 'todo';

export type Station = {
  id: string;
  title: string;
  /** The unit it sits in, a sign on the road (null: right on the level). */
  unit: string | null;
  /** done, review (the teacher asked for more), here or todo. */
  state: StationState;
  /** Mastered and due to be practised again. */
  due_review: boolean;
  /** The learner may practise it now (a lesson still ahead is not). */
  playable: boolean;
  practice: { rounds: number; answered: number; correct: number; percent: number } | null;
  /** The keys of the open missions on it. */
  missions: string[];
  games: StationGame[];
  race: Race | null;
};

/** The world island a level is drawn as (null without the games' world). */
export type IslandArt = { key: string; name: string; emoji: string; colors: Record<string, string>; music: string };

export type JourneyCreature = Creature & { island: string; island_name: string };

export type JourneyIsland = {
  id: string;
  index: number;
  title: string;
  percent: number;
  /** done, here or ahead. */
  state: 'done' | 'here' | 'ahead';
  lessons: number;
  done: number;
  art: IslandArt | null;
  creatures: JourneyCreature[];
  next_creature: { key: string; look: Look; rarity: string } | null;
  stations: Station[];
};

export type JourneyCourse = {
  enrollment_id: string;
  subject: string | null;
  teacher: string | null;
  /** private (one student a lesson) or group. */
  format: 'private' | 'group';
  /** A group course: its group, seats and the learner's own seat (by when they joined); null for a private course. */
  group: CourseGroupRaw | null;
  program: string | null;
  /** false: the course follows no published curriculum yet (its missions only). */
  has_curriculum: boolean;
  percent: number | null;
  finished: boolean;
  lessons: { mastered: number; total: number } | null;
  eta_weeks: number | null;
  finish_on: LocalTime | null;
  current: { id: string; title: string; path: string[] } | null;
  /** pending_payment, active or paused; with the pause's days, or the day it ends from (the academy's calendar). */
  status?: string;
  paused_from?: LocalTime | null;
  paused_until?: LocalTime | null;
  ends_on?: LocalTime | null;
  islands: JourneyIsland[];
};

type MissionBase = {
  key: string;
  kind_label: string;
  /** open, late (homework past its due time) or done. */
  state: 'open' | 'late' | 'done';
  enrollment_id: string | null;
  island_id: string | null;
  station_id: string | null;
  title: string | null;
};

export type LessonMission = MissionBase & {
  kind: 'lesson';
  lesson_id: string;
  starts_at: LocalTime;
  ends_at: LocalTime | null;
  /** Whether the classroom is open now, and when it opens. */
  join: { open: boolean; opens_at: LocalTime | null };
  /** The stars it can bring: three on time, one more for staying to the end. */
  stars: number;
};

export type HomeworkMission = MissionBase & {
  kind: 'homework';
  homework_id: string;
  due_at: LocalTime | null;
  /** any, text, audio, file or practice (handed in by playing). */
  accepts: string;
  /** It asks for practice in the games on its lessons. */
  practice: boolean;
  /** The game the teacher chose (its key), if any. */
  game: string | null;
  result: { game: string; stars: number; total: number; correct: number; played_at: string } | null;
  /** Stars it brought (done), else the ones it can still bring. */
  stars: number;
};

export type ReviewMission = MissionBase & { kind: 'review' };

export type Mission = LessonMission | HomeworkMission | ReviewMission;

export type Chest = {
  /** The week's Saturday. */
  period: string;
  target: number;
  progress: number;
  /** active, missed, done (ready to open, by the student) or opened. */
  state: 'active' | 'missed' | 'done' | 'opened';
  stars: number;
  /** The sticker it brings for the kids' album (the one it brought, once opened). */
  sticker: { key: string; name: string } | null;
  ends_at: LocalTime | null;
  can_open: boolean;
};

export type JourneyGames = {
  allowance: Allowance & { academy?: boolean; child?: boolean };
  /** Stars toward the next egg; ready: an egg can hatch now. */
  egg: { have: number; need: number; ready: boolean };
  buddy: JourneyCreature | null;
  creatures_found: number;
  creatures_total: number;
  recent: { at: LocalTime | null; game: string; stars: number; correct: number; total: number }[];
  /** A guardian's limits for this child (null for the student). */
  limits: GameLimits | null;
  /** A guardian's look at the child's week (null for the student). */
  glance: Glance | null;
};

export type LastLesson = {
  id: string;
  starts_at: LocalTime | null;
  stars: number;
  late_min: number | null;
  on_time: boolean | null;
  stayed: boolean | null;
  labels: string[];
};

export type JourneyMap = {
  learner_id: string;
  stage: string;
  style: JourneyStyle;
  /** self: the student; guardian: a guardian looking at their child. */
  viewer: 'self' | 'guardian';
  stars: { total: number; rank: Rank; from: Record<string, number>; per_egg: number };
  /** Lessons attended in a row. */
  streak: number;
  last_lesson: LastLesson | null;
  courses: JourneyCourse[];
  missions: Mission[];
  chest: Chest | null;
  games: JourneyGames | null;
  /** Today's hifz review (pages left, done today, a day's pages); null until the learner has pages on their hifz map. */
  hifz?: JourneyHifz | null;
  /** The guardians' cheers the student has not seen yet. */
  cheers: { id: string; kind: string; at: LocalTime | null }[];
  /** For a guardian: cheers they may still send today. */
  cheers_left: number;
};

/** POST /v1/games/hatch and a round that hatched one: the creature, with the names the child chooses from. */
export type HatchedCreature = {
  key: string;
  name: string;
  emoji: string;
  rarity: string;
  island: string;
  look: Look;
  story: string;
  names: string[];
  found: number;
  total: number;
};

/** POST /v1/learning/journey/chest. */
export type OpenedChest = { stars: number; sticker: { key: string; name: string } | null; total_before: number; total: number; ranked_up: boolean; rank: Rank & { label: string } };
