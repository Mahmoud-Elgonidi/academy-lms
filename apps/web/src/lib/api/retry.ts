/**
 * A read that meets the API's rate limit (429) waits a moment and asks once
 * more, so someone who opens many pages quickly sees the page a second later
 * instead of an error. Writes are never repeated here: whether a write may be
 * sent again is the caller's (its idempotency key).
 */

/** The longest wait a page render takes on itself; a longer Retry-After is answered with the refusal. */
export const MAX_WAIT_SECONDS = 2;

type Fetcher = (url: string, init: RequestInit) => Promise<Response>;
type Sleep = (ms: number) => Promise<void>;

const pause: Sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Seconds to wait before the second try, or null when the read should not be tried again. */
export function waitBeforeRetry(response: Response): number | null {
  if (response.status !== 429) return null;
  const header = response.headers.get('Retry-After');
  if (header === null || header.trim() === '') return 1;
  const seconds = Number(header);
  if (!Number.isFinite(seconds) || seconds < 0) return 1;
  return seconds <= MAX_WAIT_SECONDS ? seconds : null;
}

export async function fetchWithRetry(url: string, init: RequestInit, fetcher: Fetcher = fetch, sleep: Sleep = pause): Promise<Response> {
  const response = await fetcher(url, init);
  if ((init.method ?? 'GET').toUpperCase() !== 'GET') return response;
  const wait = waitBeforeRetry(response);
  if (wait === null) return response;
  await sleep(wait * 1000);
  return fetcher(url, init);
}
