/**
 * A time as the API writes it (LocalTimePresenter): the instant, the wall
 * clock in the viewer's zone, the zone, and a label ready to show. The portal
 * shows `label` (or `local`) as written and never converts zones itself.
 */
export type LocalTime = { utc: string; local: string; timezone: string | null; label: string };

/** "19:00" from a LocalTime's wall clock, as written. */
export function clock(time: LocalTime | null | undefined): string {
  return time?.local?.slice(11, 16) ?? '';
}
