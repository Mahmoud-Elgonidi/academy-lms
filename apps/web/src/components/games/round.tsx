'use client';

import { useEffect, useMemo, useRef } from 'react';
import { useTranslations } from 'next-intl';
import { frameSrc, muteMessage, readMessage, startMessage } from '@/lib/games/player';
import type { Answer, Round } from '@/lib/games/types';
import { hex } from '@/lib/games/art';

/**
 * A round, full screen over the world: the game's page in a sandboxed frame
 * (allow-scripts only: a unique origin, no cookies, no way to this page),
 * handed the round from the API when it says it is ready. Every try it
 * reports for an item of this round is kept, and handed in when it finishes.
 */
export function GameRound({ round, sky, look, muted, onFinish, onClose }: {
  round: Round;
  /** The island's sky around the frame. */
  sky?: string;
  look: string;
  muted: boolean;
  onFinish: (answers: Answer[]) => void;
  onClose: () => void;
}) {
  const t = useTranslations('games');
  const frame = useRef<HTMLIFrameElement>(null);
  const close = useRef<HTMLButtonElement>(null);
  const src = frameSrc(round.page_url);
  const ids = useMemo(() => new Set(round.round.items.map((item) => String(item.id))), [round]);
  // What the frame reports, and the hand-ins, belong to this round only; the latest callbacks are read when they come.
  const answers = useRef<Answer[]>([]);
  const finished = useRef(false);
  const started = useRef(false);
  const latest = useRef({ onFinish, muted });
  useEffect(() => {
    latest.current = { onFinish, muted };
  });

  useEffect(() => {
    answers.current = [];
    finished.current = false;
    started.current = false;
    const onMessage = (event: MessageEvent) => {
      const win = frame.current?.contentWindow;
      const message = readMessage(event, win, ids);
      if (!message || !win) return;
      if (message.type === 'ready' && !started.current) {
        started.current = true;
        win.postMessage(startMessage(round.round, latest.current.muted), '*');
      } else if (message.type === 'answer') {
        answers.current.push({ item: message.item, correct: message.correct });
      } else if (message.type === 'finish' && !finished.current) {
        finished.current = true;
        latest.current.onFinish(answers.current.slice());
      }
    };
    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [round, ids]);

  useEffect(() => {
    if (started.current) frame.current?.contentWindow?.postMessage(muteMessage(muted), '*');
  }, [muted]);

  useEffect(() => {
    close.current?.focus({ preventScroll: true });
    const html = document.documentElement;
    const was = html.style.overflow;
    html.style.overflow = 'hidden';
    return () => {
      html.style.overflow = was;
    };
  }, []);

  const what = round.focus?.title ? t('practising', { what: round.focus.title }) : round.game.how_to_play;
  return (
    <div className="gw-play gw-kids" data-look={look} style={{ ['--gw-sky' as string]: hex(sky, '#8ED8FF') }} role="dialog" aria-modal="true" aria-labelledby="gw-play-title">
      <header className="gw-play-bar">
        <button ref={close} type="button" className="gw-round-btn gw-3d" onClick={onClose} aria-label={t('map.close')}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="3.2" strokeLinecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
        <div>
          <strong id="gw-play-title">{round.game.title}</strong>
          <small>{what}</small>
        </div>
      </header>
      <div className="gw-frame">
        {src ? <iframe ref={frame} src={src} title={round.game.title} sandbox="allow-scripts" referrerPolicy="no-referrer" className="lms-game-frame" /> : null}
      </div>
    </div>
  );
}
