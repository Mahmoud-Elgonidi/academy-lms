import { describe, expect, it } from 'vitest';
import { initialOf } from './initial';

describe('initialOf', () => {
  it('passes over a title to the name itself', () => {
    expect(initialOf('أ. خالد')).toBe('خ');
    expect(initialOf('د. منى سالم')).toBe('م');
    expect(initialOf('Ms Sara')).toBe('S');
    expect(initialOf('Mr. Omar')).toBe('O');
  });

  it('keeps a plain name, and a name that is only a title', () => {
    expect(initialOf('  ناصر علي ')).toBe('ن');
    expect(initialOf('أ.')).toBe('أ');
    expect(initialOf('')).toBe('');
  });
});
