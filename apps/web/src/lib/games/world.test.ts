import { describe, expect, it } from 'vitest';
import { firstName, minuteChoices, raceAt, raceLength, residentsOf, tileArt, usedShare } from './world';

const game = (over: Partial<{ key: string; kinds: string[]; playable: boolean }> = {}) => ({ key: 'x', kinds: ['choice'], playable: true, ...over });

describe('a station\'s games', () => {
  it('draw their own tile, else one by what they play, and a lock when they cannot play now', () => {
    expect(tileArt(game({ key: 'bird-gates' }))).toBe('gates');
    expect(tileArt(game({ key: 'turtle-bridge' }))).toBe('bridge');
    expect(tileArt(game({ key: 'new', kinds: ['order', 'pair'] }))).toBe('cards');
    expect(tileArt(game({ key: 'new', kinds: ['choice'] }))).toBe('balloons');
    expect(tileArt(game({ key: 'new', kinds: ['order'] }))).toBe('gates');
    expect(tileArt(game({ key: 'bird-gates', playable: false }))).toBe('lock');
  });

  it('show up to three creatures hatched on an island, else the first one waiting', () => {
    const c = (key: string, found: boolean) => ({ found, look: { kind: key } });
    expect(residentsOf({ creatures: [c('a', true), c('b', false), c('c', true), c('d', true), c('e', true)] })).toEqual([{ kind: 'a' }, { kind: 'c' }, { kind: 'd' }]);
    expect(residentsOf({ creatures: [c('a', false), c('b', false)] })).toEqual([{ kind: 'a' }]);
    expect(residentsOf({ creatures: [] })).toEqual([{}]);
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
