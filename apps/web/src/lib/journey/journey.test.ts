import { describe, expect, it } from 'vitest';
import {
  chestShare,
  countdown,
  courseOf,
  findHomework,
  hasHomework,
  hasStars,
  hereIsland,
  homeworkGame,
  homeworkTarget,
  islandBefore,
  islandSpots,
  journeyMoments,
  lessonAt,
  lookOf,
  markOf,
  missionsOf,
  missionsOn,
  playsOnly,
  practiceStations,
  seaHeight,
  seaPath,
  starsToNext,
  wiggle,
} from './journey';
import type { HomeworkMission, JourneyCourse, JourneyIsland, JourneyMap, LessonMission, Mission, Station } from './types';

const at = (utc: string) => ({ utc, local: utc, timezone: 'UTC', label: utc });

const station = (id: string, over: Partial<Station> = {}): Station => ({
  id,
  title: id,
  unit: null,
  state: 'done',
  due_review: false,
  playable: true,
  practice: null,
  missions: [],
  games: [],
  race: null,
  ...over,
});

const game = (id: string) => ({ id, key: id, island: 'birds', title: id, summary: '', how_to_play: '', kinds: ['choice'], chosen: false, items: 4 });

const island = (id: string, state: JourneyIsland['state'], stations: Station[], art = 'birds'): JourneyIsland => ({
  id,
  index: 1,
  title: id,
  percent: 0,
  state,
  lessons: stations.length,
  done: 0,
  art: { key: art, name: art, emoji: '', colors: {}, music: 'major' },
  creatures: [],
  next_creature: null,
  stations,
});

const course = (id: string, islands: JourneyIsland[]): JourneyCourse => ({
  enrollment_id: id,
  subject: 'Quran',
  teacher: null,
  program: null,
  has_curriculum: true,
  percent: 40,
  finished: false,
  lessons: null,
  eta_weeks: null,
  finish_on: null,
  current: null,
  islands,
});

const homework = (over: Partial<HomeworkMission> = {}): HomeworkMission => ({
  key: 'homework:h1',
  kind: 'homework',
  kind_label: '',
  state: 'open',
  enrollment_id: 'c1',
  island_id: 'i2',
  station_id: 's4',
  title: 'Recite',
  homework_id: 'h1',
  due_at: at('2026-10-13T18:45:00Z'),
  accepts: 'audio',
  practice: true,
  game: null,
  result: null,
  stars: 2,
  ...over,
});

const lesson = (over: Partial<LessonMission> = {}): LessonMission => ({
  key: 'lesson:l1',
  kind: 'lesson',
  kind_label: '',
  state: 'open',
  enrollment_id: 'c1',
  island_id: 'i2',
  station_id: 's4',
  title: 'Quran',
  lesson_id: 'l1',
  starts_at: at('2026-10-15T16:00:00Z'),
  ends_at: at('2026-10-15T16:30:00Z'),
  join: { open: false, opens_at: at('2026-10-15T15:50:00Z') },
  stars: 4,
  ...over,
});

const first = island('i1', 'done', [station('s1', { due_review: true, games: [game('g1')] }), station('s2')]);
const second = island('i2', 'here', [station('s3', { state: 'review', games: [game('g2')] }), station('s4', { state: 'here', missions: ['homework:h1', 'lesson:l1'], games: [game('g3'), game('g4')] }), station('s5', { state: 'todo', playable: false })], 'turtles');
const third = island('i3', 'ahead', [station('s6', { state: 'todo', playable: false })]);
const map = (over: Partial<JourneyMap> = {}): JourneyMap => ({
  learner_id: 'kid',
  stage: 'kids',
  style: 'playful',
  viewer: 'self',
  stars: { total: 151, rank: { key: 'hero', stars: 151, next: 300, next_key: 'legend', progress: 0, label: 'Hero', next_label: 'Legend' }, from: {}, per_egg: 12 },
  streak: 7,
  last_lesson: null,
  courses: [course('c1', [first, second, third])],
  missions: [lesson(), homework(), { key: 'review:s1', kind: 'review', kind_label: '', state: 'open', enrollment_id: 'c1', island_id: 'i1', station_id: 's1', title: 's1' }],
  chest: { period: '2026-10-03', target: 3, progress: 2, state: 'active', stars: 10, sticker: null, ends_at: at('2026-10-09T23:00:00Z'), can_open: false },
  games: { allowance: { may: true, used: 0, limit: 30, left: 30, reason: null, reason_label: null, night_lock: false }, egg: { have: 7, need: 12, ready: false }, buddy: null, creatures_found: 1, creatures_total: 20, recent: [], limits: null, glance: null },
  cheers: [],
  cheers_left: 0,
  ...over,
});

