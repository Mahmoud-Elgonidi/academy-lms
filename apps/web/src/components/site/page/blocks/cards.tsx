import type { CSSProperties } from 'react';
import type { CardItem, CardsBlock, Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head } from '../heading';
import { PageButton } from '../link';
import { Picture } from '../media';
import { Section } from '../section';

/**
 * The academy's own courses, programmes or topics, each a card it writes
 * itself: a picture above the words (grid), a row each with the picture at
 * the start (list), or the words over the picture (overlay). Every card
 * keeps the same shape with or without a picture, so a row lines up; its
 * button, when there is one, makes the whole card a link.
 */
export function Cards({ block, ctx, tone }: { block: CardsBlock; ctx: PageCtx; tone: Tone }) {
  const { items, columns } = block.data;
  if (items.length === 0) return null;
  const pictures = items.some((item) => item.media);
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} />
      <ul className="sp-cards" data-variant={block.variant} data-columns={block.variant === 'list' ? undefined : columns} data-pictures={pictures ? '' : undefined}>
        {items.map((item, i) => (
          <Card key={`${item.title}-${i}`} item={item} variant={block.variant} ctx={ctx} index={i} />
        ))}
      </ul>
    </Section>
  );
}

function Card({ item, variant, ctx, index }: { item: CardItem; variant: CardsBlock['variant']; ctx: PageCtx; index: number }) {
  const button = <PageButton link={item.link} ctx={ctx} className="sp-card-link" />;
  const words = (
    <div className="sp-card-words">
      {item.label ? <p className="sp-card-label">{item.label}</p> : null}
      <h3>{item.title}</h3>
      {item.text ? <p className="sp-card-text">{item.text}</p> : null}
      {variant === 'list' ? null : button}
    </div>
  );
  return (
    <li
      className="sp-card"
      data-reveal=""
      data-media={item.media ? '' : undefined}
      data-linked={item.link ? '' : undefined}
      style={{ '--i': index % 4 } as CSSProperties}
    >
      {item.media ? (
        <Picture media={item.media} alt={item.media.alt} className="sp-card-media" ratio={variant === 'overlay' ? null : '4 / 3'} />
      ) : null}
      {words}
      {variant === 'list' && item.link ? <div className="sp-card-action">{button}</div> : null}
    </li>
  );
}
