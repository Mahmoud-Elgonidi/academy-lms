import { Fragment, type CSSProperties } from 'react';
import type { AppBlock, Tone } from '@/lib/site/page';
import { AcademyCode } from '../../academy-plans';
import { AppShowcase } from '../../app-showcase';
import { AppleIcon, GooglePlayIcon, Icon } from '../../icons';
import { screensFor } from '../../phones';
import { audienceRaw, type PageCtx } from '../context';
import { Head, LegacyHead } from '../heading';
import { Picture } from '../media';
import { LegacySection, Section } from '../section';

const DEFAULTS = { title: 'app.title', lead: 'app.lead', eyebrow: 'app.eyebrow' };

type Point = { title: string; text: string; points?: string[] };

/** The app (live: the platform's store links and the academy's code): today's phone showcase, the academy's own picture, or words and store buttons. */
export function App({ block, ctx, tone }: { block: AppBlock; ctx: PageCtx; tone: Tone }) {
  const { site, t } = ctx;
  const { show_code, show_stores, media } = block.data;
  const hasStores = Boolean(site.app.android_url || site.app.ios_url);
  if (show_stores && !hasStores && !show_code) return null;
  const points: Point[] = block.data.points ?? audienceRaw<Point[]>(ctx, 'app.steps');

  const footer = (
    <>
      {show_code ? <AcademyCode code={site.academy.code} label={t('app.code')} /> : null}
      {show_stores ? (
        <div className="ls-stores">
          {site.app.android_url ? (
            <a className="ls-store" href={site.app.android_url} target="_blank" rel="noopener">
              <GooglePlayIcon />
              <span><small>{t('app.getOn')}</small>{t('app.googlePlay')}</span>
            </a>
          ) : null}
          {site.app.ios_url ? (
            <a className="ls-store" href={site.app.ios_url} target="_blank" rel="noopener">
              <AppleIcon />
              <span><small>{t('app.downloadOn')}</small>{t('app.appStore')}</span>
            </a>
          ) : null}
          {!hasStores ? <span className="ls-kicker"><i aria-hidden="true" />{t('app.soon')}</span> : null}
        </div>
      ) : null}
    </>
  );

  if (block.variant === 'phones') {
    // The learner's home first, then the second screen of the set (a child's journey, or an adult's session notes).
    const screens = screensFor(ctx.audience, ctx.phones);
    return (
      <LegacySection block={block} tone={tone}>
        <LegacyHead block={block} ctx={ctx} defaults={DEFAULTS} />
        <AppShowcase
          steps={points.map((p) => ({ title: p.title, text: p.text, points: p.points ?? [] }))}
          screens={[<Fragment key="c">{screens.center}</Fragment>, <Fragment key="s">{screens.start}</Fragment>]}
          footer={footer}
        />
      </LegacySection>
    );
  }

  const withImage = block.variant === 'image' && media;
  return (
    <Section block={block} tone={tone} className="sp-app">
      <div className="sp-app-grid" data-image={withImage ? '' : undefined}>
        <div>
          <Head block={block} ctx={ctx} defaults={DEFAULTS} layout="stack" />
          {points.length > 0 ? (
            <ul className="sp-points">
              {points.map((p, i) => (
                <li key={`${p.title}-${i}`} data-reveal="" style={{ '--i': i } as CSSProperties}>
                  <Icon name="check" />
                  <div>
                    <h3>{p.title}</h3>
                    {p.text ? <p>{p.text}</p> : null}
                  </div>
                </li>
              ))}
            </ul>
          ) : null}
          <div className="sp-app-footer">{footer}</div>
        </div>
        {withImage ? <Picture media={media} className="sp-app-media" /> : null}
      </div>
    </Section>
  );
}