describe('the look of the journey', () => {
  it("is the student's own stage, calm for teens and adults, and a guardian's look for a guardian", () => {
    expect(lookOf({ viewer: 'self', style: 'playful' }, 'kids')).toBe('kids');
    expect(lookOf({ viewer: 'self', style: 'playful' }, 'middle')).toBe('middle');
    expect(lookOf({ viewer: 'self', style: 'playful' }, 'teen')).toBe('calm');
    expect(lookOf({ viewer: 'self', style: 'playful' }, 'classic')).toBe('calm');
    expect(lookOf({ viewer: 'guardian', style: 'playful' }, 'classic')).toBe('guardian');
  });

  it('is calm for everyone when the academy chose it, and has no stars in the plain style', () => {
    expect(lookOf({ viewer: 'self', style: 'calm' }, 'kids')).toBe('calm');
    expect(lookOf({ viewer: 'guardian', style: 'calm' }, 'classic')).toBe('guardian');
    expect(hasStars({ style: 'plain' })).toBe(false);
    expect(hasStars({ style: 'calm' })).toBe(true);
  });
});

describe('islands and stations', () => {
  it('marks a lesson mastered and due again for review', () => {
    expect(markOf({ state: 'done', due_review: false })).toBe('done');
    expect(markOf({ state: 'done', due_review: true })).toBe('review');
    expect(markOf({ state: 'review', due_review: false })).toBe('review');
    expect(markOf({ state: 'here', due_review: true })).toBe('here');
    expect(markOf({ state: 'todo', due_review: false })).toBe('todo');
  });

  it('finds the course chosen, the island the learner is on and the one before an island', () => {
    const two = [course('c1', [first]), course('c2', [second])];
    expect(courseOf(two, 'c2')?.enrollment_id).toBe('c2');
    expect(courseOf(two, 'gone')?.enrollment_id).toBe('c1');
    expect(courseOf([], null)).toBeNull();
    expect(hereIsland(course('c1', [first, second, third]))?.id).toBe('i2');
    // A course finished: the last island.
    expect(hereIsland(course('c1', [first]))?.id).toBe('i1');
    expect(islandBefore(course('c1', [first, second, third]), 'i3')?.id).toBe('i2');
    expect(islandBefore(course('c1', [first, second, third]), 'i1')).toBeNull();
  });

  it('flags a station with open homework and puts the lesson boat on it', () => {
    expect(hasHomework(second.stations[1]!)).toBe(true);
    expect(hasHomework(second.stations[0]!)).toBe(false);
    expect(lessonAt(map().missions, 's4')?.lesson_id).toBe('l1');
    expect(lessonAt(map().missions, 's3')).toBeNull();
    expect(missionsOn(map().missions, 's4').map((m) => m.kind)).toEqual(['lesson', 'homework']);
  });

  it('keeps the missions of the course on screen, and those of no course', () => {
    const missions: Mission[] = [lesson(), homework({ enrollment_id: 'c2' }), homework({ key: 'homework:h9', enrollment_id: null })];
    expect(missionsOf(missions, 'c1').map((m) => m.key)).toEqual(['lesson:l1', 'homework:h9']);
    expect(missionsOf(missions, null)).toHaveLength(3);
  });

  it('offers practice on the station they are on first, then reviews, then the rest', () => {
    expect(practiceStations(course('c1', [first, second, third])).map((s) => s.id)).toEqual(['s4', 's1', 's3']);
    expect(practiceStations(null)).toEqual([]);
  });
});

