/**
 * Which scripts a portal page may run, as on the API's pages
 * (apps/api/modules/SharedKernel/src/Http/ContentSecurityPolicy.php): those
 * carrying this request's nonce, and what they load ('strict-dynamic': the
 * page's chunks). Next.js puts the nonce on its own scripts when it finds it
 * in the request's policy (proxy.ts); a script the portal writes itself takes
 * it from the x-nonce header. A script slipped into the page does not run.
 */

/** What every answer carries (next.config.ts sends it on the rest). */
export const BASE_POLICY = "base-uri 'self'; object-src 'none'; frame-ancestors 'self'";

export const NONCE_HEADER = 'x-nonce';

export function newNonce(): string {
  return btoa(crypto.randomUUID());
}

/** A page's policy; in development React also needs eval for its error overlay. */
export function pagePolicy(nonce: string, development = false): string {
  return `${BASE_POLICY}; script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${development ? " 'unsafe-eval'" : ''}`;
}
