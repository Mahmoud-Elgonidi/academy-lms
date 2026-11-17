import type { CSSProperties } from 'react';
import { say, type Heading as HeadingData, type PageBlock } from '@/lib/site/page';
import { audienceSay, type PageCtx } from './context';

type Defaults = { title?: string; lead?: string; eyebrow?: string };

/** The words of a block's heading after the defaults: null parts are not drawn. */
export function headingOf(block: PageBlock & { data: HeadingData }, ctx: PageCtx, defaults: Defaults) {
  const { data, style } = block;
  const fromKey = (key?: string) => (key ? () => audienceSay(ctx, key) : () => null);
  return {
    eyebrow: style.eyebrow ? say<string | null>(data.eyebrow, fromKey(defaults.eyebrow)) : null,
    title: say<string | null>(data.title, fromKey(defaults.title)),
    lead: say<string | null>(data.lead, fromKey(defaults.lead)),
  };
}

/** The page's one h1 is this block's title, or an h2 (6.5). */
export function TitleTag({ block, ctx, className, children, style }: { block: { id: string }; ctx: PageCtx; className?: string; children: React.ReactNode; style?: CSSProperties }) {
  return ctx.h1 === block.id ? <h1 className={className} style={style}>{children}</h1> : <h2 className={className} style={style}>{children}</h2>;
}

/** Today's centred section head (the legacy variants keep it exactly). */
export function LegacyHead({ block, ctx, defaults, className = 'ls-head', lead = true }: {
  block: PageBlock & { data: HeadingData };
  ctx: PageCtx;
  defaults: Defaults;
  className?: string;
  lead?: boolean;
}) {
  const words = headingOf(block, ctx, defaults);
  if (!words.title && !words.lead && !words.eyebrow) return null;
  return (
    <header className={className} data-reveal="">
      {words.eyebrow ? <span className="ls-eyebrow">{words.eyebrow}</span> : null}
      {words.title ? <TitleTag block={block} ctx={ctx}>{words.title}</TitleTag> : null}
      {lead && words.lead ? <p>{words.lead}</p> : null}
    </header>
  );
}

/**
 * The editorial head: a small label in the accent (sentence case, no pill)
 * when the academy turns it on, a large title, and a lead held to a short
 * measure. Aligned to the start, wide screens set the lead beside the title.
 */
export function Head({ block, ctx, defaults = {}, layout = 'split' }: {
  block: PageBlock & { data: HeadingData };
  ctx: PageCtx;
  defaults?: Defaults;
  layout?: 'split' | 'stack';
}) {
  const words = headingOf(block, ctx, defaults);
  if (!words.title && !words.lead && !words.eyebrow) return null;
  const split = layout === 'split' && block.style.align === 'start' && Boolean(words.title && words.lead);
  return (
    <header className="sp-head" data-layout={split ? 'split' : 'stack'} data-reveal="">
      <div>
        {words.eyebrow ? <p className="sp-eyebrow">{words.eyebrow}</p> : null}
        {words.title ? <TitleTag block={block} ctx={ctx} className="sp-title">{words.title}</TitleTag> : null}
      </div>
      {words.lead ? <p className="sp-lead">{words.lead}</p> : null}
    </header>
  );
}
