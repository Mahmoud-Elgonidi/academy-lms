import { createHash, randomBytes } from 'node:crypto';
import { NextResponse, type NextRequest } from 'next/server';
import { routing } from '@/i18n/routing';
import { send } from '@/lib/api/server';
import { outside } from '@/lib/outside';
import { safeNext } from '@/lib/session';
import { SOCIAL_COOKIE } from '@/lib/social';

/**
 * Off to Google or Apple, to sign in or (signed in) to link the account: a
 * secret stays here in an httpOnly cookie, only its hash goes to the API,
 * and the ticket coming back works only with it.
 */
export async function GET(request: NextRequest, { params }: { params: Promise<{ provider: string }> }) {
  const { provider } = await params;
  const query = request.nextUrl.searchParams;
  const asked = query.get('locale') ?? '';
  const locale = (routing.locales as readonly string[]).includes(asked) ? asked : routing.defaultLocale;
  const link = query.get('link') === '1';
  const back = safeNext(query.get('back'), locale);

  const secret = randomBytes(30).toString('base64url');
  const bind = createHash('sha256').update(secret).digest('hex');
  const started = await send<{ url: string }>('post', link ? '/v1/me/social/{provider}/start' : '/v1/auth/social/{provider}/start', {
    params: { provider },
    body: { bind, locale },
  });
  if (!started.ok) {
    return NextResponse.redirect(outside(request, link && back ? `${back}?social=off` : `/${locale}/sign-in?social=off&provider=${encodeURIComponent(provider)}`));
  }

  const response = NextResponse.redirect(started.data.url);
  response.cookies.set(SOCIAL_COOKIE, JSON.stringify({ secret, back }), {
    httpOnly: true,
    secure: (request.headers.get('x-forwarded-proto') ?? request.nextUrl.protocol.replace(':', '')) === 'https',
    sameSite: 'lax',
    path: '/bff/social',
    maxAge: 600,
  });
  return response;
}
