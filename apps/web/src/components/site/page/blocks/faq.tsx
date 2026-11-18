import type { CSSProperties } from 'react';
import type { FaqBlock, Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';

const DEFAULTS = { title: 'faq.title', eyebrow: 'faq.eyebrow' };

/** Questions and answers as native disclosures (keyboard and screen readers for free), centred as today or beside a sticky heading. */
export function Faq({ block, ctx, tone }: { block: FaqBlock; ctx: PageCtx; tone: Tone }) {
  const items = block.data.items;
  if (items.length === 0) return null;
  const list = (className: string) => (
    <div className={className}>
      {items.map((f, i) => (
        <details key={i} data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
          <summary>{f.q}</summary>
          <p>{f.a}</p>
        </details>
      ))}
    </div>
  );

  if (block.variant === 'accordion') {
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
        {list('ls-faq')}
      </LegacySection>
    );
  }

  return (
    <Section block={block} tone={tone}>
      <div className="sp-aside">
        <div className="sp-aside-head">
          <Head block={block} ctx={ctx} defaults={DEFAULTS} layout="stack" />
        </div>
        {list('sp-faq')}
      </div>
    </Section>
  );
}
