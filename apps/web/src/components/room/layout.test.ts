import { describe, expect, it } from 'vitest';
import { arrange, elapsed, gridShape, mainOf, nearestCorner, startCorner, type TileFact } from './layout';

const me: TileFact = { key: 'me:camera', local: true, screen: false };
const teacher: TileFact = { key: 'teacher:camera', local: false, screen: false };
const guardian: TileFact = { key: 'guardian:camera', local: false, screen: false };
const teacherScreen: TileFact = { key: 'teacher:screen', local: false, screen: true };
const myScreen: TileFact = { key: 'me:screen', local: true, screen: true };

describe('the room’s layout', () => {
  it('fills the stage with the one person there', () => {
    expect(arrange([], 'auto')).toEqual({ kind: 'empty' });
    expect(arrange([me], 'auto')).toEqual({ kind: 'solo', main: 'me:camera' });
    expect(arrange([me], 'grid')).toEqual({ kind: 'solo', main: 'me:camera' });
  });

  it('shows the other person large and one’s own camera small with two', () => {
    expect(arrange([me, teacher], 'auto')).toEqual({ kind: 'pair', main: 'teacher:camera', self: 'me:camera' });
    // Two others (one watching without a camera of their own): a grid.
    expect(arrange([teacher, guardian], 'auto')).toEqual({ kind: 'grid', tiles: ['teacher:camera', 'guardian:camera'] });
  });

  it('puts three or more in an equal grid', () => {
    expect(arrange([me, teacher, guardian], 'auto')).toEqual({ kind: 'grid', tiles: ['me:camera', 'teacher:camera', 'guardian:camera'] });
    expect(arrange([me, teacher], 'grid')).toEqual({ kind: 'grid', tiles: ['me:camera', 'teacher:camera'] });
  });

  it('gives every student of a group lesson a tile of their own, the whole class fitting the stage', () => {
    // The teacher (here) and a full group of eight: nine equal tiles, none of them off the stage.
    const students = Array.from({ length: 8 }, (_, i): TileFact => ({ key: `student${i}:camera`, local: false, screen: false }));
    const room = arrange([me, ...students], 'auto');
    expect(room).toEqual({ kind: 'grid', tiles: ['me:camera', ...students.map((s) => s.key)] });
    const shape = gridShape(9, 1200, 700);
    expect(shape.cols * shape.rows).toBeGreaterThanOrEqual(9);
    expect(shape.cols * shape.width).toBeLessThanOrEqual(1200);
    expect(shape.rows * shape.height).toBeLessThanOrEqual(700);
    // A student sharing their screen puts it first, the class in the strip.
    const shared: TileFact = { key: 'student3:screen', local: false, screen: true };
    expect(arrange([me, ...students, shared], 'auto')).toMatchObject({ kind: 'spotlight', main: 'student3:screen' });
  });

  it('puts a shared screen in the spotlight, someone else’s before one’s own', () => {
    expect(arrange([me, teacher, teacherScreen], 'auto')).toEqual({ kind: 'spotlight', main: 'teacher:screen', strip: ['me:camera', 'teacher:camera'] });
    expect(arrange([me, myScreen, teacher], 'auto')).toEqual({ kind: 'spotlight', main: 'me:screen', strip: ['me:camera', 'teacher:camera'] });
    expect(arrange([me, myScreen, teacher, teacherScreen], 'auto')).toMatchObject({ main: 'teacher:screen' });
    // The grid, when chosen, stays a grid during a share.
    expect(arrange([me, teacher, teacherScreen], 'grid').kind).toBe('grid');
  });

  it('spotlights the other person when asked, and a pinned tile whatever the mode', () => {
    expect(arrange([me, teacher], 'spotlight')).toEqual({ kind: 'spotlight', main: 'teacher:camera', strip: ['me:camera'] });
    expect(arrange([me, teacher], 'auto', 'me:camera')).toEqual({ kind: 'spotlight', main: 'me:camera', strip: ['teacher:camera'] });
    expect(arrange([me, teacher, teacherScreen], 'grid', 'teacher:camera')).toMatchObject({ kind: 'spotlight', main: 'teacher:camera' });
    // A pin on someone who left is forgotten.
    expect(arrange([me, teacher], 'auto', 'gone:camera').kind).toBe('pair');
  });

  it('names what the large place shows', () => {
    expect(mainOf(arrange([me, teacher], 'auto'))).toBe('teacher:camera');
    expect(mainOf(arrange([me, teacher, teacherScreen], 'auto'))).toBe('teacher:screen');
    expect(mainOf(arrange([], 'auto'))).toBeNull();
  });
});

describe('the grid’s shape', () => {
  it('fits every tile without scrolling, as large as it can', () => {
    expect(gridShape(1, 1600, 900, 16 / 9, 0)).toEqual({ cols: 1, rows: 1, width: 1600, height: 900 });
    expect(gridShape(2, 1600, 900, 16 / 9, 0)).toMatchObject({ cols: 2, rows: 1 });
    expect(gridShape(2, 390, 700, 16 / 9, 0)).toMatchObject({ cols: 1, rows: 2 });
    expect(gridShape(4, 1600, 900)).toMatchObject({ cols: 2, rows: 2 });
    for (const count of [3, 5, 7, 9, 12]) {
      const shape = gridShape(count, 1200, 700);
      expect(shape.cols * shape.rows).toBeGreaterThanOrEqual(count);
      expect(shape.cols * shape.width + (shape.cols - 1) * 8).toBeLessThanOrEqual(1200);
      expect(shape.rows * shape.height + (shape.rows - 1) * 8).toBeLessThanOrEqual(700);
    }
  });

  it('is empty without room', () => {
    expect(gridShape(0, 100, 100).width).toBe(0);
    expect(gridShape(3, 0, 100).width).toBe(0);
  });
});

describe('one’s own small picture', () => {
  it('snaps to the nearest corner and starts at the end of the line', () => {
    expect(nearestCorner(10, 10, 800, 600)).toBe('top-left');
    expect(nearestCorner(790, 20, 800, 600)).toBe('top-right');
    expect(nearestCorner(30, 590, 800, 600)).toBe('bottom-left');
    expect(nearestCorner(700, 500, 800, 600)).toBe('bottom-right');
    expect(startCorner(false)).toBe('bottom-right');
    expect(startCorner(true)).toBe('bottom-left');
  });
});

describe('the time since joining', () => {
  it('reads mm:ss, with hours past an hour', () => {
    expect(elapsed(0)).toBe('00:00');
    expect(elapsed(65)).toBe('01:05');
    expect(elapsed(3600 + 125)).toBe('1:02:05');
    expect(elapsed(-3)).toBe('00:00');
  });
});
