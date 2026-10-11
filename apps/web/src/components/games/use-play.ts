'use client';

import { useCallback, useEffect, useRef, useState, useTransition } from 'react';
import { useTranslations } from 'next-intl';
import { finishRound, startRound } from '@/app/[locale]/family/games/actions';
import type { Answer, Finished, Quest, Round } from '@/lib/games/types';

/** A round finished: what the API answered, what to play again, and the quest's best time before it (the race). */
export type Played = { result: Finished; game: string; quest: string | null; title: string; island: string; bestBefore: number | null };

type Playing = Round & { quest: string | null; bestBefore: number | null };

/**
 * Playing on any look's screen: start a round (the API checks the allowance
 * and the items), hand in what the game reported once (one key per round, so
 * a retry counts once), play the same again, or leave. A refusal is said in
 * words through `say`.
 */
export function usePlay(learnerId: string, quests: readonly Quest[], say: (text: string) => void) {
  const t = useTranslations('games');
  const [round, setRound] = useState<Playing | null>(null);
  const [played, setPlayed] = useState<Played | null>(null);
  const [pending, begin] = useTransition();
  const keys = useRef(new Map<string, string>());
  // The world as it is now (it refreshes after every round), for the quest's best time.
  const now = useRef(quests);
  useEffect(() => {
    now.current = quests;
  }, [quests]);

  const start = useCallback(
    (game: string, quest: string | null) => {
      const bestBefore = quest ? (now.current.find((q) => q.key === quest)?.best_seconds ?? null) : null;
      begin(async () => {
        const started = await startRound(learnerId, game, quest);
        if (!started.ok) {
          say(started.type === 'games-not-enough-items' ? t('notEnough') : (started.message ?? t('failed')));
          return;
        }
        setPlayed(null);
        setRound({ ...started.round, quest, bestBefore });
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
        setPlayed({ result: done.result, game: playing.game.id, quest: playing.quest, title: playing.game.title, island: playing.game.island, bestBefore: playing.bestBefore });
      });
    },
    [round, say, t],
  );

  const again = useCallback(() => {
    if (played) start(played.game, played.quest);
  }, [played, start]);

  const leave = useCallback(() => {
    setRound(null);
    setPlayed(null);
  }, []);

  return { round, played, pending, start, finish, again, leave };
}
