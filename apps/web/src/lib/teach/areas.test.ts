import { describe, expect, it } from 'vitest';
import { inPlan } from './areas';

describe('the teacher pages a plan may leave out', () => {
  it('shows trials and earnings only when the plan includes the CRM and payroll', () => {
    expect(inPlan('/teach/trials', ['crm'])).toBe(true);
    expect(inPlan('/teach/trials', ['payroll'])).toBe(false);
    expect(inPlan('/teach/earnings', ['payroll'])).toBe(true);
    expect(inPlan('/teach/earnings', [])).toBe(false);
  });

  it('keeps every other page', () => {
    expect(inPlan('/teach', [])).toBe(true);
    expect(inPlan('/teach/time-off', [])).toBe(true);
  });
});
