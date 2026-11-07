import 'server-only';
import { areaRead } from '../modules-server';

/**
 * The Quran's reads for a page. With the academy's plan leaving the Quran
 * out the API answers 403 feature-not-in-plan: the page says so calmly
 * (`off`), never an error page; anything else it cannot read is `missing`.
 */
export type QuranRead<T> = { state: 'ok'; data: T } | { state: 'off' } | { state: 'missing' };

export async function quranRead<T>(...args: Parameters<typeof areaRead>): Promise<QuranRead<T>> {
  const done = await areaRead<{ data: T }>(...args);
  if (done.state === 'ok') return { state: 'ok', data: done.data.data };
  return done.state === 'off' ? { state: 'off' } : { state: 'missing' };
}

/** The data of a read, or null. */
export function dataOf<T>(found: QuranRead<T>): T | null {
  return found.state === 'ok' ? found.data : null;
}
