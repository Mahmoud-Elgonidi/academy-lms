import { cssRatio, type GalleryBlock, type Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head } from '../heading';
import { Section } from '../section';
import { GalleryView } from './gallery-view';

/** The academy's pictures, larger on a click. */
export function Gallery({ block, ctx, tone }: { block: GalleryBlock; ctx: PageCtx; tone: Tone }) {
  if (block.data.items.length === 0) return null;
  const t = ctx.t;
  return (
    <Section block={block} tone={tone}>
      <Head block={block} ctx={ctx} />
      <GalleryView
        items={block.data.items}
        variant={block.variant}
        ratio={cssRatio(block.data.ratio)}
        labels={{ open: t('blocks.gallery.open'), close: t('blocks.gallery.close'), previous: t('blocks.carousel.previous'), next: t('blocks.carousel.next') }}
      />
    </Section>
  );
}
