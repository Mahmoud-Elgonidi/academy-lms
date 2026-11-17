import type { CSSProperties } from 'react';
import type { StatsBlock, Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head } from '../heading';
import { Section } from '../section';

/** Numbers the academy typed itself (never counted from the database): one quiet band, or cards. */
export function Stats({ block, ctx, tone }: { block: StatsBlock; ctx: PageCtx; tone: Tone }) {
  const items = block.data.items;
  if (items.length === 0) return null;
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} />
      <dl className="sp-stats" data-variant={block.variant} style={{ '--count': items.length } as CSSProperties}>
        {items.map((item, i) => (
          <div key={`${item.label}-${i}`} data-reveal="" style={{ '--i': i } as CSSProperties}>
            <dt>{item.label}</dt>
            <dd dir="auto">{item.value}</dd>
          </div>
        ))}
      </dl>
    </Section>
  );
}
