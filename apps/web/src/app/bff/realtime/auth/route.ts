import type { NextRequest } from 'next/server';
import { call } from '@/lib/api/server';

/**
 * The live socket's permission to listen on the person's own channel: the
 * page sends the socket and channel, the API signs them with the token from
 * the cookie (it refuses anyone else's channel). Only from the portal's own
 * pages.
 */
export async function POST(request: NextRequest) {
  const origin = request.headers.get('origin');
  const host = request.headers.get('x-forwarded-host') ?? request.headers.get('host');
  if (origin && host && new URL(origin).host !== host) return new Response(null, { status: 403 });

  const asked = (await request.json().catch(() => null)) as { socket_id?: unknown; channel_name?: unknown } | null;
  if (typeof asked?.socket_id !== 'string' || typeof asked.channel_name !== 'string') return new Response(null, { status: 422 });

  const response = await call('post', '/v1/realtime/auth', { body: { socket_id: asked.socket_id, channel_name: asked.channel_name } });
  if (!response.ok) return new Response(null, { status: response.status === 401 ? 401 : 403 });
  const signed = (await response.json()) as { auth: string };
  return Response.json({ auth: signed.auth }, { headers: { 'Cache-Control': 'private, no-store' } });
}
