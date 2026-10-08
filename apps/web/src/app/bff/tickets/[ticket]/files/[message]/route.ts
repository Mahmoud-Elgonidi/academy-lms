import type { NextRequest } from 'next/server';
import { call } from '@/lib/api/server';

/**
 * A picture or PDF on one of the person's tickets: the API checks the token
 * from the cookie (only the requester's own ticket, only lines they see),
 * and the bytes pass through.
 */
export async function GET(_request: NextRequest, { params }: { params: Promise<{ ticket: string; message: string }> }) {
  const { ticket, message } = await params;
  const response = await call('get', '/v1/support/tickets/{ticket}/messages/{message}/file', { params: { ticket, message } });
  if (!response.ok) {
    return new Response(null, { status: response.status === 401 || response.status === 403 ? 403 : 404 });
  }
  const headers: Record<string, string> = { 'Cache-Control': 'private, no-store' };
  for (const name of ['content-type', 'content-length', 'content-disposition']) {
    const value = response.headers.get(name);
    if (value) headers[name] = value;
  }
  return new Response(response.body, { status: 200, headers });
}
