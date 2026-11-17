import { cssRatio, embedUrl, type Tone, type VideoBlock } from '@/lib/site/page';
import type { PageCtx } from '../context';
import { Head, headingOf, TitleTag } from '../heading';
import { Facade, FramedVideo } from '../player';
import { Section } from '../section';

/** A video: the academy's upload (poster, then its player) or YouTube/Vimeo behind a click; framed, wide, or beside words. */
export function Video({ block, ctx, tone }: { block: VideoBlock; ctx: PageCtx; tone: Tone }) {
  const { source, caption, autoplay_muted } = block.data;
  const ratio = cssRatio(block.data.ratio) ?? '16 / 9';
  const words = headingOf(block, ctx, {});
  const label = ctx.t('blocks.video.play', { title: words.title ?? caption ?? ctx.site.academy.name });
  const embed = embedUrl(source);
  let player = null;
  if (source.kind === 'upload' && source.video) {
    player = <FramedVideo video={source.video} poster={source.poster} label={label} ratio={ratio} autoplay={autoplay_muted} />;
  } else if (embed) {
    player = <Facade embed={embed} poster={source.poster} label={label} ratio={ratio} />;
  }
  if (!player) return null;
  const figure = (
    <figure className="sp-video-figure" data-ratio={block.data.ratio} data-reveal="">
      {player}
      {caption ? <figcaption>{caption}</figcaption> : null}
    </figure>
  );

  if (block.variant === 'side') {
    return (
      <Section block={block} tone={tone}>
        <div className="sp-pair" data-side="end" data-media="">
          <div className="sp-pair-media">{figure}</div>
          <div className="sp-pair-words" data-reveal="">
            {words.eyebrow ? <p className="sp-eyebrow">{words.eyebrow}</p> : null}
            {words.title ? <TitleTag block={block} ctx={ctx} className="sp-title">{words.title}</TitleTag> : null}
            {words.lead ? <p className="sp-lead">{words.lead}</p> : null}
          </div>
        </div>
      </Section>
    );
  }
  return (
    <Section block={block} tone={tone} className={block.variant === 'wide' ? 'sp-wide-media' : undefined}>
      <Head block={block} ctx={ctx} />
      {figure}
    </Section>
  );
}
