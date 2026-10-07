import createMiddleware from 'next-intl/middleware';
import { NextResponse, type NextRequest } from 'next/server';
import { routing } from './i18n/routing';
import { outside } from './lib/outside';
import { SESSION_COOKIE } from './lib/session';

const intl = createMiddleware(routing);

/**
 * Every portal page needs a session, except signing in; then next-intl
 * keeps the locale in the path. The API still checks the token on each call.
 */
export default function proxy(request: NextRequest) {
  const match = request.nextUrl.pathname.match(/^\/(ar|en)(\/.*)?$/);
  if (match) {
    const [, locale, rest = '/'] = match;
    const open = rest.startsWith('/sign-in');
    if (!open && !request.cookies.has(SESSION_COOKIE)) {
      const target = outside(request, `/${locale}/sign-in`);
      target.searchParams.set('next', request.nextUrl.pathname + request.nextUrl.search);
      return NextResponse.redirect(target);
    }
  }
  return intl(request);
}

export const config = { matcher: ['/', '/(ar|en)', '/(ar|en)/:path*'] };
