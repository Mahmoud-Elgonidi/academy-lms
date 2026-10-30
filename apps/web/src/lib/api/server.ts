import 'server-only';
import { cookies, headers } from 'next/headers';
import { notFound, redirect } from 'next/navigation';
import { getLocale } from 'next-intl/server';
import type { paths } from './schema';
import { CHOSEN_ZONE_COOKIE, SESSION_COOKIE, TIMEZONE_COOKIE } from '../session';

/** Laravel inside the stack (compose: http://web:8080); `php artisan serve` on a laptop. */
export const API = process.env.API_INTERNAL_URL ?? 'http://127.0.0.1:8000';

type Method = 'get' | 'post' | 'put' | 'patch' | 'delete';

/**
 * A path of the API (packages/openapi/openapi.json, `npm run api:types`) that
 * answers this method. A path or method the API no longer has fails the
 * typecheck, as the mobile app's contract test does for its calls.
 */
export type PathFor<M extends Method> = {
  [P in keyof paths]: [NonNullable<paths[P][M]>] extends [never] ? never : P;
}[keyof paths];

type Query = Record<string, string | number | boolean | null | undefined>;

type Options = {
  /** Values for the path's {placeholders}. */
  params?: Record<string, string>;
  query?: Query;
  body?: unknown;
  /** A multipart body instead (a file handed in); fetch sets its boundary. */
  form?: FormData;
  /** A token just issued, before the cookie holds it. */
  token?: string;
  /** Writes that move lessons or money: the same key for a retry of the same submit. */
  idempotencyKey?: string;
  /** More headers to pass on (a Range for a recording). */
  headers?: Record<string, string>;
};

/**
 * The request as this person, from the server only. The academy is the one
 * of the host the page was asked on: X-Forwarded-Host carries it, and the API
 * resolves it as it resolves its own pages (subdomain, custom domain, or
 * PLATFORM_HOME_ACADEMY). ACADEMY names one by slug on a laptop.
 */
async function outgoing(token?: string): Promise<Record<string, string>> {
  const [jar, incoming, locale] = await Promise.all([cookies(), headers(), getLocale()]);
  const sent: Record<string, string> = { Accept: 'application/json', 'Accept-Language': locale };

  const bearer = token ?? jar.get(SESSION_COOKIE)?.value;
  if (bearer) sent.Authorization = `Bearer ${bearer}`;
  // The zone chosen on this browser, else the device's.
  const zone = jar.get(CHOSEN_ZONE_COOKIE)?.value ?? jar.get(TIMEZONE_COOKIE)?.value;
  if (zone) sent['X-Timezone'] = decodeURIComponent(zone);
  const host = incoming.get('x-forwarded-host') ?? incoming.get('host');
  if (host) sent['X-Forwarded-Host'] = host;
  sent['X-Forwarded-Proto'] = incoming.get('x-forwarded-proto') ?? 'http';
  // The visitor's address, so the API's limits count each person, not the portal.
  const from = incoming.get('x-forwarded-for');
  if (from) sent['X-Forwarded-For'] = from;
  if (process.env.ACADEMY) sent['X-Academy'] = process.env.ACADEMY;
  return sent;
}

function urlOf(path: string, params?: Record<string, string>, query?: Query): string {
  const filled = path.replace(/\{(\w+)\}/g, (_, name: string) => encodeURIComponent(params?.[name] ?? ''));
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
  }
  const qs = search.toString();
  return `${API}/api${filled}${qs ? `?${qs}` : ''}`;
}

/** One call to the API, as this person. */
export async function call<M extends Method>(method: M, path: PathFor<M>, options: Options = {}): Promise<Response> {
  const sent = { ...(options.headers ?? {}), ...(await outgoing(options.token)) };
  if (options.body !== undefined) sent['Content-Type'] = 'application/json';
  if (options.idempotencyKey) sent['Idempotency-Key'] = options.idempotencyKey;
  return fetch(urlOf(path, options.params, options.query), {
    method: method.toUpperCase(),
    headers: sent,
    body: options.form ?? (options.body === undefined ? undefined : JSON.stringify(options.body)),
    cache: 'no-store',
    redirect: 'manual',
  });
}

/** The API's problem (RFC 7807): its type, the words to show, field errors, and any members of its own (two_factor_token…). */
export type Problem = { status: number; title: string; errors: Record<string, string[]>; type?: string; extra?: Record<string, unknown> };

async function problemOf(response: Response): Promise<Problem> {
  const body = (await response.json().catch(() => ({}))) as {
    type?: string;
    title?: string;
    detail?: string;
    message?: string;
    errors?: Record<string, string[]>;
  } & Record<string, unknown>;
  return {
    status: response.status,
    title: body.detail ?? body.title ?? body.message ?? `HTTP ${response.status}`,
    errors: body.errors ?? {},
    type: body.type,
    extra: body,
  };
}

/** A problem the page cannot show by itself: the error boundary takes it (and logs its status). */
export class ApiProblem extends Error {
  constructor(readonly problem: Problem) {
    super(`${problem.status} ${problem.title}`);
  }
}

/** A lapsed or refused session: forget it here, then sign in again. */
async function sessionEnded(): Promise<never> {
  redirect(`/bff/session-ended?locale=${await getLocale()}`);
}

/**
 * A read for a page: its data, or the person goes back to signing in (401),
 * a "not found" page (403, 404), or the page's error boundary.
 */
export async function get<T>(path: PathFor<'get'>, options: Omit<Options, 'body' | 'idempotencyKey'> = {}): Promise<T> {
  const response = await call('get', path, options);
  if (response.status === 401) return sessionEnded();
  if (response.status === 403 || response.status === 404) notFound();
  if (!response.ok) throw new ApiProblem(await problemOf(response));
  return (await response.json()) as T;
}

export type Outcome<T> = { ok: true; data: T } | { ok: false; problem: Problem };

/**
 * A read that may come back without an answer and leave the page as it is:
 * a part of a page only some records have (a lesson's mushaf), or a Server
 * Action's read for a client component. A lapsed session goes back to
 * signing in, as for every read.
 */
export async function read<T>(path: PathFor<'get'>, options: Omit<Options, 'body' | 'idempotencyKey' | 'form'> = {}): Promise<Outcome<T>> {
  let response: Response;
  try {
    response = await call('get', path, options);
  } catch {
    return { ok: false, problem: { status: 0, title: '', errors: {} } };
  }
  if (response.status === 401 && !options.token) return sessionEnded();
  if (!response.ok) return { ok: false, problem: await problemOf(response) };
  return { ok: true, data: (await response.json()) as T };
}

/**
 * A write from a Server Action: the answer, or the API's problem for the form
 * to show next to its fields. A lapsed session goes back to signing in.
 */
export async function send<T = unknown, M extends Exclude<Method, 'get'> = Exclude<Method, 'get'>>(
  method: M,
  path: PathFor<M>,
  options: Options = {},
): Promise<Outcome<T>> {
  let response: Response;
  try {
    response = await call(method, path, options);
  } catch {
    return { ok: false, problem: { status: 0, title: '', errors: {} } };
  }
  if (response.status === 401 && !options.token) return sessionEnded();
  if (!response.ok) return { ok: false, problem: await problemOf(response) };
  const text = await response.text();
  return { ok: true, data: (text ? JSON.parse(text) : null) as T };
}
