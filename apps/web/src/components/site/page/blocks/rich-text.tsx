import type { RichTextBlock, Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head } from '../heading';
import { Rich } from '../rich';
import { Section } from '../section';

/** The academy's own prose: a narrow article, or the heading on one side and the text on the other. */
export function RichText({ block, ctx, tone }: { block: RichTextBlock; ctx: PageCtx; tone: Tone }) {
  const { body } = block.data;
  if (body.length === 0 && !block.data.title) return null;
  if (block.variant === 'two_columns') {
    return (
      <Section block={block} tone={tone}>
        <div className="sp-aside">
          <div className="sp-aside-head"><Head block={block} ctx={ctx} layout="stack" /></div>
          <Rich nodes={body} />
        </div>
      </Section>
    );
  }
  return (
    <Section block={block} tone={tone} className="sp-article">
      <Head block={block} ctx={ctx} layout="stack" />
      <Rich nodes={body} />
    </Section>
  );
}
