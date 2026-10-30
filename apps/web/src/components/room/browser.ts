/**
 * What this browser can do in the lesson room, read without throwing where
 * it cannot: picture-in-picture (a floating window of its own in Chrome and
 * Edge, else the video's own in Safari and Firefox, else none), sharing the
 * screen (not on phones), and the room's shortcuts.
 */

export type PipWay = 'document' | 'video' | 'none';

/** The floating window's first size (people can resize it). */
export const PIP_SIZE = { width: 360, height: 220 } as const;

type PipWindowApi = { requestWindow: (options: { width: number; height: number }) => Promise<Window>; window: Window | null };

/** Chrome's Document Picture-in-Picture, when there is one. */
export function documentPip(win: unknown): PipWindowApi | null {
  try {
    const api = (win as { documentPictureInPicture?: Partial<PipWindowApi> } | null | undefined)?.documentPictureInPicture;
    return api && typeof api.requestWindow === 'function' ? (api as PipWindowApi) : null;
  } catch {
    return null;
  }
}

/** Which picture-in-picture this browser offers. */
export function pipWay(win: unknown): PipWay {
  if (!win) return 'none';
  if (documentPip(win)) return 'document';
  try {
    const w = win as { document?: { pictureInPictureEnabled?: boolean }; HTMLVideoElement?: { prototype?: { requestPictureInPicture?: unknown } } };
    if (w.document?.pictureInPictureEnabled === true && typeof w.HTMLVideoElement?.prototype?.requestPictureInPicture === 'function') return 'video';
  } catch {
    // Reading the browser's parts never stops the room.
  }
  return 'none';
}

/** Whether the screen can be shared (getDisplayMedia: not on phones' browsers). */
export function canShareScreen(nav: unknown): boolean {
  try {
    const media = (nav as { mediaDevices?: { getDisplayMedia?: unknown } } | null | undefined)?.mediaDevices;
    return typeof media?.getDisplayMedia === 'function';
  } catch {
    return false;
  }
}

/** Apple's keyboards say ⌘ where others say Ctrl. */
export function isApple(platform: string): boolean {
  return /Mac|iPhone|iPad|iPod/i.test(platform);
}

export type Shortcut = 'mic' | 'camera';

/**
 * Ctrl (⌘ on a Mac) with D for the microphone and E for the camera, read by
 * the key's place so they work on an Arabic keyboard too.
 */
export function shortcutOf(event: { code: string; ctrlKey: boolean; metaKey: boolean; altKey: boolean; shiftKey: boolean }, apple: boolean): Shortcut | null {
  const modifier = apple ? event.metaKey && !event.ctrlKey : event.ctrlKey && !event.metaKey;
  if (!modifier || event.altKey || event.shiftKey) return null;
  if (event.code === 'KeyD') return 'mic';
  if (event.code === 'KeyE') return 'camera';
  return null;
}

/** How a shortcut is written in a tooltip. */
export function shortcutLabel(key: 'D' | 'E', apple: boolean): string {
  return apple ? `⌘${key}` : `Ctrl+${key}`;
}
