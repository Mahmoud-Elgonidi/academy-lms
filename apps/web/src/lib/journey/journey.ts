import { islandBox } from '../games/art';
import type { PlayTarget } from '../games/types';
import type { Stage } from '../family/types';
import type { HomeworkMission, JourneyCourse, JourneyIsland, JourneyMap, LessonMission, Mission, Station, StationGame } from './types';

/**
 * The journey's reckoning (docs/architecture/02, section 18), as plain logic
 * the pages and their tests share: which look draws it, where the islands
 * and the stops sit, what a station shows, which game a homework plays, and
 * the moments the clock alone changes it.
 */

/** The four ways the journey is drawn: a child's islands, middle school's levels, the calm list, a guardian's look at their child. */
export type JourneyLook = 'kids' | 'middle' | 'calm' | 'guardian';

/**
 * The look for this viewer: a guardian always looks at their child's journey
 * (in the classic look); calm draws everyone the same; otherwise the student's
 * own stage (teens and adults are calm anyway).
 */
export function lookOf(map: Pick<JourneyMap, 'viewer' | 'style'>, viewerStage: Stage): JourneyLook {
  if (map.viewer === 'guardian') return 'guardian';
  if (map.style === 'calm') return 'calm';
  if (viewerStage === 'kids') return 'kids';
  if (viewerStage === 'middle') return 'middle';
  return 'calm';
}

/** Stars, the chest, the egg and the games: everywhere but in the plain style. */
export function hasStars(map: Pick<JourneyMap, 'style'>): boolean {
  return map.style !== 'plain';
}

/** How a station is marked on the path: a lesson mastered and due again is shown for review. */
export function markOf(station: Pick<Station, 'state' | 'due_review'>): 'done' | 'review' | 'here' | 'todo' {
  if (station.state === 'here' || station.state === 'todo') return station.state;
  return station.state === 'review' || station.due_review ? 'review' : 'done';
}

/** The course on screen: the one chosen, else the first. */
export function courseOf(courses: readonly JourneyCourse[], chosen: string | null): JourneyCourse | null {
  return courses.find((c) => c.enrollment_id === chosen) ?? courses[0] ?? null;
}

/** The island the learner is on (else the last one, the course finished). */
export function hereIsland(course: Pick<JourneyCourse, 'islands'> | null): JourneyIsland | null {
  if (!course) return null;
  return course.islands.find((i) => i.state === 'here') ?? course.islands.at(-1) ?? null;
}

/** The island before this one (an island ahead opens once that one is done). */
export function islandBefore(course: Pick<JourneyCourse, 'islands'>, islandId: string): JourneyIsland | null {
  const at = course.islands.findIndex((i) => i.id === islandId);
  return at > 0 ? (course.islands[at - 1] ?? null) : null;
}

/** A station anywhere in the journey, with its island and course. */
export function findStation(map: Pick<JourneyMap, 'courses'>, stationId: string | null): { course: JourneyCourse; island: JourneyIsland; station: Station } | null {
  if (!stationId) return null;
  for (const course of map.courses) {
    for (const island of course.islands) {
      const station = island.stations.find((s) => s.id === stationId);
      if (station) return { course, island, station };
    }
  }
  return null;
}

/** The missions on a station (done homework too, to show what it brought). */
export function missionsOn(missions: readonly Mission[], stationId: string): Mission[] {
  return missions.filter((m) => m.station_id === stationId);
}

/** The course's missions (those of no course too), as the strip shows them. */
export function missionsOf(missions: readonly Mission[], enrollmentId: string | null): Mission[] {
  return missions.filter((m) => !enrollmentId || m.enrollment_id === null || m.enrollment_id === enrollmentId);
}

/** The lesson on the course's here station, for its boat. */
export function lessonAt(missions: readonly Mission[], stationId: string): LessonMission | null {
  return (missions.find((m) => m.kind === 'lesson' && m.station_id === stationId) as LessonMission | undefined) ?? null;
}

/** Whether a station carries an open homework (a flag on it). */
export function hasHomework(station: Pick<Station, 'missions'>): boolean {
  return station.missions.some((key) => key.startsWith('homework:'));
}

/**
 * The game a homework's practice plays: the teacher's choice, else the best
 * game on its station; null when nothing can play it (handed in another way).
 */
export function homeworkGame(mission: Pick<HomeworkMission, 'practice' | 'game' | 'station_id'>, map: Pick<JourneyMap, 'courses'>): string | null {
  if (!mission.practice) return null;
  if (mission.game) return mission.game;
  return findStation(map, mission.station_id)?.station.games[0]?.id ?? null;
}

/**
 * The round a homework's practice opens with (?homework= on the journey, the
 * homework page's "play"): only for practice still to do, with a game that
 * can play it, while the journey has games.
 */
export function homeworkTarget(map: Pick<JourneyMap, 'courses' | 'missions' | 'games' | 'style'>, homeworkId: string | null | undefined): PlayTarget | null {
  if (!hasStars(map) || !map.games) return null;
  const mission = findHomework(map.missions, homeworkId);
  if (!mission || mission.state === 'done') return null;
  const game = homeworkGame(mission, map);
  return game ? { game, homework: mission.homework_id } : null;
}

