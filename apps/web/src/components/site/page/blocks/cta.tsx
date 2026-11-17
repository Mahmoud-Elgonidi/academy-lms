import type { CtaBlock, Tone } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { TitleTag } from '../heading';
import { PageButton } from '../link';
import { Picture } from '../media';
import { Section } from '../section';

/** A call to act: a full band (over the academy's picture, with a scrim), a raised card, or words beside a picture. */
export function Cta({ block, ctx, tone }: { block: CtaBlock; ctx: PageCtx; tone: Tone }) {
  const { title, lead, primary, secondary, media } = block.data;
  const words = (
    <div className="sp-cta-words">
      <TitleTag block={block} ctx={ctx} className="sp-title">{title}</TitleTag>
      {lead ? <p className="sp-lead">{lead}</p> : null}
      <div className="sp-actions">
        <PageButton link={primary} ctx={ctx} kind="primary" />
        <PageButton link={secondary} ctx={ctx} kind="secondary" />
      </div>
    </div>
  );
  if (block.variant === 'split') {
    return (
      <Section block={block} tone={tone}>
        <div className="sp-pair sp-cta" data-side="end" data-media={media ? '' : undefined}>
          {media ? <div className="sp-pair-media"><Picture media={media} /></div> : null}
          <div className="sp-pair-words" data-reveal="">{words}</div>
        </div>
      </Section>
    );
  }
  if (block.variant === 'card') {
    return (
      <Section block={block} tone={tone}>
        <div className="sp-cta-card" data-reveal="">{words}</div>
      </Section>
    );
  }
  return (
    <Section block={block} tone={media ? 'night' : tone} className="sp-cta-banner">
      {media ? (
        <div className="sp-cta-bg" aria-hidden="true">
          {/* eslint-disable-next-line @next/next/no-img-element -- the academy's own picture, behind the words */}
          <img src={media.url} alt="" loading="lazy" decoding="async" />
        </div>
      ) : null}
      <div data-reveal="">{words}</div>
    </Section>
  );
}
