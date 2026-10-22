import { describe, expect, it } from 'vitest';
import { clock, DEFAULT_TIME_FORMAT, formatClock, timeFormatOf, type LocalTime } from './time';

const at = (local: string): LocalTime => ({ utc: '2026-10-22T14:05:00Z', local, timezone: 'Africa/Cairo', label: 'whatever the API wrote' });

describe('a clock in 12 hours', () => {
  it('says ص or م in Arabic and AM or PM in English, with Latin digits', () => {
    expect(formatClock('17:05', 'h12', 'ar')).toBe('5:05 م');
    expect(formatClock('17:05', 'h12', 'en')).toBe('5:05 PM');
    expect(formatClock('09:30', 'h12', 'ar')).toBe('9:30 ص');
    expect(formatClock('09:30', 'h12', 'en')).toBe('9:30 AM');
    expect(formatClock('23:55', 'h12', 'ar')).toBe('11:55 م');
    expect(formatClock('01:00', 'h12', 'en')).toBe('1:00 AM');
  });

  it('writes midnight and noon as 12', () => {
    expect(formatClock('00:00', 'h12', 'ar')).toBe('12:00 ص');
    expect(formatClock('00:15', 'h12', 'en')).toBe('12:15 AM');
    expect(formatClock('12:00', 'h12', 'ar')).toBe('12:00 م');
    expect(formatClock('12:45', 'h12', 'en')).toBe('12:45 PM');
    expect(formatClock('11:59', 'h12', 'ar')).toBe('11:59 ص');
  });

  it('ends a day at 24:00 as midnight', () => {
    expect(formatClock('24:00', 'h12', 'ar')).toBe('12:00 ص');
    expect(formatClock('24:00', 'h12', 'en')).toBe('12:00 AM');
  });

  it('takes the language from a regional locale too', () => {
    expect(formatClock('17:00', 'h12', 'ar-EG')).toBe('5:00 م');
    expect(formatClock('17:00', 'h12', 'en-GB')).toBe('5:00 PM');
  });
});

describe('a clock in 24 hours', () => {
  it('stays as the API keeps it, in both languages', () => {
    expect(formatClock('17:05', 'h24', 'ar')).toBe('17:05');
    expect(formatClock('17:05', 'h24', 'en')).toBe('17:05');
    expect(formatClock('00:00', 'h24', 'ar')).toBe('00:00');
    expect(formatClock('24:00', 'h24', 'en')).toBe('24:00');
  });

  it('pads an hour written with one digit, and leaves seconds out', () => {
    expect(formatClock('9:05', 'h24', 'ar')).toBe('09:05');
    expect(formatClock('17:05:30', 'h24', 'ar')).toBe('17:05');
    expect(formatClock('17:05:30', 'h12', 'en')).toBe('5:05 PM');
  });
});

describe('what is not a clock', () => {
  it('comes back as it was', () => {
    expect(formatClock('', 'h12', 'ar')).toBe('');
    expect(formatClock('soon', 'h12', 'en')).toBe('soon');
    expect(formatClock('25:00', 'h12', 'ar')).toBe('25:00');
    expect(formatClock('17:75', 'h24', 'ar')).toBe('17:75');
  });
});

describe('a LocalTime’s wall clock', () => {
  it('is written from `local` the reader’s way, never converted', () => {
    expect(clock(at('2026-10-22T17:05:00+03:00'), 'h12', 'ar')).toBe('5:05 م');
    expect(clock(at('2026-10-22T17:05:00+03:00'), 'h12', 'en')).toBe('5:05 PM');
    expect(clock(at('2026-10-22T17:05:00+03:00'), 'h24', 'ar')).toBe('17:05');
    expect(clock(at('2026-10-23T00:30:00-04:00'), 'h12', 'ar')).toBe('12:30 ص');
  });

  it('is nothing without a time', () => {
    expect(clock(null, 'h12', 'ar')).toBe('');
    expect(clock(undefined, 'h24', 'en')).toBe('');
  });
});

describe('the format from the API', () => {
  it('is 24 hours only when it says so, else 12', () => {
    expect(DEFAULT_TIME_FORMAT).toBe('h12');
    expect(timeFormatOf('h24')).toBe('h24');
    expect(timeFormatOf('h12')).toBe('h12');
    expect(timeFormatOf(null)).toBe('h12');
    expect(timeFormatOf(undefined)).toBe('h12');
    expect(timeFormatOf('H:i')).toBe('h12');
  });
});
