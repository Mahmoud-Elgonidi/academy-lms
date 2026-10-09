import type { Stage } from './types';

/** A student's own look: no cancelling or rating for kids, no money for students in a stage. */
export function canAct(stage: Stage): boolean {
  return stage !== 'kids';
}

export function seesMoney(stage: Stage): boolean {
  return stage === 'classic';
}

/** Whether a lesson offers this person anything to do: cancel, turn down the substitute, rate it once it took place, or dispute what it was recorded as. */
export function lessonActionsFor(
  lesson: { status: string; can_cancel: boolean; can_refuse_substitute: boolean; can_dispute?: boolean },
  stage: Stage,
): { cancel: boolean; refuse: boolean; rate: boolean; dispute: boolean } | null {
  if (!canAct(stage)) return null;
  const rate = lesson.status === 'completed' || lesson.status === 'student_absent';
  const found = { cancel: lesson.can_cancel, refuse: lesson.can_refuse_substitute, rate, dispute: lesson.can_dispute === true };
  return found.cancel || found.refuse || found.rate || found.dispute ? found : null;
}

/** A guardian follows at least one child (not only themself): their children's privacy is theirs to answer. */
export function isGuardian(learners: readonly { is_self: boolean }[]): boolean {
  return learners.some((l) => !l.is_self);
}
