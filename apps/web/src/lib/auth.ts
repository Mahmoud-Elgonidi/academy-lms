import 'server-only';
import { cookies, headers } from 'next/headers';
import { SESSION_COOKIE } from './session';

/** A short name for this browser on the person's device list ("Web · Chrome on Windows"). */
export function deviceName(userAgent: string | null): string {
  const ua = userAgent ?? '';
  const browser = /Edg\//.test(ua) ? 'Edge' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Browser';
  const system = /Windows/.test(ua) ? 'Windows' : /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Mac OS X/.test(ua) ? 'macOS' : /Linux/.test(ua) ? 'Linux' : '';
  return `Web · ${browser}${system ? ` on ${system}` : ''}`;
}

/** Keeps the token for this host, out of the page's reach, until it ends. */
export async function keepSession(token: string, expiresAt: string | null): Promise<void> {
  const secure = ((await headers()).get('x-forwarded-proto') ?? 'http') === 'https';
  const expires = new Date(expiresAt ?? NaN);
  (await cookies()).set(SESSION_COOKIE, token, {
    httpOnly: true,
    secure,
    sameSite: 'lax',
    path: '/',
    expires: Number.isNaN(expires.getTime()) ? undefined : expires,
  });
}

export async function forgetSession(): Promise<void> {
  (await cookies()).delete(SESSION_COOKIE);
}
