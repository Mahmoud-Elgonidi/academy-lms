/**
 * The portal's live updates, the parts that are plain logic (the client
 * component in components/live-refresh.tsx uses them): what the API's
 * realtime answer looks like, the signals a page refreshes on, the socket's
 * settings, and the next moment the clock alone changes a page.
 */

/** GET /v1/realtime: where to listen (enabled false when the server does not broadcast). */
export type Realtime = { enabled: boolean; key: string | null; host: string | null; port: number | null; tls: boolean; channel: string };

/**
 * The person's own signals (no data): the page asks the API again for what it
 * shows. The journey follows lessons (a lesson's stars, the next one),
 * homework (missions, the week's chest) and practice (rounds, the egg,
 * creatures, a guardian's limits); the hifz map and its daily review follow
 * hifz (a page reviewed, marked, or recited and then heard); a lesson's
 * shared mushaf follows mushaf (its page turned, a word marked).
 */
export const SIGNALS = ['lessons.changed', 'homework.changed', 'inbox.changed', 'payments.changed', 'consents.changed', 'tickets.changed', 'privacy.changed', 'concerns.changed', 'checks.changed', 'messages.changed', 'practice.changed', 'hifz.changed', 'mushaf.changed'] as const;

export type Signal = (typeof SIGNALS)[number];

/**
 * Signals a screen follows by itself, reading its own part again, so the page
 * is not refreshed for them: the shared mushaf turns its pages many times a
 * lesson. A page that shows what they change refreshes itself (RefreshOn).
 */
export const OWN_SIGNALS: readonly Signal[] = ['mushaf.changed'];

/** Whether a signal refreshes the whole page (LiveRefresh), or only the screens that follow it. */
export function refreshesPage(signal: string): boolean {
  return !OWN_SIGNALS.includes(signal as Signal);
}

/**
 * Every signal heard is also told to the page's own scripts, as a window
 * event whose detail is its name, so a client component can read its own
 * state again (useSignal). `resync` says "catch up on everything": the relay
 * started again, the socket came back, or the slow timer refreshed the page.
 */
export const SIGNAL_EVENT = 'lms:signal';
export const RESYNC = 'resync';

/** Whether a screen that follows these signals acts on this one (a resync is for every screen). */
export function concerns(signal: unknown, followed: readonly string[]): boolean {
  return typeof signal === 'string' && (signal === RESYNC || followed.includes(signal));
}

/** The relay's public channel: "alive" every minute, "resync" when it starts (every open screen catches up). */
export const RELAY = { channel: 'lms.live', alive: 'alive', resync: 'resync' } as const;

/** Without a socket, or with the relay quiet this long, the page refreshes on a slow timer, as the panels do. */
export const QUIET_MS = 150_000;
export const FALLBACK_MS = 60_000;

/** Whether the page should refresh by the clock now: nothing is telling it about changes, and the last refresh is a while ago. */
export function needsFallback(state: { connected: boolean; lastHeard: number; lastRefresh: number }, now: number): boolean {
  const deaf = !state.connected || now - state.lastHeard > QUIET_MS;
  return deaf && now - state.lastRefresh >= FALLBACK_MS;
}

/** The socket's settings for pusher-js: Reverb on the page's own host unless the API names another. */
export function socketOptions(realtime: Realtime, page: { hostname: string; protocol: string }) {
  const tls = realtime.tls;
  const port = realtime.port ?? (tls ? 443 : 80);
  return {
    cluster: '',
    wsHost: realtime.host ?? page.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: tls,
    enabledTransports: ['ws', 'wss'] as ('ws' | 'wss')[],
    disableStats: true,
  };
}

/** The room opens 10 minutes before a lesson and closes 10 minutes after it ends (Classroom). */
const ROOM_CLOSES_AFTER_MS = 10 * 60_000;

type Timed = { starts_at: { utc: string }; ends_at: { utc: string }; join?: { open: boolean; opens_at: { utc: string } | null } };

/** The moments the clock alone changes what these lessons show: the room opening, the lesson starting, the room closing. */
export function momentsOf(lessons: Timed[]): string[] {
  return lessons.flatMap((l) => [l.join?.opens_at?.utc, l.starts_at.utc, new Date(Date.parse(l.ends_at.utc) + ROOM_CLOSES_AFTER_MS).toISOString()].filter((m): m is string => !!m));
}

/** The longest wait set at once; a page open for days sets the next one then. */
const LONGEST_WAIT_MS = 6 * 60 * 60_000;

/** How long until the soonest of these moments that is still ahead (and a breath after it), or null. */
export function waitUntilNext(moments: readonly string[], now: number): number | null {
  const ahead = moments.map((m) => Date.parse(m)).filter((t) => Number.isFinite(t) && t > now);
  if (ahead.length === 0) return null;
  return Math.min(Math.min(...ahead) - now + 2_000, LONGEST_WAIT_MS);
}
