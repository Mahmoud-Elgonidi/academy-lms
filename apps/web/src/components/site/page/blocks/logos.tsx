import type { CSSProperties } from 'react';
import { safeUrl, type LogosBlock, type Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head } from '../heading';
import { Section } from '../section';

/** Partners, accreditations, "as seen in": a row, a moving strip (still under reduced motion), or a grid; in one colour until hovered. */
export function Logos({ block, ctx, tone }: { block: LogosBlock; ctx: PageCtx; tone: Tone }) {
  const items = block.data.items;
  if (items.length === 0) return null;
  const logo = (item: LogosBlock['data']['items'][number], i: number, hidden = false) => {
    const href = safeUrl(item.url);
    const img = (
      // eslint-disable-next-line @next/next/no-img-element -- the academy's partners' marks
      <img src={item.media.url} alt={hidden ? '' : item.name} width={item.media.width ?? undefined} height={item.media.height ?? undefined} loading="lazy" decoding="async" />
    );
    return (
      <li key={`${item.name}-${i}`} aria-hidden={hidden ? true : undefined}>
        {href && !hidden ? <a href={href} target="_blank" rel="noopener">{img}</a> : img}
      </li>
    );
  };
  const marquee = block.variant === 'marquee';
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} />
      <div className="sp-logos" data-variant={block.variant} data-mono={block.data.mono ? '' : undefined} data-reveal="">
        <ul style={marquee ? ({ '--sp-marquee': `${Math.max(24, items.length * 4)}s` } as CSSProperties) : undefined}>
          {items.map((item, i) => logo(item, i))}
          {marquee ? items.map((item, i) => logo(item, i + items.length, true)) : null}
        </ul>
      </div>
    </Section>
  );
}
