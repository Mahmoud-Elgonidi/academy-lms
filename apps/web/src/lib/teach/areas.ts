import { placeOn } from '../modules';

/**
 * The teacher's pages that belong to an area the academy may not have on
 * (`me.modules`, lib/modules.ts): trials are the CRM's, earnings payroll's,
 * exams the Quran's, messages messaging's, help support's, performance
 * evaluation's and "My card" the academy page's. While it is off a page leaves
 * the menus (the API refuses it too, and the page says so calmly).
 */
export function inPlan(href: string, modules: readonly string[]): boolean {
  return placeOn(href, modules);
}
