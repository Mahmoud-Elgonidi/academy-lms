'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { PIP_SIZE, documentPip, pipWay, type PipWay } from './browser';

export type Pip = {
  way: PipWay;
  /** The floating window while it is open (Chrome's Document Picture-in-Picture). */
  window: Window | null;
  /** Whether a picture-in-picture of either kind is showing. */
  open: boolean;
  enter: (auto?: boolean) => Promise<void>;
  exit: () => void;
};

/**
 * Picture-in-picture for the room: a floating window of its own (the main
 * video with the mic and leave buttons, drawn into it through a portal, the
 * page's styles copied in), else the browser's own on the main video. It
 * opens from the button, and by itself when the person leaves the tab while
 * connected: Chrome asks through the media session ("enterpictureinpicture"),
 * and leaving the tab tries too (the browser may refuse without a click).
 * One the room opened by itself closes when the person comes back. Nothing
 * here throws where a browser has none of it.
 */
export function usePictureInPicture({ active, video }: { active: boolean; video: () => HTMLVideoElement | null }): Pip {
  const [way] = useState<PipWay>(() => (typeof window === 'undefined' ? 'none' : pipWay(window)));
  const [win, setWin] = useState<Window | null>(null);
  const [videoOpen, setVideoOpen] = useState(false);
  const auto = useRef(false);
  const opening = useRef(false);
  const videoRef = useRef(video);
  useEffect(() => {
    videoRef.current = video;
  }, [video]);

  const enter = useCallback(
    async (byItself = false) => {
      if (opening.current) return;
      opening.current = true;
      try {
        const api = documentPip(window);
        if (way === 'document' && api) {
          if (api.window) return;
          const next = await api.requestWindow(PIP_SIZE);
          dressUp(document, next.document);
          next.addEventListener('pagehide', () => {
            auto.current = false;
            setWin(null);
          });
          auto.current = byItself;
          setWin(next);
          return;
        }
        if (way === 'video') {
          const el = videoRef.current();
          if (!el || document.pictureInPictureElement === el) return;
          await el.requestPictureInPicture();
          auto.current = byItself;
          setVideoOpen(true);
          el.addEventListener(
            'leavepictureinpicture',
            () => {
              auto.current = false;
              setVideoOpen(false);
            },
            { once: true },
          );
        }
      } catch {
        // Refused (no click to go with it) or not offered: the room stays as it is.
      } finally {
        opening.current = false;
      }
    },
    [way],
  );

  const exit = useCallback(() => {
    auto.current = false;
    closeAll();
    setWin(null);
    setVideoOpen(false);
  }, []);

  // Leaving the tab while connected: the media session's own way first, then a try on hiding.
  useEffect(() => {
    if (!active || way === 'none') return;
    const session = typeof navigator !== 'undefined' ? navigator.mediaSession : undefined;
    try {
      session?.setActionHandler('enterpictureinpicture' as MediaSessionAction, () => void enter(true));
    } catch {
      // This browser does not know the action.
    }
    const onVisibility = () => {
      if (document.visibilityState === 'hidden') void enter(true);
      else if (auto.current) exit();
    };
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      document.removeEventListener('visibilitychange', onVisibility);
      try {
        session?.setActionHandler('enterpictureinpicture' as MediaSessionAction, null);
      } catch {
        // As above.
      }
    };
  }, [active, way, enter, exit]);

  // Out of the room: no floating window left behind.
  useEffect(() => closeAll, []);

  return { way, window: win, open: win !== null || videoOpen, enter, exit };
}

function closeAll() {
  try {
    documentPip(window)?.window?.close();
    if (document.pictureInPictureElement) void document.exitPictureInPicture().catch(() => undefined);
  } catch {
    // Already closed.
  }
}

/**
 * The floating window takes the page's look: its stylesheets (rules copied,
 * or linked when the browser keeps them to itself), the academy's colours
 * and fonts set on <html>, its language and direction.
 */
function dressUp(from: Document, to: Document) {
  for (const sheet of Array.from(from.styleSheets)) {
    try {
      const style = to.createElement('style');
      style.textContent = Array.from(sheet.cssRules)
        .map((rule) => rule.cssText)
        .join('\n');
      to.head.appendChild(style);
    } catch {
      if (sheet.href) {
        const link = to.createElement('link');
        link.rel = 'stylesheet';
        link.href = sheet.href;
        to.head.appendChild(link);
      }
    }
  }
  const html = from.documentElement;
  to.documentElement.lang = html.lang;
  to.documentElement.dir = html.dir;
  to.documentElement.className = html.className;
  const style = html.getAttribute('style');
  if (style) to.documentElement.setAttribute('style', style);
  to.body.className = `${from.body.className} room-pip`;
  to.title = from.title;
}
