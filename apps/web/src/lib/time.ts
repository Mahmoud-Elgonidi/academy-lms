/**
 * A time as the API writes it (LocalTimePresenter): the instant, the wall
 * clock in the viewer's zone, the zone, and a label ready to show. The portal
 * shows `label` (or `local`) as written and never converts zones itself.
 */
export type LocalTime = { utc: string; local: string; timezone: string | null; label: string };

/**
 * How a person reads a clock: 12 hours with ص/م (AM/PM), or 24 hours. Their
 * own choice, else the academy's (`Me.time_format` is the one in force);
 * 12 hours when nothing says otherwise, as the API does.
 */
export type TimeFormat = 'h12' | 'h24';

export const DEFAULT_TIME_FORMAT: TimeFormat = 'h12';

/** A format from the API's words (a string there), 12 hours for anything else. */
export function timeFormatOf(value: string | null | undefined): TimeFormat {
  return value === 'h24' ? 'h24' : DEFAULT_TIME_FORMAT;
}

/**
 * A wall clock ("HH:MM", as the API keeps one) written the reader's way:
 * "17:05" is "5:05 م" in Arabic and "5:05 PM" in English in 12 hours, and
 * "17:05" in 24. Midnight is "12:00 ص" (24:00, the end of a day, too), noon
 * "12:00 م". Written by hand rather than through Intl, so the server and the
 * browser write the same (with Latin digits), as Carbon does on the API.
 * Anything that is not a clock comes back as it was.
 */
export function formatClock(hhmm: string, format: TimeFormat, locale: string): string {
  const found = /^(\d{1,2}):(\d{2})/.exec(hhmm);
  if (!found) return hhmm;
  const hours = Number(found[1]);
  const minutes = found[2]!;
  if (hours > 24 || Number(minutes) > 59) return hhmm;
  if (format === 'h24') return `${String(hours).padStart(2, '0')}:${minutes}`;
  const afternoon = hours >= 12 && hours < 24;
  const hour = hours % 12 === 0 ? 12 : hours % 12;
  const arabic = locale.toLowerCase().startsWith('ar');
  const suffix = arabic ? (afternoon ? 'م' : 'ص') : afternoon ? 'PM' : 'AM';
  return `${hour}:${minutes} ${suffix}`;
}

/** The wall clock of a LocalTime ("5:00 م", or "17:00"), as the API wrote it, the reader's way. */
export function clock(time: LocalTime | null | undefined, format: TimeFormat, locale: string): string {
  const local = time?.local;
  return local ? formatClock(local.slice(11, 16), format, locale) : '';
}
