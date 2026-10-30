import { NextResponse, type NextRequest } from 'next/server';
import { routing } from '@/i18n/routing';
import { send } from '@/lib/api/server';
import { deviceName } from '@/lib/auth';
import { outside } from '@/lib/outside';
import { CHOSEN_ZONE_COOKIE, SESSION_COOKIE, homeOf, safeNext } from '@/lib/session';
import { SOCIAL_COOKIE, TWO_FACTOR_COOKIE, refusalOf } from '@/lib/social';
import type { LocalTime } from '@/lib/time';
import { twoFactorTurn } from '@/lib/two-factor';

type Signed = { token: string; expires_at: LocalTime | null; user: { roles: string[] } };

/**
 * Back from Google or Apple (through the platform's callback): the ticket and
 * this browser's secret make a session (or link the account of the person
 * signed in). The authenticator's code, when two-step sign-in is on, is
 * asked on the sign-in page; staff go to the back office.
 */
export async function GET(request: NextRequest) {
  const query = request.nextUrl.searchParams;
  const asked = query.get('locale') ?? '';
  const locale = (routing.locales as readonly string[]).includes(asked) ? asked : routing.defaultLocale;
  const provider = encodeURIComponent(query.get('provider') ?? '');
  const linking = query.get('intent') === 'link';
  const saved = savedOf(request.cookies.get(SOCIAL_COOKIE)?.value);
  const secure = (request.headers.get('x-forwarded-proto') ?? request.nextUrl.protocol.replace(':', '')) === 'https';

  const to = (path: string) => {
    const response = NextResponse.redirect(outside(request, path));
    response.cookies.delete({ name: SOCIAL_COOKIE, path: '/bff/social' });
    return response;
  };
  const back = safeNext(saved?.back, locale);
  const fail = (outcome: string) => to(linking && back ? `${back}?social=${outcome}` : `/${locale}/sign-in?social=${outcome}&provider=${provider}`);

  const error = query.get('error');
  if (error) return fail(error === 'denied' ? 'denied' : 'failed');
  const ticket = query.get('ticket');
  if (!saved || !ticket) return fail('expired');

  if (linking) {
    const linked = await send('post', '/v1/me/social/ticket', { body: { ticket, bind: saved.secret } });
    if (!linked.ok) return fail(linked.problem.type === 'social-ticket-refused' ? 'expired' : 'taken');
    return to(`${back ?? `/${locale}`}?social=linked`);
  }

  const signed = await send<Signed>('post', '/v1/auth/social/ticket', {
    body: { ticket, bind: saved.secret, device_name: deviceName(request.headers.get('user-agent')) },
  });
  if (!signed.ok) {
    const turn = twoFactorTurn(signed.problem);
    if (turn?.step === 'back-office') return to(`/${locale}/sign-in?staff=1`);
    if (turn?.step === 'two-factor') {
      // The sign-in page asks the authenticator's code, with this token (for a few minutes).
      const response = to(`/${locale}/sign-in?step=two-factor`);
      response.cookies.set(TWO_FACTOR_COOKIE, turn.twoFactorToken, { httpOnly: true, secure, sameSite: 'lax', path: '/', maxAge: 300 });
      return response;
    }
    return fail(refusalOf(signed.problem.type));
  }

  const home = homeOf(signed.data.user.roles);
  if (home === 'staff') {
    // The back office has its own sign-in; this token is not needed.
    await send('post', '/v1/auth/logout', { token: signed.data.token });
    return to(`/${locale}/sign-in?staff=1`);
  }
  const response = to(back ?? `/${locale}/${home}`);
  const expires = new Date(signed.data.expires_at?.utc ?? NaN);
  response.cookies.delete(CHOSEN_ZONE_COOKIE);
  response.cookies.set(SESSION_COOKIE, signed.data.token, {
    httpOnly: true,
    secure,
    sameSite: 'lax',
    path: '/',
    expires: Number.isNaN(expires.getTime()) ? undefined : expires,
  });
  return response;
}

function savedOf(value: string | undefined): { secret: string; back: string | null } | null {
  try {
    const parsed = JSON.parse(value ?? '') as { secret?: unknown; back?: unknown };
    return typeof parsed.secret === 'string' && parsed.secret !== '' ? { secret: parsed.secret, back: typeof parsed.back === 'string' ? parsed.back : null } : null;
  } catch {
    return null;
  }
}
