'use server';

import { refresh } from 'next/cache';
import { send, type Outcome } from '@/lib/api/server';
import type { Answer, Finished, GameLimits, Round } from '@/lib/games/types';
import type { HatchedCreature } from '@/lib/journey/types';

/**
 * The games' writes, through the same endpoints (and so the same Actions)
 * as the app and the /my panel: starting and finishing a round on the
 * journey, hatching the egg the journey's stars filled, naming a creature or
 * choosing the buddy (the learner only), a guardian's limits. The API checks
 * each one again (whose learner, the allowance, the items, the egg).
 */
export type Refusal = { ok: false; message?: string; type?: string };

function refused(outcome: Outcome<unknown> & { ok: false }): Refusal {
  const first = Object.values(outcome.problem.errors)[0]?.[0];
  const type = outcome.problem.type?.split('/').at(-1);
  return { ok: false, message: first ?? (outcome.problem.title || undefined), type };
}

/**
 * Starts a round of a game on a station of the learner's journey (a lesson
 * they may practise now), or for a homework's practice; the page then opens
 * the game. A guardian starts one for their child (playing together).
 */
export async function startRound(learnerId: string, game: string, on: { station: string | null; homework: string | null }): Promise<{ ok: true; round: Round } | Refusal> {
  const started = await send<{ data: Round }>('post', '/v1/games/plays', {
    body: { learner_id: learnerId, game, station: on.homework ? null : on.station, homework_id: on.homework },
  });
  return started.ok ? { ok: true, round: started.data.data } : refused(started);
}

/** Hands in what the game reported; a retry of the same round sends the same key and counts once. */
export async function finishRound(play: string, answers: Answer[], key: string): Promise<{ ok: true; result: Finished } | Refusal> {
  const done = await send<{ data: Finished }>('post', '/v1/games/plays/{play}/finish', {
    params: { play },
    body: { answers: answers.slice(0, 500) },
    idempotencyKey: key,
  });
  if (!done.ok) return refused(done);
  refresh();
  return { ok: true, result: done.data.data };
}

/**
 * The learner taps open the egg the journey's stars filled: the next creature
 * hatches on the island they are on (its art's key). Null when no egg was
 * ready after all. One key per egg, so a retry hatches once.
 */
export async function hatchEgg(island: string | null, key: string): Promise<{ ok: true; creature: HatchedCreature | null } | Refusal> {
  const done = await send<{ data: { creature: HatchedCreature | null } }>('post', '/v1/games/hatch', { body: { island }, idempotencyKey: key });
  if (!done.ok) return refused(done);
  refresh();
  return { ok: true, creature: done.data.data.creature };
}

/** The learner names a creature they hatched (empty: its own name back). */
export async function nameCreature(key: string, name: string): Promise<{ ok: true } | Refusal> {
  const done = await send('put', '/v1/games/creatures/{creature}', { params: { creature: key }, body: { name } });
  if (!done.ok) return refused(done);
  refresh();
  return { ok: true };
}

/** The learner makes a creature their buddy (one at a time). */
export async function chooseBuddy(key: string): Promise<{ ok: true } | Refusal> {
  const done = await send('put', '/v1/games/creatures/{creature}', { params: { creature: key }, body: { buddy: true } });
  if (!done.ok) return refused(done);
  refresh();
  return { ok: true };
}

/** A guardian's choice for their child: on or off, minutes a day (null: the academy's), and the night lock. */
export async function saveLimits(learnerId: string, limits: Pick<GameLimits, 'enabled' | 'daily_minutes' | 'night_lock'>): Promise<{ ok: true } | Refusal> {
  const done = await send('put', '/v1/games/limits', {
    body: { learner_id: learnerId, enabled: limits.enabled, daily_minutes: limits.daily_minutes, night_lock: limits.night_lock },
  });
  if (!done.ok) return refused(done);
  refresh();
  return { ok: true };
}
