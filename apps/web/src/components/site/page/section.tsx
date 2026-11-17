import type { ReactNode } from 'react';
import type { PageBlock, Tone } from '@/lib/site/page';

/** A block in the editorial look: its tone, alignment, spacing and measure as data attributes (page.css). */
export function Section({ block, tone, className, children, wrap = true }: {
  block: PageBlock;
  tone: Tone;
  className?: string;
  children: ReactNode;
  /** Put the content in the block's measure (false: the block lays out its own). */
  wrap?: boolean;
}) {
  return (
    <section
      className={`sp-block${className ? ` ${className}` : ''}`}
      id={block.anchor}
      data-block={block.type}
      data-variant={block.variant}
      data-tone={tone}
      data-align={block.style.align}
      data-space={block.style.space}
      data-width={block.style.width}
    >
      {wrap ? <div className="sp-wrap">{children}</div> : children}
    </section>
  );
}

/**
 * A block in today's look (the legacy variants): the same section and
 * classes as the old page (its head stays centred). Its defaults (paper, normal space and width)
 * write nothing extra, so a migrated page is the old page.
 */
export function LegacySection({ block, tone, className, children, wrap = true }: {
  block: PageBlock;
  tone: Tone;
  className?: string;
  children: ReactNode;
  wrap?: boolean;
}) {
  const { space, width } = block.style;
  return (
    <section
      className={`ls-section${className ? ` ${className}` : ''}`}
      data-tone={tone === 'paper' ? undefined : tone}
      id={block.anchor}
      data-block={block.type}
      data-space={space === 'normal' ? undefined : space}
      data-width={width === 'normal' ? undefined : width}
    >
      {wrap ? <div className="ls-wrap">{children}</div> : children}
    </section>
  );
}
