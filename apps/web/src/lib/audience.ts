/**
 * Whom the academy serves (`academies.audience`, GET /v1/branding `audience`,
 * GET /v1/me `academy.audience`): `families` (children with their guardians,
 * as at first) or `adults` (companies and offices that train professionals:
 * no guardians, no children, training words). docs/architecture/02, section 29.
 *
 * For adults every word the portal says comes from `messages/{locale}.json`
 * with `messages/adults/{locale}.json` laid over it (only the keys that
 * differ), read once per request in `src/i18n/request.ts`; a families
 * academy reads its words exactly as before.
 */
export type Audience = 'families' | 'adults';

/** The API's word, anything else (an older API, no academy) as families. */
export function audienceOf(value: unknown): Audience {
  return value === 'adults' ? 'adults' : 'families';
}

export type MessageTree = { [key: string]: unknown };

const isTree = (value: unknown): value is MessageTree => typeof value === 'object' && value !== null && !Array.isArray(value);

/** The base words with the overlay's laid over them, key by key (a list or a sentence is replaced whole). */
export function overlaid<T extends MessageTree>(base: T, over: MessageTree): T {
  const out: MessageTree = { ...base };
  for (const [key, value] of Object.entries(over)) {
    const under = out[key];
    out[key] = isTree(value) && isTree(under) ? overlaid(under, value) : value;
  }
  return out as T;
}
