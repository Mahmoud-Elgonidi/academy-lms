import type { AcademySite } from '@/lib/site/server';
import type { ShownPlan } from './academy-plans';

/** A price per lesson, written as the API writes money ("40 ر.س." in Arabic with Latin digits, "SAR 40" in English). */
export function perLesson(amountMinor: number, currency: string, lessons: number, locale: string): string | null {
  if (lessons < 2 || amountMinor <= 0) return null;
  try {
    const digits = new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2;
    const value = Math.round(amountMinor / lessons) / 10 ** digits;
    const arabic = locale.startsWith('ar');
    const text = new Intl.NumberFormat(arabic ? 'ar-u-nu-latn' : locale, {
      style: 'currency',
      currency,
      minimumFractionDigits: Number.isInteger(value) ? 0 : Math.min(2, digits),
      maximumFractionDigits: Number.isInteger(value) ? 0 : Math.min(2, digits),
    }).format(value).replace(/[‎‏]/g, '').trim();
    return arabic ? `⁨${text}⁩` : text;
  } catch {
    return null;
  }
}

/** The region a visitor sees prices in first: the academy's default, else the first. */
export function startRegion(regions: AcademySite['regions']): string | null {
  return (regions.find((r) => r.default) ?? regions[0])?.id ?? null;
}

type Say = (key: string, values?: Record<string, string | number>) => string;

/**
 * The academy's plans as the page shows them, every word made on the server:
 * facts, prices per region (and per lesson, unless the page leaves it out),
 * and private or group only when the academy sells both.
 */
export function shownPlans(plans: AcademySite['plans'], locale: string, t: Say, shared: string, withPerLesson = true): ShownPlan[] {
  const isGroup = (plan: AcademySite['plans'][number]) => plan.format === 'group';
  const bothKinds = plans.some(isGroup) && plans.some((p) => !isGroup(p));
  return plans.map((plan) => {
    const facts = [
      ...(isGroup(plan) && plan.max_students ? [t('pricing.upTo', { count: plan.max_students })] : []),
      t('pricing.lessons', { count: plan.sessions_per_block }),
      t('pricing.minutes', { count: plan.session_minutes }),
      t('pricing.perWeek', { count: plan.sessions_per_week }),
      t('pricing.validity', { days: plan.validity_days }),
    ];
    if (plan.shared) facts.push(shared);
    const prices: ShownPlan['prices'] = {};
    for (const p of plan.prices) {
      prices[p.region_id] = { price: p.price, perLesson: withPerLesson ? perLesson(p.amount_minor, p.currency, plan.sessions_per_block, locale) : null };
    }
    return { id: plan.id, name: plan.name, facts, prices, format: isGroup(plan) ? 'group' : bothKinds ? 'private' : null };
  });
}
