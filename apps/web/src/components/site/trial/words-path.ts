import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';

/**
 * The key (under `site`) a trial word is read from: the adults' own
 * (`adults.<area>.<key>`) on a page for adults where it has one, else the
 * usual `<area>.<key>`. One rule for the page's form (words.tsx) and the
 * pick and lesson pages reached by link (words-server.ts).
 */
export function wordPath(has: (key: string) => boolean, adults: boolean, area: 'trial' | 'join', tail: string): string {
  return adults && has(`adults.${area}.${tail}`) ? `adults.${area}.${tail}` : `${area}.${tail}`;
}

/**
 * The trial flow speaks to adults: always on an academy for adults (its
 * audience, GET /v1/branding), else when its published page does (its
 * settings.audience); no page: the usual words.
 */
export function speaksToAdults(site: Pick<AcademySite, 'page'> | null | undefined, look?: Pick<Branding, 'audience'> | null): boolean {
  return look?.audience === 'adults' || site?.page?.settings.audience === 'adults';
}
