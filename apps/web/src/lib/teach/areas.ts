/**
 * The teacher's pages that belong to an area the academy's plan may leave
 * out (the API's `plan_features` on `me`): trials are the CRM's, earnings are
 * payroll's. Outside the plan they leave the menus (the API refuses them too).
 */
const AREAS: Record<string, string> = {
  '/teach/trials': 'crm',
  '/teach/earnings': 'payroll',
};

export function inPlan(href: string, features: readonly string[]): boolean {
  const area = AREAS[href];
  return area === undefined || features.includes(area);
}
