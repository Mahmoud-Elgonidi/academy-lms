import type { CSSProperties } from 'react';
import { initialOf } from '@/lib/site/initial';
import type { TestimonialsBlock, Tone } from '@/lib/site/page';
import { Icon } from '../../icons';
import type { PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';
import { QuoteDeck } from './quote-deck';

const DEFAULTS = { title: 'testimonials.title', eyebrow: 'testimonials.eyebrow' };

type Item = TestimonialsBlock['data']['items'][number];

function Stars({ rating, label }: { rating: number; label: string }) {
  if (rating < 1) return null;
  return (
    <span className="sp-stars" role="img" aria-label={label}>
      {[1, 2, 3, 4, 5].map((n) => <Icon key={n} name="star" className={n <= rating ? 'on' : undefined} />)}
    </span>
  );
}

function Face({ item, className }: { item: Item; className: string }) {
  return (
    <i className={className} aria-hidden="true">
      {item.photo ? (
        // eslint-disable-next-line @next/next/no-img-element -- the academy's own picture
        <img src={item.photo.url} alt="" loading="lazy" decoding="async" />
      ) : (
        initialOf(item.name)
      )}
    </i>
  );
}

/** One quote as a figure (the grid and the one-at-a-time deck). */
export function QuoteFigure({ item, ctx, large = false }: { item: Item; ctx: PageCtx; large?: boolean }) {
  return (
    <figure className="sp-quote" data-large={large ? '' : undefined}>
      <Stars rating={item.rating} label={ctx.t('blocks.testimonials.rating', { count: item.rating })} />
      <blockquote><p>{item.quote}</p></blockquote>
      <figcaption>
        <Face item={item} className="sp-quote-face" />
        <span><strong>{item.name}</strong>{item.role ? <small>{item.role}</small> : null}</span>
      </figcaption>
    </figure>
  );
}

/** What learners and families say (real words, with consent): today's moving strip, a grid, or one at a time. */
export function Testimonials({ block, ctx, tone }: { block: TestimonialsBlock; ctx: PageCtx; tone: Tone }) {
  const items = block.data.items;
  if (items.length === 0) return null;

  if (block.variant === 'marquee') {
    return (
      <LegacySection block={block} tone={tone} wrap={false}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} className="ls-head ls-wrap" />
        <div className="ls-marquee" data-reveal="">
          <div className="ls-marquee-track" style={{ '--ls-marquee': `${Math.max(30, items.length * 9)}s` } as CSSProperties}>
            {[...items, ...items].map((q, i) => (
              <figure key={i} className="ls-quote" aria-hidden={i >= items.length ? true : undefined}>
                <Stars rating={q.rating} label={ctx.t('blocks.testimonials.rating', { count: q.rating })} />
                <p>“{q.quote}”</p>
                <footer>
                  <Face item={q} className="" />
                  <span><strong>{q.name}</strong>{q.role ? <small>{q.role}</small> : null}</span>
                </footer>
              </figure>
            ))}
          </div>
        </div>
      </LegacySection>
    );
  }

  if (block.variant === 'single') {
    return (
      <Section block={block} tone={tone}>
        <Head block={block} ctx={ctx} defaults={DEFAULTS} />
        <QuoteDeck
          labels={{ previous: ctx.t('blocks.carousel.previous'), next: ctx.t('blocks.carousel.next'), of: ctx.t.raw('blocks.carousel.of') as string }}
          slides={items.map((item, i) => <QuoteFigure key={i} item={item} ctx={ctx} large />)}
        />
      </Section>
    );
  }

  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={DEFAULTS} />
      <ul className="sp-quotes">
        {items.map((item, i) => (
          <li key={i} data-reveal="" style={{ '--i': i % 3 } as CSSProperties}><QuoteFigure item={item} ctx={ctx} /></li>
        ))}
      </ul>
    </Section>
  );
}
