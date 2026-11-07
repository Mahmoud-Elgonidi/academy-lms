import 'server-only';
import { notFound } from 'next/navigation';
import { ApiProblem, read } from './api/server';
import { featureOff, offBy, type OffBy } from './modules';

/**
 * A page's read of an area the academy may have off (lib/modules.ts): its
 * data, `off` with who turned it off (the API's 403 feature-not-in-plan, shown
 * calmly by `components/area-off.tsx`, never an error page), or `missing`
 * for anything else it cannot read.
 */
export type AreaRead<T> = { state: 'ok'; data: T } | { state: 'off'; by: OffBy } | { state: 'missing' };

export async function areaRead<T>(...args: Parameters<typeof read>): Promise<AreaRead<T>> {
  const done = await read<T>(...args);
  if (done.ok) return { state: 'ok', data: done.data };
  return featureOff(done.problem) ? { state: 'off', by: offBy(done.problem) } : { state: 'missing' };
}

/**
 * A page's main read of an area: its data, or `{ off }` while the area is off
 * (the page shows `AreaOff`); anything else goes as `get` sends it (a "not
 * found" page for 403 and 404, the error boundary for the rest).
 */
export async function areaGet<T>(...args: Parameters<typeof read>): Promise<{ off: false; data: T } | { off: true; by: OffBy }> {
  const done = await read<T>(...args);
  if (done.ok) return { off: false, data: done.data };
  if (featureOff(done.problem)) return { off: true, by: offBy(done.problem) };
  if (done.problem.status === 403 || done.problem.status === 404) notFound();
  throw new ApiProblem(done.problem);
}
