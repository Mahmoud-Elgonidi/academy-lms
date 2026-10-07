import { describe, expect, it } from 'vitest';
import { targetOf } from './targets';

describe('where a notification opens', () => {
  it('follows the screen and id the API puts on it', () => {
    expect(targetOf('write_report', 'r1')).toBe('/teach/reports/r1');
    expect(targetOf('write_report', null)).toBe('/teach/reports');
    expect(targetOf('homework_to_mark', 'h1')).toBe('/teach/marking');
    expect(targetOf('shift_proposal', 'p1')).toBe('/teach/hours');
    expect(targetOf('time_off', 't1')).toBe('/teach/time-off');
    expect(targetOf('payroll_month', '2026-09')).toBe('/teach/earnings?month=2026-09');
    expect(targetOf('payroll_month', '../x')).toBe('/teach/earnings');
    expect(targetOf('trial', 'x')).toBe('/teach/trials');
    expect(targetOf('session', 's1')).toBe('/teach');
    expect(targetOf(undefined, undefined)).toBe('/teach');
  });
});
