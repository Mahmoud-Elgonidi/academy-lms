import { hasModule } from '../modules';
import type { Stage } from './types';

/** A student's own look: no cancelling or rating for kids, no money for students in a stage. */
export function canAct(stage: Stage): boolean {
  return stage !== 'kids';
}

/** The zone chip in the corner: every look but the kids' (as the panels and the app leave it out there). */
export function showsZoneChip(stage: Stage): boolean {
  return stage !== 'kids';
}

export function seesMoney(stage: Stage): boolean {
  return stage === 'classic';
}

/** "My data" (a copy, erasure): adults only, in the classic look; a younger student's data is their guardian's to ask about. */
export function ownsData(stage: Stage): boolean {
  return stage === 'classic';
}

/**
 * Whether a lesson offers this person anything to do: cancel, turn down the
 * substitute, rate it once it took place (while the academy has evaluation
 * on: `modules`, left out meaning it is), or dispute what it was recorded as.
 */
export function lessonActionsFor(
  lesson: { status: string; can_cancel: boolean; can_refuse_substitute: boolean; can_dispute?: boolean },
  stage: Stage,
  modules?: readonly string[] | null,
): { cancel: boolean; refuse: boolean; rate: boolean; dispute: boolean } | null {
  if (!canAct(stage)) return null;
  const rating = modules ? hasModule(modules, 'evaluation') : true;
  const rate = rating && (lesson.status === 'completed' || lesson.status === 'student_absent');
  const found = { cancel: lesson.can_cancel, refuse: lesson.can_refuse_substitute, rate, dispute: lesson.can_dispute === true };
  return found.cancel || found.refuse || found.rate || found.dispute ? found : null;
}

/**
 * A guardian follows at least one child (not only themself): their
 * children's privacy is theirs to answer. Never in an academy for adults
 * (`adults`), where nobody follows a child.
 */
export function isGuardian(learners: readonly { is_self: boolean }[], adults = false): boolean {
  return !adults && learners.some((l) => !l.is_self);
}

/**
 * Whether «إضافة طفل» is offered (J5a): a guardian in the classic look, one
 * who follows a child or, with no learner yet, has the guardian role; never
 * an adult who studies for themselves alone, and never in an academy for
 * adults (the API answers 404 there too).
 */
export function mayAddChild(who: { stage: Stage; learners: readonly { is_self: boolean }[]; roles: readonly string[]; adults: boolean }): boolean {
  if (who.adults || !seesMoney(who.stage)) return false;
  return isGuardian(who.learners) || (who.learners.length === 0 && who.roles.includes('guardian'));
}

/** A report, a start or a trial read in the first person ("your report"): an adult academy's trainee, or a learner reading about themself. */
export function readsAsSelf(adults: boolean, learner: { is_self: boolean } | null | undefined): boolean {
  return adults || learner?.is_self === true;
}
