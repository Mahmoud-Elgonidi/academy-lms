import type { NextRequest } from 'next/server';
import { call } from '@/lib/api/server';

/**
 * A teacher's own vetting document: the API checks the token from the cookie
 * (only the teacher who sent it), and the bytes pass through.
 */
export async function GET(_request: NextRequest, { params }: { params: Promise<{ check: string }> }) {
  const { check } = await params;
  const response = await call('get', '/v1/teacher/checks/{check}/file', { params: { check } });
  if (!response.ok) {
    return new Response(null, { status: response.status === 401 || response.status === 403 ? 403 : 404 });
  }
  const headers: Record<string, string> = { 'Cache-Control': 'private, no-store', 'X-Content-Type-Options': 'nosniff' };
  for (const name of ['content-type', 'content-length', 'content-disposition']) {
    const value = response.headers.get(name);
    if (value) headers[name] = value;
  }
  return new Response(response.body, { status: 200, headers });
}
