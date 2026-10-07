import type { NextRequest } from 'next/server';

/** This request's own address as the browser sees it (behind nginx and the web container). */
export function outside(request: NextRequest, path: string): URL {
  const proto = request.headers.get('x-forwarded-proto') ?? request.nextUrl.protocol.replace(':', '');
  const host = request.headers.get('x-forwarded-host') ?? request.headers.get('host') ?? request.nextUrl.host;
  return new URL(path, `${proto}://${host}`);
}
