import { describe, expect, it } from 'vitest';
import { cityOf, groupZones, offsetMinutes, sameClock, searchable, untilNextMinute, wallClockIn, zoneDifferences, type Zone } from './zones';

const zone = (id: string, city: string, offset: string, region: string): Zone => ({ id, city, offset, label: `${city} (${offset})`, region });

// As GET /v1/timezones answers: by region, then by offset.
const ZONES: Zone[] = [
  zone('Africa/Algiers', 'الجزائر', 'UTC+1', 'أفريقيا'),
  zone('Africa/Cairo', 'القاهرة', 'UTC+2', 'أفريقيا'),
  zone('America/New_York', 'نيويورك', 'UTC−4', 'الأمريكتان'),
  zone('Asia/Riyadh', 'الرياض', 'UTC+3', 'آسيا'),
  zone('Asia/Kolkata', 'كولكاتا', 'UTC+5:30', 'آسيا'),
  zone('Asia/Qatar', 'الدوحة', 'UTC+3', 'آسيا'),
];

describe('the list of zones', () => {
  it('keeps the API’s regions and order', () => {
    expect(groupZones(ZONES).map((g) => [g.region, g.zones.map((z) => z.id)])).toEqual([
      ['أفريقيا', ['Africa/Algiers', 'Africa/Cairo']],
      ['الأمريكتان', ['America/New_York']],
      ['آسيا', ['Asia/Riyadh', 'Asia/Kolkata', 'Asia/Qatar']],
    ]);
  });

  it('finds a city by its Arabic name, loosely, or by its IANA name or offset', () => {
    const ids = (q: string) => groupZones(ZONES, q).flatMap((g) => g.zones.map((z) => z.id));
    expect(ids('القاهره')).toEqual(['Africa/Cairo']);
    expect(ids('الجزاءر')).toEqual([]);
    expect(ids('الْجزائر')).toEqual(['Africa/Algiers']);
    expect(ids('new york')).toEqual(['America/New_York']);
    expect(ids('NEW_YORK')).toEqual(['America/New_York']);
    expect(ids('+3')).toEqual(['Asia/Riyadh', 'Asia/Qatar']);
    expect(ids('utc-4')).toEqual(['America/New_York']);
    expect(ids('آسيا +5')).toEqual(['Asia/Kolkata']);
    expect(ids('   ')).toHaveLength(ZONES.length);
    expect(groupZones(ZONES, 'لا مدينة')).toEqual([]);
  });

  it('compares letters as people type them', () => {
    expect(searchable('  أمريكا_الشمالية ')).toBe('امريكا الشماليه');
    expect(searchable('مُصطفى')).toBe('مصطفي');
  });
});

describe('a zone’s clock', () => {
  it('names the city without its offset', () => {
    expect(cityOf('القاهرة (UTC+2)')).toBe('القاهرة');
    expect(cityOf('Kolkata (UTC+5:30)')).toBe('Kolkata');
    expect(cityOf('UTC')).toBe('UTC');
  });

  it('reads the offset and the wall clock in a zone', () => {
    const at = new Date('2026-01-15T10:05:00Z');
    expect(offsetMinutes('Asia/Riyadh', at)).toBe(180);
    expect(offsetMinutes('Asia/Kolkata', at)).toBe(330);
    expect(offsetMinutes('America/New_York', at)).toBe(-300);
    expect(offsetMinutes('UTC', at)).toBe(0);
    expect(offsetMinutes('Mars/Olympus', at)).toBeNull();
    expect(wallClockIn('Asia/Riyadh', at)).toBe('13:05');
    expect(wallClockIn('America/New_York', at)).toBe('05:05');
    expect(wallClockIn('Asia/Riyadh', new Date('2026-01-15T21:00:00Z'))).toBe('00:00');
  });

  it('knows two zones keep the same clock only when they agree all year', () => {
    const at = new Date('2026-01-15T10:00:00Z');
    expect(sameClock('Asia/Riyadh', 'Asia/Qatar', at)).toBe(true);
    expect(sameClock('Asia/Riyadh', 'Asia/Riyadh', at)).toBe(true);
    // Egypt keeps summer time: the same as Riyadh in summer only.
    expect(sameClock('Africa/Cairo', 'Asia/Riyadh', at)).toBe(false);
    expect(sameClock('Africa/Cairo', 'Asia/Jerusalem', at)).toBe(false);
  });

  it('waits for the next minute', () => {
    expect(untilNextMinute(new Date('2026-01-15T10:00:59.500Z'))).toBe(520);
    expect(untilNextMinute(new Date('2026-01-15T10:00:00.000Z'))).toBe(60_020);
  });
});

describe('what the chip points out', () => {
  const at = new Date('2026-01-15T10:00:00Z');

  it('says nothing when the device and the account keep the pages’ clock', () => {
    expect(zoneDifferences({ shown: 'Asia/Riyadh', account: 'Asia/Kuwait', device: 'Asia/Riyadh', mismatch: false, at })).toEqual({ device: null, account: null, dot: false });
    expect(zoneDifferences({ shown: 'Asia/Riyadh', account: null, device: null, mismatch: false, at })).toEqual({ device: null, account: null, dot: false });
  });

  it('points to the device when it keeps another clock (the zone chosen here differs)', () => {
    expect(zoneDifferences({ shown: 'Africa/Cairo', account: 'Africa/Cairo', device: 'Europe/Istanbul', mismatch: false, at })).toEqual({ device: 'Europe/Istanbul', account: null, dot: true });
  });

  it('points to the account when the pages follow a device on another clock', () => {
    expect(zoneDifferences({ shown: 'Europe/Istanbul', account: 'Africa/Cairo', device: 'Europe/Istanbul', mismatch: true, at })).toEqual({ device: null, account: 'Africa/Cairo', dot: true });
  });

  it('keeps the API’s word for the dot, and leaves out zones the browser does not know', () => {
    expect(zoneDifferences({ shown: 'Asia/Riyadh', account: 'Nowhere/Here', device: 'Nowhere/There', mismatch: true, at })).toEqual({ device: null, account: null, dot: true });
  });
});
