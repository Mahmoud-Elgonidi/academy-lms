import { describe, expect, it } from 'vitest';
import { length } from './recordings';

describe('a recording\'s length', () => {
  it('reads minutes and seconds, with hours only past an hour', () => {
    expect(length(0)).toBe('0:00');
    expect(length(20)).toBe('0:20');
    expect(length(25 * 60)).toBe('25:00');
    expect(length(3600 + 2 * 60 + 5)).toBe('1:02:05');
  });
});