/** A homework mission by its homework's id. */
export function findHomework(missions: readonly Mission[], homeworkId: string | null | undefined): HomeworkMission | null {
  if (!homeworkId) return null;
  return (missions.find((m) => m.kind === 'homework' && m.homework_id === homeworkId) as HomeworkMission | undefined) ?? null;
}

/** Whether a homework is handed in only by playing (no hand-in form). */
export function playsOnly(mission: Pick<HomeworkMission, 'accepts'>): boolean {
  return mission.accepts === 'practice';
}

/** The stations a guardian (or anyone) can practise now, the one they are on first, then reviews, then the rest. */
export function practiceStations(course: Pick<JourneyCourse, 'islands'> | null): Station[] {
  if (!course) return [];
  const all = course.islands.flatMap((i) => i.stations).filter((s) => s.playable && s.games.length > 0);
  const order = (s: Station) => ({ here: 0, review: 1, done: 2, todo: 3 })[markOf(s)];
  return [...all].sort((a, b) => order(a) - order(b));
}

/** The room opens, the lesson starts, the room closes (10 minutes after the end), and the week's chest closes: the moments the clock alone changes the journey. */
export function journeyMoments(map: Pick<JourneyMap, 'missions' | 'chest'>): string[] {
  const after = (utc: string) => new Date(Date.parse(utc) + 10 * 60_000).toISOString();
  const lessons = map.missions
    .filter((m): m is LessonMission => m.kind === 'lesson')
    .flatMap((m) => [m.join.opens_at?.utc, m.starts_at.utc, after(m.ends_at?.utc ?? m.starts_at.utc)]);
  return [...lessons, map.chest?.ends_at?.utc].filter((m): m is string => !!m);
}

/** How long until a moment, in whole days, hours and minutes (none once it came). */
export function countdown(utc: string, now: number): { days: number; hours: number; minutes: number } | null {
  const left = Date.parse(utc) - now;
  if (!Number.isFinite(left) || left <= 0) return null;
  const minutes = Math.ceil(left / 60_000);
  return { days: Math.floor(minutes / 1440), hours: Math.floor((minutes % 1440) / 60), minutes: minutes % 60 };
}

/** Stars left to the next rank (null at the top). */
export function starsToNext(rank: { stars: number; next: number | null }): number | null {
  return rank.next === null ? null : Math.max(0, rank.next - rank.stars);
}

/** The share of the week's chest done, 0 to 100. */
export function chestShare(chest: { target: number; progress: number }): number {
  return chest.target > 0 ? Math.min(100, Math.round((100 * chest.progress) / chest.target)) : 0;
}

/** The games a station offers in order, the teacher's choice first (as the API sends them). */
export function gamesOf(station: Pick<Station, 'playable' | 'games'>): StationGame[] {
  return station.playable ? station.games : [];
}

/** Where each island sits on the 390-wide sea, top to bottom, one side then the other (the first on the right, where Arabic starts). */
export type IslandSpot = { x: number; y: number; w: number; h: number; cx: number; cy: number; delay: string };

export const SEA_WIDTH = 390;
const SEA_STEP = 172;

/** The islands' spots, from `top` down (under the map's header, and its course chips when there are). */
export function islandSpots(islands: readonly Pick<JourneyIsland, 'id' | 'art'>[], top = 76): Record<string, IslandSpot> {
  const spots: Record<string, IslandSpot> = {};
  islands.forEach((island, i) => {
    const { width: w, height: h } = islandBox(island.art?.key ?? 'plain');
    const x = i % 2 === 0 ? SEA_WIDTH - w - 14 : 14;
    const y = top + i * SEA_STEP;
    spots[island.id] = { x, y, w, h, cx: x + w / 2, cy: y + h / 2, delay: ['', 'gw-d2', 'gw-d3'][i % 3]! };
  });
  return spots;
}

/** How tall the sea is for these islands (never less than a phone's screen of it). */
export function seaHeight(spots: Record<string, IslandSpot>): number {
  return Math.max(560, ...Object.values(spots).map((s) => s.y + s.h + 56));
}

/** The dotted way from island to island, through their middles. */
export function seaPath(spots: readonly IslandSpot[]): string {
  if (spots.length < 2) return '';
  const [first, ...rest] = spots;
  let d = `M${first!.cx} ${first!.cy}`;
  let prev = first!;
  for (const s of rest) {
    const midY = (prev.cy + s.cy) / 2;
    d += ` C${prev.cx} ${midY} ${s.cx} ${midY} ${s.cx} ${s.cy}`;
    prev = s;
  }
  return d;
}

/** A stop's sideways step on the winding path of an island's stations (in px, the reading side first). */
export function wiggle(index: number): number {
  return [0, 54, 78, 54, 0, -54, -78, -54][index % 8]!;
}

/** The latest lesson whose stars were shown, kept on this device. */
export const SEEN_LESSON_KEY = 'lms.journey.lesson-seen';
