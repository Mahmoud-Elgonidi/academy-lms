import { describe, expect, it } from 'vitest';
import type { GameCard, Island, World } from './types';
import { badgeOf, CONSTELLATION, currentIsland, firstName, litStars, mapHeight, minuteChoices, raceAt, raceLength, residentsOf, spotsOf, tileArt, usedShare } from './world';

const card = (over: Partial<GameCard> = {}): GameCard => ({ id: 'g', key: 'x', island: 'birds', title: '', summary: '', how_to_play: '', kinds: ['choice'], playable: true, plays: 0, best: 0, quest: null, ...over });
const island = (key: string, over: Partial<Island> = {}): Island => ({ key, name: key, story: '', emoji: '', colors: {}, music: 'major', open: true, fresh: false, homework: false, creatures: [], games: [], next: null, ...over });

describe('the map', () => {
  it('keeps the design\'s spots and puts an island added later below the others', () => {
    const spots = spotsOf([island('birds'), island('reef'), island('caves')]);
    expect(spots.birds).toMatchObject({ x: 18, y: 470, w: 200, h: 190 });
    expect(spots.reef).toMatchObject({ x: 190, y: 700 });
    expect(spots.caves).toMatchObject({ x: 18, y: 860 });
    expect(mapHeight(spotsOf([island('birds')]))).toBe(760);
    expect(mapHeight(spots)).toBe(860 + 140 + 40);
  });

  it('is at the first quest\'s island, else the first open one', () => {
    const islands = [island('volcano', { open: false }), island('birds'), island('turtles')];
    const quest = { game: card({ island: 'turtles' }) } as World['quests'][number];
    expect(currentIsland({ islands, quests: [quest] })).toBe('turtles');
    expect(currentIsland({ islands, quests: [] })).toBe('birds');
    expect(currentIsland({ islands: [island('volcano', { open: false })], quests: [] })).toBeNull();
  });
});

describe('an island\'s games', () => {
  it('draw their own tile, else one by what they play, and a lock without practice', () => {
    expect(tileArt(card({ key: 'bird-gates' }))).toBe('gates');
    expect(tileArt(card({ key: 'turtle-bridge' }))).toBe('bridge');
    expect(tileArt(card({ key: 'new', kinds: ['order', 'pair'] }))).toBe('cards');
    expect(tileArt(card({ key: 'new', kinds: ['choice'] }))).toBe('balloons');
    expect(tileArt(card({ key: 'new', kinds: ['order'] }))).toBe('gates');
    expect(tileArt(card({ key: 'bird-gates', playable: false }))).toBe('lock');
  });

  it('say homework first, then soon, new, or their best stars', () => {
    expect(badgeOf(card({ quest: { key: 'q', reason: 'homework', title: '', items: 3, done: false }, playable: false }))).toBe('homework');
    expect(badgeOf(card({ playable: false }))).toBe('soon');
    expect(badgeOf(card({ plays: 0 }))).toBe('new');
    expect(badgeOf(card({ plays: 4, best: 2 }))).toBe('stars');
  });

  it('show up to three creatures hatched there, else the first one waiting', () => {
    const c = (key: string, found: boolean) => ({ key, found, look: { kind: key } }) as Island['creatures'][number];
    expect(residentsOf(island('birds', { creatures: [c('a', true), c('b', false), c('c', true), c('d', true), c('e', true)] }))).toEqual([{ kind: 'a' }, { kind: 'c' }, { kind: 'd' }]);
    expect(residentsOf(island('birds', { creatures: [c('a', false), c('b', false)] }))).toEqual([{ kind: 'a' }]);
  });
});

describe('the race against yourself', () => {
  it('runs a little past the slower time, at least 30 seconds', () => {
    expect(raceLength(null, null)).toBe(30);
    expect(raceLength(40, 60)).toBe(78);
    expect(raceAt(null, 30)).toBe(80);
    expect(raceAt(0, 30)).toBe(6);
    expect(raceAt(60, 30)).toBe(80);
  });
});

describe('the week\'s constellation', () => {
  it('lights a star for each star of the last seven days, up to fourteen', () => {
    const now = Date.parse('2026-10-11T12:00:00Z');
    const at = (utc: string) => ({ utc, local: utc, timezone: 'UTC', label: '' });
    const history = [
      { at: at('2026-10-10T12:00:00Z'), share: 1, stars: 3 },
      { at: at('2026-10-01T12:00:00Z'), share: 1, stars: 3 },
      { at: null, share: 1, stars: 3 },
      { at: at('2026-10-11T08:00:00Z'), share: 0.6, stars: 2 },
    ];
    expect(litStars(history, now)).toBe(5);
    expect(litStars(Array.from({ length: 10 }, () => history[0]!), now)).toBe(CONSTELLATION.length);
  });
});

describe('a guardian\'s limits', () => {
  it('offer no more minutes than the academy\'s', () => {
    expect(minuteChoices({ choices: [10, 15, 20, 30, 45, 60], academy_minutes: 30 })).toEqual([10, 15, 20, 30]);
    expect(minuteChoices({ choices: [10, 60], academy_minutes: null })).toEqual([10, 60]);
    expect(usedShare(21, 15)).toBe(1);
    expect(usedShare(5, 20)).toBe(0.25);
    expect(usedShare(5, null)).toBe(0);
    expect(firstName('  يوسف أحمد ')).toBe('يوسف');
  });
});
