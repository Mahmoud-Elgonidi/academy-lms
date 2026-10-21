import createMiddleware from 'next-intl/middleware';
import { NextRequest, NextResponse } from 'next/server';
import { routing } from './i18n/routing';
import { newNonce, NONCE_HEADER, pagePolicy } from './lib/csp';
import { outside } from './lib/outside';
import { SESSION_COOKIE } from './lib/session';

const intl = createMiddleware(routing);

/**
 * Every portal page needs a session, except signing in and the public pages
 * (the locale's root and /platform, whose own page decides); then next-intl
 * keeps the locale in the path. The API still checks the token on each call.
 * Each page gets a fresh nonce: in the request's policy, where Next.js finds
 * it for its own scripts, and in the answer's (lib/csp.ts).
 */
export default function proxy(incoming: NextRequest) {
  const nonce = newNonce();
  const policy = pagePolicy(nonce, process.env.NODE_ENV === 'development');
  const headers = new Headers(incoming.headers);
  headers.set(NONCE_HEADER, nonce);
  headers.set('Content-Security-Policy', policy);
  // next-intl reads the path, cookies and headers; the body (a Server Action's) stays untouched.
  const request = new NextRequest(incoming.url, { headers, method: incoming.method });

  let response: NextResponse | undefined;
  const match = request.nextUrl.pathname.match(/^\/(ar|en)(\/.*)?$/);
  if (match) {
    const [, locale, rest = '/'] = match;
    // The public pages: the academy's (or the platform's) at the locale's root, and the platform's own.
    const open = rest.startsWith('/sign-in') || rest === '/' || rest === '/platform' || rest.startsWith('/platform/');
    if (!open && !request.cookies.has(SESSION_COOKIE)) {
      const target = outside(request, `/${locale}/sign-in`);
      target.searchParams.set('next', request.nextUrl.pathname + request.nextUrl.search);
      response = NextResponse.redirect(target);
    }
  }
  response ??= intl(request);
  response.headers.set('Content-Security-Policy', policy);
  return response;
}

export const config = { matcher: ['/', '/(ar|en)', '/(ar|en)/:path*'] };
