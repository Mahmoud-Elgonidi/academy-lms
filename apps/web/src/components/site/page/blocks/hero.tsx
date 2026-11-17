import type { CSSProperties } from 'react';
import { say, sayList, type HeroBlock } from '@/lib/site/page';
import { Icon } from '../../icons';
import { Tilt } from '../../motion';
import { GuardianScreen, Phone, StudentScreen, TeacherScreen } from '../../phones';
import { Starfield } from '../../starfield';
import { audienceRaw, audienceSay, type PageCtx } from '../context';
import { TitleTag } from '../heading';
import { PageButton } from '../link';
import { BlockMedia } from '../media';
import { BackgroundVideo } from '../player';

/** The hero's words after the defaults, and which of them show. */
function wordsOf(block: HeroBlock, ctx: PageCtx) {
  const { data } = block;
  const show = data.show;
  return {
    kicker: show.kicker ? say(data.kicker, () => ctx.t('kicker')) : null,
    lead: show.lead ? data.lead : null,
    trust: show.trust ? sayList(data.trust, () => audienceRaw<string[]>(ctx, 'trust')) : [],
    buttons: show.buttons,
  };
}

/** The title as words, each its own span (the entrance moves them in turn). */
function Words({ title, glow }: { title: string; glow: boolean }) {
  const words = title.split(/\s+/).filter(Boolean);
  return words.map((word, i) => (
    <span key={`${word}-${i}`} className={`ls-word${glow && i >= words.length - 2 ? ' ls-glowing' : ''}`} style={{ '--w': Math.min(i, 12) } as CSSProperties}>
      {word}
      {i < words.length - 1 ? ' ' : ''}
    </span>
  ));
}

/** Today's opening for children's academies: the night sky, the three phones and the floating cards (pixel-equal to the old page). */
function PhonesHero({ block, ctx }: { block: HeroBlock; ctx: PageCtx }) {
  const w = wordsOf(block, ctx);
  const words = ctx.phones;
  return (
    <section className="ls-hero" id={block.anchor} data-block="hero">
      <div className="ls-aurora" aria-hidden="true"><span /><span /><span /></div>
      <Starfield />
      <div className="ls-grid-floor" aria-hidden="true" />
      <div className="ls-wrap ls-hero-grid" data-solo={block.data.show.media ? undefined : ''}>
        <div>
          {w.kicker ? <span className="ls-kicker"><i aria-hidden="true" />{w.kicker}</span> : null}
          <TitleTag block={block} ctx={ctx}><Words title={block.data.title} glow={ctx.character === 'playful'} /></TitleTag>
          {w.lead ? <p className="ls-lead">{w.lead}</p> : null}
          {w.buttons ? (
            <div className="ls-cta">
              <PageButton link={block.data.primary} ctx={ctx} className="ls-btn ls-btn-main" />
              <PageButton link={block.data.secondary} ctx={ctx} className="ls-btn ls-btn-ghost" />
            </div>
          ) : null}
          {w.trust.length > 0 ? (
            <div className="ls-trust">
              {w.trust.map((line) => <span key={line}><Icon name="check" />{line}</span>)}
            </div>
          ) : null}
        </div>
        {block.data.show.media ? (
          <div className="ls-stage" role="img" aria-label={audienceSay(ctx, 'phonesLabel')}>
            <Tilt className="ls-tilt" max={10}>
              <Phone position="start"><StudentScreen w={words.student} /></Phone>
              <Phone position="center"><GuardianScreen w={words.guardian} /></Phone>
              <Phone position="end"><TeacherScreen w={words.teacher} /></Phone>
              <div className="ls-float-card" data-at="top" aria-hidden="true">
                <span>{ctx.t('floating.report')}</span>
                <b>{audienceSay(ctx, 'floating.reportValue')}</b>
                <div className="ls-stars-row">{[0, 1, 2, 3, 4].map((i) => <Icon key={i} name="star" />)}</div>
              </div>
              <div className="ls-float-card" data-at="bottom" aria-hidden="true">
                <span>{ctx.t('floating.attendance')}</span>
                <b>{ctx.t('floating.attendanceValue')}</b>
              </div>
            </Tilt>
          </div>
        ) : null}
      </div>
    </section>
  );
}

/**
 * The editorial opening: the title set large and tight, a short lead, the
 * buttons, and the academy's own picture or video, beside (split), under
 * (centered) or behind (video) the words; or the words alone (minimal).
 */
function EditorialHero({ block, ctx, tone }: { block: HeroBlock; ctx: PageCtx; tone: string }) {
  const { data } = block;
  const w = wordsOf(block, ctx);
  const hasMedia = data.show.media && Boolean(data.media || data.video);
  // A split hero without its picture reads as a centred one (4.4); a video hero without its video, as a centred one in its tone.
  const variant = block.variant === 'split' && !hasMedia ? 'centered' : block.variant === 'video' && !data.video ? 'centered' : block.variant;
  const label = data.title;
  return (
    <section
      className="sp-block sp-hero"
      id={block.anchor}
      data-block="hero"
      data-variant={variant}
      data-tone={tone}
      data-align={variant === 'split' ? 'start' : block.style.align}
      data-space={block.style.space}
      data-width={block.style.width}
      data-side={data.media_side}
      data-shape={data.media_shape}
      data-scrim={variant === 'video' ? data.scrim : undefined}
    >
      {variant === 'video' && data.video ? (
        <>
          <BackgroundVideo video={data.video} poster={data.poster} />
          <div className="sp-scrim" aria-hidden="true" />
        </>
      ) : null}
      <div className="sp-wrap sp-hero-grid">
        <div className="sp-hero-copy">
          {w.kicker ? <p className="sp-kicker">{w.kicker}</p> : null}
          <TitleTag block={block} ctx={ctx} className="sp-display"><Words title={data.title} glow={false} /></TitleTag>
          {w.lead ? <p className="sp-hero-lead">{w.lead}</p> : null}
          {w.buttons ? (
            <div className="sp-actions">
              <PageButton link={data.primary} ctx={ctx} kind="primary" />
              <PageButton link={data.secondary} ctx={ctx} kind="secondary" />
            </div>
          ) : null}
          {w.trust.length > 0 ? (
            <ul className="sp-trust">
              {w.trust.map((line) => <li key={line}><Icon name="check" />{line}</li>)}
            </ul>
          ) : null}
        </div>
        {hasMedia && (variant === 'split' || variant === 'centered') ? (
          <div className="sp-hero-media">
            <BlockMedia media={data.media} video={data.video} poster={data.poster} label={label} eager shape={variant === 'split' ? data.media_shape : 'rounded'} />
          </div>
        ) : null}
      </div>
    </section>
  );
}

export function Hero({ block, ctx, tone }: { block: HeroBlock; ctx: PageCtx; tone: string }) {
  return block.variant === 'phones' ? <PhonesHero block={block} ctx={ctx} /> : <EditorialHero block={block} ctx={ctx} tone={tone} />;
}
