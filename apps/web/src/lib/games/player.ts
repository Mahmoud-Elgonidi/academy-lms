/**
 * The page's side of a game (docs/architecture/02, section 18), the parts
 * that are plain logic: where the frame opens and what it may say. A game
 * runs in <iframe sandbox="allow-scripts"> (a unique origin, no cookies, no
 * way to this page) and talks only through postMessage, as
 * apps/api/modules/Platform/resources/games/bridge.js expects. The round
 * comes from the API, never from the game; what the game reports is kept
 * only from that frame, and an answer only for an item of this round.
 */

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/** A published version's id (what the game's page is served by). */
export function isVersion(value: string): boolean {
  return UUID.test(value);
}

/** The frame's address on the portal's own origin, from the API's page_url (…/games/{version}); null when it names nothing else. */
export function frameSrc(pageUrl: string): string | null {
  let path: string;
  try {
    path = new URL(pageUrl, 'http://portal.invalid').pathname;
  } catch {
    return null;
  }
  const version = /^\/games\/([^/]+)$/.exec(path)?.[1];
  return version && isVersion(version) ? `/bff/games/${version}` : null;
}

/** What the frame may tell the page. */
export type GameMessage = { type: 'ready' } | { type: 'answer'; item: string; correct: boolean } | { type: 'finish' } | { type: 'error'; message: string; line: number };

/**
 * A message from the game, or null: only the frame's own window, only the
 * bridge's JSON ({lms: 1, type}), and an answer only for this round's items.
 */
export function readMessage(event: { source: unknown; data: unknown }, frame: unknown, items: ReadonlySet<string>): GameMessage | null {
  if (!frame || event.source !== frame || typeof event.data !== 'string') return null;
  let message: Record<string, unknown>;
  try {
    message = JSON.parse(event.data) as Record<string, unknown>;
  } catch {
    return null;
  }
  if (!message || typeof message !== 'object' || message.lms !== 1) return null;
  switch (message.type) {
    case 'ready':
      return { type: 'ready' };
    case 'answer':
      return items.has(String(message.item)) ? { type: 'answer', item: String(message.item), correct: message.correct === true } : null;
    case 'finish':
      return { type: 'finish' };
    case 'error':
      return { type: 'error', message: String(message.message ?? '').slice(0, 300), line: Number(message.line) || 0 };
    default:
      return null;
  }
}

/** What the page hands the game when it is ready, and when the sound is turned on or off. */
export function startMessage(round: Record<string, unknown>, muted: boolean): string {
  return JSON.stringify({ lms: 1, type: 'start', round: { ...round, muted } });
}

export function muteMessage(muted: boolean): string {
  return JSON.stringify({ lms: 1, type: 'mute', muted });
}
