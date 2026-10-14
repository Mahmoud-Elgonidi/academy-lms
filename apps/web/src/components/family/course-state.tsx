import { useLocale, useTranslations } from 'next-intl';
import { Badge } from '@/components/ui';
import { courseState, type CourseDays } from '@/lib/family/course-state';
import type { Stage } from '@/lib/family/types';

/**
 * A course paused (until when), to be paused, ending or ended, as the API
 * says it, in the look's words; nothing when the course simply goes on.
 */
export function CourseState({ course, stage = 'classic', block = false }: { course: CourseDays; stage?: Stage; block?: boolean }) {
  const t = useTranslations('family.course');
  const state = courseState(course, useLocale(), stage);
  if (!state) return null;
  const badge = <Badge tone={state.tone}>{t(state.key, state.values)}</Badge>;
  return block ? <p>{badge}</p> : badge;
}

/** The same words alone, for a look that draws its own note (the kids' map). */
export function useCourseStateText(course: CourseDays | null | undefined, stage: Stage): string {
  const t = useTranslations('family.course');
  const state = courseState(course ?? {}, useLocale(), stage);
  return state ? t(state.key, state.values) : '';
}
