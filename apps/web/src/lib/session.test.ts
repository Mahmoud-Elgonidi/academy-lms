import { describe, expect, it } from 'vitest';
import { homeOf, safeNext } from './session';

describe('where a person starts', () => {
  it('sends teachers to teach, families home, and staff to the back office', () => {
    expect(homeOf(['teacher'])).toBe('teach');
    expect(homeOf(['admin', 'teacher'])).toBe('teach');
    expect(homeOf(['guardian'])).toBe('family');
    expect(homeOf(['student'])).toBe('family');
    expect(homeOf(['owner'])).toBe('staff');
    expect(homeOf([])).toBe('staff');
  });
});

describe('going back after signing in', () => {
  it('only goes to a page of this portal, in this language', () => {
    expect(safeNext('/ar/teach/week', 'ar')).toBe('/ar/teach/week');
    expect(safeNext('/ar/teach/week?from=2026-10-01', 'ar')).toBe('/ar/teach/week?from=2026-10-01');
    expect(safeNext('/en/teach', 'ar')).toBeNull();
    expect(safeNext('//evil.example/ar/', 'ar')).toBeNull();
    expect(safeNext('https://evil.example/ar/teach', 'ar')).toBeNull();
    expect(safeNext('/ar/\\evil.example', 'ar')).toBeNull();
    expect(safeNext('/ar/sign-in', 'ar')).toBeNull();
    expect(safeNext(null, 'ar')).toBeNull();
  });
});
