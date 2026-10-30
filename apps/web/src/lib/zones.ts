/**
 * Time zones as the zone chip and My account show them: the API's list
 * (`GET /v1/timezones`: IANA id, city and offset in the reader's language, its
 * region; sorted by region, then offset), searched and grouped by region; and
 * the clocks of a few zones (now, and whether two keep the same one), read
 * through Intl in the browser. The API writes every time a page shows; these
 * are only the chip's own clocks.
 */
export type Zone = { id: string; city: string; offset: string; label: string; region: string };

export type ZoneGroup = { region: string; zones: Zone[] };

/** Letters as a search compares them: case, Arabic marks and the forms of alif, yaa and taa marbuta set aside. */
export function searchable(text: string): string {
  return text
    .toLowerCase()
    .replace(/[ً-ْٰـ]/g, '')
    .replace(/[إأآٱ]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .replace(/[_/]/g, ' ')
    .replace(/−/g, '-')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * The zones that match what was typed (every word somewhere in the city, the
 * IANA id, the offset or the region), by region in the API's order.
 */
export function groupZones(zones: readonly Zone[], query = ''): ZoneGroup[] {
  const words = searchable(query).split(' ').filter(Boolean);
  const groups: ZoneGroup[] = [];
  for (const zone of zones) {
    if (words.length > 0) {
      const text = searchable(`${zone.city} ${zone.id} ${zone.offset} ${zone.region}`);
      if (!words.every((w) => text.includes(w))) continue;
    }
    const last = groups[groups.length - 1];
    if (last && last.region === zone.region) last.zones.push(zone);
    else groups.push({ region: zone.region, zones: [zone] });
  }
  return groups;
}

/** A label without its offset: "القاهرة (UTC+2)" is "القاهرة". */
export function cityOf(label: string): string {
  return label.replace(/\s*\((?:UTC|GMT)[^)]*\)\s*$/, '').trim() || label;
}

/** Whether the browser knows this IANA zone. */
export function knownZone(zone: string | null | undefined): zone is string {
  if (!zone) return false;
  try {
    new Intl.DateTimeFormat('en-US', { timeZone: zone });
    return true;
  } catch {
    return false;
  }
}

/** Minutes from UTC in a zone at a moment (Cairo in summer: 180), or null for a zone the browser does not know. */
export function offsetMinutes(zone: string, at: Date): number | null {
  if (!knownZone(zone)) return null;
  const name = new Intl.DateTimeFormat('en-US', { timeZone: zone, timeZoneName: 'longOffset' }).formatToParts(at).find((p) => p.type === 'timeZoneName')?.value ?? '';
  const found = /([+-−])(\d{1,2}):?(\d{2})?/.exec(name);
  if (!found) return 0;
  const minutes = Number(found[2]) * 60 + Number(found[3] ?? 0);
  return found[1] === '+' ? minutes : -minutes;
}

/**
 * Whether two zones keep the same clock over the next year (Riyadh and Kuwait
 * do; Cairo and Riyadh only outside Egypt's summer time), as the API's
 * `Timezones::sameClock` decides.
 */
export function sameClock(a: string, b: string, from: Date = new Date()): boolean {
  if (a === b) return true;
  for (let month = 0; month < 12; month++) {
    const at = new Date(from);
    at.setUTCMonth(at.getUTCMonth() + month);
    if (offsetMinutes(a, at) !== offsetMinutes(b, at)) return false;
  }
  return true;
}

/** The wall clock in a zone ("HH:MM", 24 hours), for `formatClock` to write the reader's way. */
export function wallClockIn(zone: string, at: Date): string {
  const parts = new Intl.DateTimeFormat('en-GB', { timeZone: knownZone(zone) ? zone : 'UTC', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(at);
  const part = (type: string) => parts.find((p) => p.type === type)?.value ?? '00';
  return `${part('hour')}:${part('minute')}`;
}

/** Milliseconds until the next minute begins (at least a little), when the chip's clock changes. */
export function untilNextMinute(at: Date): number {
  return 60_000 - (at.getTime() % 60_000) + 20;
}

/**
 * What the chip has to point out, from the zone the pages are shown in, the
 * account's own zone (null when not chosen), the device's (from Intl) and the
 * API's word that the device and the account disagree: the device's zone when
 * it keeps another clock than the pages, the account's when it keeps another
 * clock than the pages; either one means the dot.
 */
export function zoneDifferences(input: { shown: string; account: string | null; device: string | null; mismatch: boolean; at?: Date }): {
  device: string | null;
  account: string | null;
  dot: boolean;
} {
  const at = input.at ?? new Date();
  const device = knownZone(input.device) && !sameClock(input.device, input.shown, at) ? input.device : null;
  const account = knownZone(input.account) && !sameClock(input.account, input.shown, at) ? input.account : null;
  return { device, account, dot: device !== null || account !== null || input.mismatch };
}
