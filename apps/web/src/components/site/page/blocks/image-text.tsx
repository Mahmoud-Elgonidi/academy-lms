import type { ImageTextBlock, Tone } from '@/lib/site/page';
import { Icon } from '../../icons';
import type { PageCtx } from '../context';
import { headingOf, TitleTag } from '../heading';
import { PageButton } from '../link';
import { BlockMedia } from '../media';
import { Rich } from '../rich';
import { Section } from '../section';

/** A picture (or a video) and words side by side, with the words overlapping it, or stacked. */
export function ImageText({ block, ctx, tone }: { block: ImageTextBlock; ctx: PageCtx; tone: Tone }) {
  const { data } = block;
  const words = headingOf(block, ctx, {});
  const hasMedia = Boolean(data.media || data.video);
  return (
    <Section block={block} tone={tone}>
      <div className="sp-pair" data-side={data.media_side} data-media={hasMedia ? '' : undefined}>
        {hasMedia ? (
          <div className="sp-pair-media" data-reveal="">
            <BlockMedia media={data.media} video={data.video} poster={data.poster} label={words.title ?? ctx.site.academy.name} />
          </div>
        ) : null}
        <div className="sp-pair-words" data-reveal="">
          {words.eyebrow ? <p className="sp-eyebrow">{words.eyebrow}</p> : null}
          {words.title ? <TitleTag block={block} ctx={ctx} className="sp-title">{words.title}</TitleTag> : null}
          {words.lead ? <p className="sp-lead">{words.lead}</p> : null}
          <Rich nodes={data.body} />
          {data.bullets.length > 0 ? (
            <ul className="sp-bullets">
              {data.bullets.map((line, i) => <li key={`${line}-${i}`}><Icon name="check" />{line}</li>)}
            </ul>
          ) : null}
          {data.link ? <div className="sp-actions"><PageButton link={data.link} ctx={ctx} kind="secondary" /></div> : null}
        </div>
      </div>
    </Section>
  );
}
