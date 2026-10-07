import { describe, expect, it } from 'vitest';
import { addDays, addMonths, dayLabel, hoursOf, weekOrder, weekdayName, zoneName } from './format';

describe('calendar days', () => {
  it('stay the same day whatever the server clock', () => {
    expect(dayLabel('2026-10-11', 'en')).toBe('Sunday 11 October');
    expect(dayLabel('2026-10-11', 'ar')).toContain('11');
    expect(dayLabel('2026-10-11', 'ar')).toContain('أكتوبر');
  });

  it('move by days and months across their ends', () => {
    expect(addDays('2026-10-30', 3)).toBe('2026-11-02');
    expect(addDays('2026-03-01', -1)).toBe('2026-02-28');
    expect(addMonths('2026-12', 1)).toBe('2027-01');
    expect(addMonths('2026-01', -1)).toBe('2025-12');
  });

  it('count the week from Saturday in Arabic and Monday in English', () => {
    expect(weekOrder('ar')).toEqual([6, 7, 1, 2, 3, 4, 5]);
    expect(weekOrder('en')[0]).toBe(1);
    expect(weekdayName(1, 'en')).toBe('Monday');
    expect(weekdayName(6, 'ar')).toBe('السبت');
  });
});

describe('words', () => {
  it('say hours as people do', () => {
    expect(hoursOf(90)).toBe('1.5');
    expect(hoursOf(360)).toBe('6');
  });

  it('name a zone by its city, in Arabic where known', () => {
    expect(zoneName('Africa/Cairo', 'ar')).toBe('القاهرة');
    expect(zoneName('America/New_York', 'en')).toBe('New York');
    expect(zoneName('Pacific/Chatham', 'ar')).toBe('Chatham');
    expect(zoneName(null, 'ar')).toBe('');
  });
});
