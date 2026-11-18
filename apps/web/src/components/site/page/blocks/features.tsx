import type { CSSProperties } from 'react';
import type { FeaturesBlock, Tone } from '@/lib/site/page';
import { Icon, isIconName, type IconName } from '../../icons';
import type { PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { LegacySection, Section } from '../section';

const WHY_ICONS: IconName[] = ['users', 'report', 'calendar', 'map', 'shield', 'heart'];
const ACCENTS = ['', 'gold', 'blue', 'rose', 'violet', ''];
const DEFAULTS = { title: 'why.title', lead: 'why.lead', eyebrow: 'why.eyebrow' };

const iconOf = (name: string, i: number): IconName => (isIconName(name) ? name : WHY_ICONS[i] ?? 'star');

/** Why the academy: reasons as cards (today's), under icons, as a ticked list, or numbered when they are an order. */
export function Features({ block, ctx, tone }: { block: FeaturesBlock; ctx: PageCtx; tone: Tone }) {
  const { items, columns } = block.data;
  if (items.length === 0) return null;

  if (block.variant === 'cards') {
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
        <div className="ls-cards" data-columns={columns}>
          {items.map((f, i) => (
            <article key={`${f.title}-${i}`} className="ls-card ls-feature" data-accent={ACCENTS[i % ACCENTS.length]} data-reveal="" style={{ '--i': i % 3 } as CSSProperties}>
              <span className="ls-icon"><Icon name={iconOf(f.icon, i)} /></span>
              <h3>{f.title}</h3>
              {f.text ? <p>{f.text}</p> : null}
            </article>
          ))}
        </div>
      </LegacySection>
    );
  }

  // Numbers only when the reasons really are an order (an ordered list); otherwise a plain list.
  const List = block.variant === 'numbers' ? 'ol' : 'ul';
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} defaults={DEFAULTS} />
      {block.variant === 'list' ? (
        <ul className="sp-ticks" data-columns={2}>
          {items.map((f, i) => (
            <li key={`${f.title}-${i}`} data-reveal="" style={{ '--i': i % 4 } as CSSProperties}>
              <Icon name="check" />
              <div>
                <h3>{f.title}</h3>
                {f.text ? <p>{f.text}</p> : null}
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <List className="sp-features" data-variant={block.variant} data-columns={columns}>
          {items.map((f, i) => (
            <li key={`${f.title}-${i}`} data-reveal="" style={{ '--i': i % 3 } as CSSProperties}>
              {block.variant === 'numbers' ? (
                <span className="sp-ordinal" aria-hidden="true">{(i + 1).toLocaleString(ctx.locale === 'ar' ? 'ar-u-nu-latn' : 'en')}</span>
              ) : (
                <span className="sp-icon"><Icon name={iconOf(f.icon, i)} /></span>
              )}
              <h3>{f.title}</h3>
              {f.text ? <p>{f.text}</p> : null}
            </li>
          ))}
        </List>
      )}
    </Section>
  );
}
