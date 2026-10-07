import { describe, expect, it } from 'vitest';
import { formProblem } from './problems';

describe("a refused write's words", () => {
  it('leave field errors to their fields and say the rest', () => {
    expect(formProblem({ message: 'Invalid', errors: { reason: ['Pick a reason'] } }, ['reason'])).toBeUndefined();
    expect(formProblem({ message: 'Invalid', errors: { period: ['Overlaps another request'] } }, ['reason'])).toBe('Overlaps another request');
    expect(formProblem({ message: 'Invalid', errors: { 'items.0.outcome': ['Pick one'] } }, ['items'])).toBeUndefined();
    expect(formProblem({ message: 'Closed month' }, ['reason'])).toBe('Closed month');
  });
});
