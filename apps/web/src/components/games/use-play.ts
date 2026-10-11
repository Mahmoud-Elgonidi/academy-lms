'use client';

import { useCallback, useRef, useState, useTransition } from 'react';
import { useTranslations } from 'next-intl';
import { finishRound, startRound } from '@/app/[locale]/family/games/actions';
import type { Answer, Finished, PlayTarget, Round } from '@/lib/games/types';

/** A round finished: what the API answered, and what to play again. */
export type Played = { result: Finished; target: PlayTarget; title: string; island: string };

type Playing = Round & { target: PlayTarget };

/**
 * Playing on any look's screen: start a round (the API checks the allowance
 * and the items), hand in what the game reported once (one key per round, so
 * a retry counts once), play the same again, or leave. A refusal is said in
 * words through `say`.
 */
export function usePlay(learnerId: string, say: (text: string) => void) {
  const t = useTranslations('games');
  const [round, setRound] = useState<Playing | null>(null);
  const [played, setPlayed] = useState<Played | null>(null);
  const [pending, begin] = useTransition();
  const keys = useRef(new Map<string, string>());

  const start = useCallback(
    (target: PlayTarget) => {
      begin(async () => {
        const started = await startRound(learnerId, target.game, { station: target.station ?? null, homework: target.homework ?? null });
        if (!started.ok) {
          say(started.type === 'games-not-enough-items' ? t('notEnough') : (started.message ?? t('failed')));
          return;
        }
        setPlayed(null);
        setRound({ ...started.round, target });
      });
    },
    [learnerId, say, t],
  );

  const finish = useCallback(
    (answers: Answer[]) => {
      const playing = round;
      if (!playing) return;
      const key = keys.current.get(playing.play_id) ?? crypto.randomUUID();
      keys.current.set(playing.play_id, key);
      begin(async () => {
        const done = await finishRound(playing.play_id, answers, key);
        setRound(null);
        if (!done.ok) {
          say(done.message ?? t('failed'));
          return;
        }
        setPlayed({ result: done.result, target: playing.target, title: playing.game.title, island: playing.game.island });
      });
    },
    [round, say, t],
  );

  const again = useCallback(() => {
    if (!played) return;
    // The best time before the next round: this one's, if it beat the old best.
    const times = [played.target.bestBefore, played.result.seconds].filter((s): s is number => typeof s === 'number');
    start({ ...played.target, bestBefore: times.length > 0 ? Math.min(...times) : null });
  }, [played, start]);

  const leave = useCallback(() => {
    setRound(null);
    setPlayed(null);
  }, []);

  return { round, played, pending, start, finish, again, leave };
}
