import { describe, expect, it } from 'vitest';
import { featureOff, hasModule, moduleOf, modulesOf, offBy, placeOn } from './modules';

describe('the academy’s modules', () => {
  it('reads them from me, the older plan_features too', () => {
    expect(modulesOf({ modules: ['messaging'], plan_features: ['crm'] })).toEqual(['messaging']);
    expect(modulesOf({ plan_features: ['crm'] })).toEqual(['crm']);
    expect(modulesOf(null)).toEqual([]);
  });

  it('is on only when listed; games need the curriculum they live on', () => {
    expect(hasModule(['messaging'], 'messaging')).toBe(true);
    expect(hasModule(['messaging'], 'support')).toBe(false);
    expect(hasModule(undefined, 'support')).toBe(false);
    expect(hasModule(['games'], 'games')).toBe(false);
    expect(hasModule(['games', 'curriculum'], 'games')).toBe(true);
  });

  it('tells an area that is off from any other refusal, and who turned it off', () => {
    const off = { status: 403, type: 'feature-not-in-plan', extra: { feature: 'messaging', by: 'academy' } };
    expect(featureOff(off)).toBe(true);
    expect(offBy(off)).toBe('academy');
    expect(offBy({ extra: { by: 'plan' } })).toBe('plan');
    expect(offBy({})).toBe('plan');
    expect(featureOff({ status: 403, type: 'forbidden' })).toBe(false);
    expect(featureOff({ status: 404, type: 'feature-not-in-plan' })).toBe(false);
  });

  it('knows which module a place belongs to, its pages under it too', () => {
    expect(moduleOf('/family/messages/abc')).toBe('messaging');
    expect(moduleOf('/teach/help')).toBe('support');
    expect(moduleOf('/family/games/album')).toBe('games');
    expect(moduleOf('/family/journey')).toBe('curriculum');
    expect(moduleOf('/teach/performance')).toBe('evaluation');
    expect(moduleOf('/teach/card')).toBe('site');
    expect(moduleOf('/family/lessons')).toBeNull();
    expect(moduleOf('/family/messagesx')).toBeNull();
  });

  it('keeps the core open whatever is off', () => {
    expect(placeOn('/family/lessons', [])).toBe(true);
    expect(placeOn('/teach/messages', [])).toBe(false);
    expect(placeOn('/teach/messages', ['messaging'])).toBe(true);
  });
});
