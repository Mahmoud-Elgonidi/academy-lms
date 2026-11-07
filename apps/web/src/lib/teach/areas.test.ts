import { describe, expect, it } from 'vitest';
import { inPlan } from './areas';

describe('the teacher pages a plan may leave out', () => {
  it('shows trials and earnings only when the plan includes the CRM and payroll', () => {
    expect(inPlan('/teach/trials', ['crm'])).toBe(true);
    expect(inPlan('/teach/trials', ['payroll'])).toBe(false);
    expect(inPlan('/teach/earnings', ['payroll'])).toBe(true);
    expect(inPlan('/teach/earnings', [])).toBe(false);
  });

  it('shows the Quran’s exams only when the plan includes the Quran', () => {
    expect(inPlan('/teach/exams', ['quran'])).toBe(true);
    expect(inPlan('/teach/exams', ['crm'])).toBe(false);
  });

  it('keeps every other page', () => {
    expect(inPlan('/teach', [])).toBe(true);
    expect(inPlan('/teach/time-off', [])).toBe(true);
  });
});

describe('the teacher pages an academy may turn off', () => {
  it('drops messages, help, performance and the card while their module is off', () => {
    for (const [href, area] of [
      ['/teach/messages', 'messaging'],
      ['/teach/help', 'support'],
      ['/teach/performance', 'evaluation'],
      ['/teach/card', 'site'],
    ] as const) {
      expect(inPlan(href, [area])).toBe(true);
      expect(inPlan(href, [])).toBe(false);
    }
  });
});
