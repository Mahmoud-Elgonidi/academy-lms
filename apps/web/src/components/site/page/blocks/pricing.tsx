import { say, type PricingBlock, type Tone } from '@/lib/site/page';
import { AcademyPlans } from '../../academy-plans';
import { shownPlans, startRegion } from '../../plans';
import { audienceSay, type PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';
import { PlansTable } from './plans-table';

const DEFAULTS = { title: 'pricing.title', lead: 'pricing.lead', eyebrow: 'pricing.eyebrow' };

/** The academy's plans (live: Billing, filtered and ordered by the API for this block), as today's cards or a table. */
export function Pricing({ block, ctx, tone }: { block: PricingBlock; ctx: PageCtx; tone: Tone }) {
  const { site, t } = ctx;
  if (site.plans.length === 0) return null;
  const plans = shownPlans(site.plans, ctx.locale, (key, values) => t(key, values), audienceSay(ctx, 'pricing.shared'), block.data.per_lesson);
  const regions = site.regions.map((r) => ({ id: r.id, name: r.name }));
  const highlight = block.data.highlight && plans.some((p) => p.id === block.data.highlight)
    ? { id: block.data.highlight, label: say(block.data.highlight_label, () => t('blocks.pricing.highlight')) }
    : null;
  const startHref = ctx.links.form ? '#join' : null;
  const note = block.data.note ? <p className="sp-note">{block.data.note}</p> : null;

  if (block.variant === 'cards') {
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
        <AcademyPlans plans={plans} regions={regions} start={startRegion(site.regions)} highlight={highlight} startHref={startHref} />
        {note}
      </LegacySection>
    );
  }

  // The table's rows: the facts every plan has, in the same order as on the cards.
  const source = site.plans;
  const row = (label: string, cell: (p: (typeof source)[number]) => string) => ({ label, cells: source.map(cell) });
  const rows = [
    row(t('blocks.pricing.rows.lessons'), (p) => t('pricing.lessons', { count: p.sessions_per_block })),
    row(t('blocks.pricing.rows.minutes'), (p) => t('pricing.minutes', { count: p.session_minutes })),
    row(t('blocks.pricing.rows.perWeek'), (p) => t('pricing.perWeek', { count: p.sessions_per_week })),
    row(t('blocks.pricing.rows.validity'), (p) => t('pricing.validity', { days: p.validity_days })),
  ];
  if (source.some((p) => p.format === 'group')) {
    rows.unshift(row(t('blocks.pricing.rows.format'), (p) => (p.format === 'group' ? (p.max_students ? t('pricing.upTo', { count: p.max_students }) : t('blocks.pricing.group')) : t('blocks.pricing.private'))));
  }
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={DEFAULTS} />
      <PlansTable plans={plans} rows={rows} regions={regions} start={startRegion(site.regions)} highlight={highlight} startLabel={t('pricing.start')} href={startHref} />
      {note}
    </Section>
  );
}
