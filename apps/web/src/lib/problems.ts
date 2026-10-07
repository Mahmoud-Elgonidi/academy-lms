/**
 * What a form says when the API refuses a write: the API's own words for a
 * field the form shows go next to that field; anything else (a rule about the
 * whole request, an overlap, a closed month) comes first, then the title.
 */
export function formProblem(result: { message?: string; errors?: Record<string, string[]> }, shown: readonly string[]): string | undefined {
  const errors = result.errors ?? {};
  const other = Object.entries(errors).find(([key]) => !shown.some((field) => key === field || key.startsWith(`${field}.`)));
  if (other) return other[1][0];
  return Object.keys(errors).length > 0 ? undefined : result.message;
}
