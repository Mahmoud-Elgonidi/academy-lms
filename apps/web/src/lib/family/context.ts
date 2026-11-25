import 'server-only';
import { cookies } from 'next/headers';
import { cache } from 'react';
import { get } from '../api/server';
import { adultsAcademy } from '../audience-server';
import type { Learner, Learners, Stage } from './types';

/** Which of a guardian's learners the pages show; any page of theirs keeps to it. */
export const LEARNER_COOKIE = 'lms_learner';

export type Family = {
  learners: Learner[];
  /** The look of the person signed in (FamilyView::stage): kids, middle, teen, or classic. */
  stage: Stage;
  /** The learner every page is about: the one chosen, else the first (the student themself). */
  current: Learner | null;
  /** The academy trains adults (lib/audience.ts): nobody here follows a child. */
  adults: boolean;
};

export const family = cache(async (): Promise<Family> => {
  const [list, jar, adults] = await Promise.all([get<Learners>('/v1/family/learners'), cookies(), adultsAcademy()]);
  const chosen = jar.get(LEARNER_COOKIE)?.value;
  return {
    learners: list.data,
    stage: list.viewer_stage,
    current: list.data.find((l) => l.id === chosen) ?? list.data[0] ?? null,
    adults,
  };
});
