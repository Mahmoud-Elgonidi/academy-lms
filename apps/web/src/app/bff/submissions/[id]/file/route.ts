import type { NextRequest } from 'next/server';
import { call } from '@/lib/api/server';

/**
 * A file handed in with homework (a recording, a picture, a PDF), for the
 * signed-in teacher's page: the API checks the token from the cookie, and
 * the bytes pass through. Recordings seek, so a Range asked is passed on.
 */
export async function GET(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const response = await call('get', '/v1/learning/submissions/{submission}/file', {
    params: { submission: id },
    headers: pick(request.headers, ['range']),
  });
  if (!response.ok && response.status !== 206) {
    return new Response(null, { status: response.status === 401 || response.status === 403 ? 403 : 404 });
  }
  const passed = pick(response.headers, ['content-type', 'content-length', 'content-range', 'accept-ranges', 'content-disposition', 'last-modified', 'etag']);
  return new Response(response.body, { status: response.status, headers: { ...passed, 'Cache-Control': 'private, no-store' } });
}

function pick(from: Headers, names: string[]): Record<string, string> {
  const out: Record<string, string> = {};
  for (const name of names) {
    const value = from.get(name);
    if (value) out[name] = value;
  }
  return out;
}
