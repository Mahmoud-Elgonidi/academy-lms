/**
 * The academy's modules (docs/architecture/02, section 28): the areas that
 * are on, as `GET me` lists them (`modules`: SharedKernel\Plans\Feature
 * values; the core is never listed and always on). A paid area is the
 * plan's, an optional one the academy may hide; either way, an area that is
 * not listed is off, its entries leave the menus, tabs and home cards, and
 * the API answers its routes 403 `feature-not-in-plan` with `feature` and
 * `by` (academy or plan), which a page reached directly shows calmly.
 */

export type Module =
  | 'crm'
  | 'online_payments'
  | 'import'
  | 'custom_branding'
  | 'payroll'
  | 'ai'
  | 'quran'
  | 'groups'
  | 'curriculum'
  | 'games'
  | 'messaging'
  | 'announcements'
  | 'support'
  | 'evaluation'
  | 'site';

/** The API's problem when an area is off. */
export const FEATURE_OFF = 'feature-not-in-plan';

/** Who turned an area off: the academy (Settings › Academy modules) or its plan. */
export type OffBy = 'academy' | 'plan';

/** The modules that are on, from `me` (older answers carried only `plan_features`). */
export function modulesOf(person: { modules?: readonly string[] | null; plan_features?: readonly string[] | null } | null | undefined): readonly string[] {
  return person?.modules ?? person?.plan_features ?? [];
}

/** Whether a module is on. Games need the curriculum too (they live on the journey). */
export function hasModule(modules: readonly string[] | null | undefined, feature: Module): boolean {
  const on = modules ?? [];
  if (feature === 'games') return on.includes('games') && on.includes('curriculum');
  return on.includes(feature);
}

/** Whether a refused read means its area is off (a calm word, never an error page). */
export function featureOff(problem: { status: number; type?: string } | null | undefined): boolean {
  return problem?.status === 403 && problem.type === FEATURE_OFF;
}

/** Who turned it off, from the problem's `by` (the plan when it does not say). */
export function offBy(problem: { extra?: Record<string, unknown> } | null | undefined): OffBy {
  return problem?.extra?.by === 'academy' ? 'academy' : 'plan';
}

/**
 * The portal's places that belong to a module: a path under one of these
 * leaves the menus while its module is off. Everything else is the core.
 */
const PLACES: [string, Module][] = [
  ['/teach/trials', 'crm'],
  ['/teach/earnings', 'payroll'],
  ['/teach/exams', 'quran'],
  ['/teach/messages', 'messaging'],
  ['/teach/help', 'support'],
  ['/teach/performance', 'evaluation'],
  ['/teach/card', 'site'],
  ['/family/messages', 'messaging'],
  ['/family/help', 'support'],
  ['/family/journey', 'curriculum'],
  ['/family/games', 'games'],
  ['/family/challenges', 'curriculum'],
  ['/family/quran', 'quran'],
  ['/family/hifz', 'quran'],
];

/** The module a place belongs to, or null for the core. */
export function moduleOf(href: string): Module | null {
  const found = PLACES.find(([place]) => href === place || href.startsWith(`${place}/`));
  return found ? found[1] : null;
}

/** Whether a place is open while these modules are on. */
export function placeOn(href: string, modules: readonly string[]): boolean {
  const area = moduleOf(href);
  return area === null || hasModule(modules, area);
}
