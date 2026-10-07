import { NextResponse, type NextRequest } from 'next/server';
import { routing } from '@/i18n/routing';
import { outside } from '@/lib/outside';
import { SESSION_COOKIE } from '@/lib/session';

/**
 * The API refused the session (it ended, or was signed out on another
 * device): the cookie goes, and the person signs in again. Pages cannot
 * change cookies while they render, so they come here first.
 */
export function GET(request: NextRequest) {
  const asked = request.nextUrl.searchParams.get('locale') ?? '';
  const locale = (routing.locales as readonly string[]).includes(asked) ? asked : routing.defaultLocale;
  const response = NextResponse.redirect(outside(request, `/${locale}/sign-in?expired=1`));
  response.cookies.delete(SESSION_COOKIE);
  return response;
}