describe("a homework's practice", () => {
  it("plays the teacher's game, else the best game on its station, only when it asks for practice", () => {
    expect(homeworkGame(homework({ game: 'turtle-bridge' }), map())).toBe('turtle-bridge');
    expect(homeworkGame(homework(), map())).toBe('g3');
    expect(homeworkGame(homework({ practice: false }), map())).toBeNull();
    expect(homeworkGame(homework({ station_id: null }), map())).toBeNull();
  });

  it('opens from ?homework= while it is still to do and the journey has games', () => {
    expect(homeworkTarget(map(), 'h1')).toEqual({ game: 'g3', homework: 'h1' });
    expect(homeworkTarget(map(), 'other')).toBeNull();
    expect(homeworkTarget(map(), undefined)).toBeNull();
    expect(homeworkTarget(map({ games: null }), 'h1')).toBeNull();
    expect(homeworkTarget(map({ style: 'plain' }), 'h1')).toBeNull();
    expect(homeworkTarget(map({ missions: [homework({ state: 'done' })] }), 'h1')).toBeNull();
    expect(findHomework(map().missions, 'h1')?.homework_id).toBe('h1');
  });

  it('has no hand-in form when it is handed in only by playing', () => {
    expect(playsOnly({ accepts: 'practice' })).toBe(true);
    expect(playsOnly({ accepts: 'audio' })).toBe(false);
  });
});

describe('the clock', () => {
  it("refreshes the journey when a lesson's room opens, the lesson starts, the room closes and the week's chest closes", () => {
    expect(journeyMoments(map())).toEqual(['2026-10-15T15:50:00Z', '2026-10-15T16:00:00Z', '2026-10-15T16:40:00.000Z', '2026-10-09T23:00:00Z']);
    expect(journeyMoments({ missions: [], chest: null })).toEqual([]);
  });

  it('counts down in days, hours and minutes, and stops once it came', () => {
    const now = Date.parse('2026-10-11T12:00:00Z');
    expect(countdown('2026-10-13T15:30:00Z', now)).toEqual({ days: 2, hours: 3, minutes: 30 });
    expect(countdown('2026-10-11T12:00:30Z', now)).toEqual({ days: 0, hours: 0, minutes: 1 });
    expect(countdown('2026-10-11T11:00:00Z', now)).toBeNull();
    expect(countdown('not a time', now)).toBeNull();
  });
});

describe('stars and the chest', () => {
  it('says the stars left to the next rank, none at the top', () => {
    expect(starsToNext({ stars: 74, next: 80 })).toBe(6);
    expect(starsToNext({ stars: 320, next: null })).toBeNull();
    expect(starsToNext({ stars: 90, next: 80 })).toBe(0);
  });

  it("fills the week's chest by its missions done", () => {
    expect(chestShare({ target: 3, progress: 2 })).toBe(67);
    expect(chestShare({ target: 0, progress: 0 })).toBe(0);
    expect(chestShare({ target: 2, progress: 5 })).toBe(100);
  });
});

describe('the sea', () => {
  it('lays the islands top to bottom, one side then the other, from under its header', () => {
    const spots = islandSpots([first, second, third]);
    expect(spots.i1).toMatchObject({ y: 76, x: 390 - 200 - 14 });
    expect(spots.i2).toMatchObject({ y: 76 + 172, x: 14 });
    expect(spots.i3!.y).toBe(76 + 2 * 172);
    expect(islandSpots([first], 120).i1!.y).toBe(120);
    expect(seaHeight(spots)).toBe(spots.i3!.y + spots.i3!.h + 56);
    expect(seaHeight({})).toBe(560);
  });

  it('draws the way from island to island, and winds the stops of an island', () => {
    const spots = islandSpots([first, second]);
    expect(seaPath([spots.i1!, spots.i2!])).toMatch(/^M\d+(\.\d+)? \d+(\.\d+)? C/);
    expect(seaPath([spots.i1!])).toBe('');
    expect([0, 1, 2, 3, 4, 5, 6, 7, 8].map(wiggle)).toEqual([0, 54, 78, 54, 0, -54, -78, -54, 0]);
  });
});
