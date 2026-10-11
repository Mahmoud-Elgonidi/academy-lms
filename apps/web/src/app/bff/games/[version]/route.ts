import type { NextRequest } from 'next/server';
import { API } from '@/lib/api/server';
import { isVersion } from '@/lib/games/player';

/**
 * A game's page (one published version, the platform's, the same for every
 * academy), on the portal's own origin so the family's frame may show it
 * (the page allows only same-origin framing). The bytes and the page's own
 * headers pass through as the API sends them: its sandbox policy (no
 * network, nothing from outside), no referrer, and a year's cache, since a
 * published version never changes.
 */
export async function GET(request: NextRequest, { params }: { params: Promise<{ version: string }> }) {
  const { version } = await params;
  if (!isVersion(version)) return new Response(null, { status: 404 });
  let response: Response;
  try {
    // The visitor's address, so the API's limit counts each person, not the portal.
    const from = request.headers.get('x-forwarded-for');
    response = await fetch(`${API}/games/${version}`, { headers: { Accept: 'text/html', ...(from ? { 'X-Forwarded-For': from } : {}) }, cache: 'no-store', redirect: 'manual' });
  } catch {
    return new Response(null, { status: 502 });
  }
  if (!response.ok) return new Response(null, { status: response.status === 429 ? 429 : 404 });
  // The portal's own set (next.config.ts) leaves this route to the game's policy; the rest of it is here.
  const passed: Record<string, string> = {
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'SAMEORIGIN',
    'Permissions-Policy': 'geolocation=(), usb=(), serial=(), hid=(), bluetooth=()',
  };
  for (const name of ['content-type', 'content-security-policy', 'referrer-policy', 'cross-origin-opener-policy', 'cache-control', 'etag', 'last-modified']) {
    const value = response.headers.get(name);
    if (value) passed[name] = value;
  }
  return new Response(response.body, { status: 200, headers: passed });
}
