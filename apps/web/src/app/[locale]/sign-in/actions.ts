'use server';

import { headers } from 'next/headers';
import { redirect } from 'next/navigation';
import { getLocale } from 'next-intl/server';
import { send, type Problem } from '@/lib/api/server';
import { deviceName, forgetSession, keepSession } from '@/lib/auth';
import { homeOf, safeNext } from '@/lib/session';
import { formProblem } from '@/lib/problems';
import { twoFactorTurn } from '@/lib/two-factor';
import type { LocalTime } from '@/lib/time';

export type SignInState = {
  step: 'contact' | 'code' | 'two-factor';
  /** The second step's token, when the account has two-step sign-in on (auth/two-factor). */
  twoFactorToken?: string;
  contact?: string;
  challengeId?: string;
  sentTo?: string;
  resendAfter?: number;
  error?: string;
  staff?: boolean;
};

type Signed = { token: string; expires_at: LocalTime | null; user: { roles: string[] } };

/** Two-step sign-in: the authenticator's code comes next, or the back office for a role that must set it up there. */
function secondStep(problem: Problem, contact?: string): SignInState | null {
  const turn = twoFactorTurn(problem);
  if (turn?.step === 'back-office') return { step: 'contact', staff: true };
  return turn ? { step: 'two-factor', twoFactorToken: turn.twoFactorToken, contact } : null;
}

/** The API's own words for what went wrong (a field's message before the general title). */
function said(problem: Problem): string | undefined {
  return formProblem({ message: problem.title, errors: problem.errors }, []);
}

async function finish(signed: Signed, next: FormDataEntryValue | null): Promise<SignInState> {
  const locale = await getLocale();
  const home = homeOf(signed.user.roles);
  if (home === 'staff') {
    // The back office has its own sign-in; this token is not needed.
    await send('post', '/v1/auth/logout', { token: signed.token });
    return { step: 'contact', staff: true };
  }
  await keepSession(signed.token, signed.expires_at?.utc ?? null);
  redirect(safeNext(typeof next === 'string' ? next : null, locale) ?? `/${locale}/${home}`);
}

async function device(): Promise<string> {
  return deviceName((await headers()).get('user-agent'));
}

/** Sends a one-time code to an email or number of this academy (the API answers the same whether or not it has the account). */
export async function sendCode(_: SignInState, form: FormData): Promise<SignInState> {
  const contact = String(form.get('contact') ?? '').trim();
  const sent = await send<{ challenge_id: string; sent_to: string; resend_after: number }>('post', '/v1/auth/code', { body: { contact } });
  if (!sent.ok) return { step: 'contact', contact, error: said(sent.problem) };
  const { challenge_id, sent_to, resend_after } = sent.data;
  return { step: 'code', contact, challengeId: challenge_id, sentTo: sent_to, resendAfter: resend_after };
}

/** Checks the code against the challenge the form carries (the one sendCode just made). */
export async function verifyCode(_: SignInState, form: FormData): Promise<SignInState> {
  const challenge = challengeOf(form.get('challenge'));
  const code = String(form.get('code') ?? '').replace(/\s/g, '');
  const signed = await send<Signed>('post', '/v1/auth/code/verify', {
    body: { challenge_id: challenge.challengeId ?? '', code, device_name: await device() },
  });
  if (!signed.ok) return secondStep(signed.problem, challenge.contact) ?? { step: 'code', ...challenge, error: said(signed.problem) };
  return finish(signed.data, form.get('next'));
}

/** The code the authenticator app shows now (or a recovery code), for the sign-in waiting on it. */
export async function verifyTwoFactor(_: SignInState, form: FormData): Promise<SignInState> {
  const token = String(form.get('token') ?? '');
  const code = String(form.get('code') ?? '').trim();
  const signed = await send<Signed>('post', '/v1/auth/two-factor', {
    body: { two_factor_token: token, code: /^[\d\s]+$/.test(code) ? code.replace(/\s/g, '') : code, device_name: await device() },
  });
  if (!signed.ok) {
    // Too late or too many tries: back to the start.
    if (signed.problem.errors.two_factor_token) return { step: 'contact', error: said(signed.problem) };
    return { step: 'two-factor', twoFactorToken: token, error: said(signed.problem) };
  }
  return finish(signed.data, form.get('next'));
}

function challengeOf(value: FormDataEntryValue | null): Pick<SignInState, 'challengeId' | 'contact'> {
  try {
    const parsed = JSON.parse(typeof value === 'string' ? value : '{}') as { challengeId?: unknown; contact?: unknown };
    return {
      challengeId: typeof parsed.challengeId === 'string' ? parsed.challengeId : undefined,
      contact: typeof parsed.contact === 'string' ? parsed.contact : undefined,
    };
  } catch {
    return {};
  }
}

export async function signInWithPassword(_: SignInState, form: FormData): Promise<SignInState> {
  const login = String(form.get('login') ?? '').trim();
  const signed = await send<Signed>('post', '/v1/auth/login', {
    body: { login, password: String(form.get('password') ?? ''), device_name: await device() },
  });
  if (!signed.ok) return secondStep(signed.problem, login) ?? { step: 'contact', contact: login, error: said(signed.problem) };
  return finish(signed.data, form.get('next'));
}

/** Ends the token on the API (best effort) and forgets it here. */
export async function signOut(): Promise<void> {
  await send('post', '/v1/auth/logout');
  await forgetSession();
  redirect(`/${await getLocale()}/sign-in`);
}
