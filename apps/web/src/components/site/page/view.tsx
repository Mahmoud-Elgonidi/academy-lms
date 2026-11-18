import type { CSSProperties, ReactNode } from 'react';
import { characterOf, isDark, toneOf, type FormBlock, type PageBlock, type SitePageData, type SitePreviewInfo } from '@/lib/site/page';
import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';
import { Reveal } from '../motion';
import type { PhoneWords } from '../phones';
import { Block } from './block';
import { FloatingWhatsApp, PageFooter, PageHeader, type HeaderMode } from './chrome';
import type { PageCtx, Say } from './context';
import '../site.css';
import './page.css';

const DEFAULT_TITLES: Partial<Record<PageBlock['type'], string>> = {
  features: 'why.title', subjects: 'subjects.title', teachers: 'teachers.title', pricing: 'pricing.title', steps: 'steps.title',
  app: 'app.title', testimonials: 'testimonials.title', faq: 'faq.title', form: 'join.title', contact: 'blocks.contact.title',
};

/** The block whose title is the page's h1 (6.5): the hero; else the first block with a title; else none (a hidden h1). */
export function h1Of(blocks: PageBlock[]): string | null {
  const hero = blocks.find((b) => b.type === 'hero');
  if (hero) return hero.id;
  for (const block of blocks) {
    if (block.type === 'spacer') continue;
    if (block.type === 'cta') return block.id;
    if ('title' in block.data && (block.data.title || DEFAULT_TITLES[block.type])) return block.id;
  }
  return null;
}

/** How the header sits over the first block (decisions, 9.1). */
export function headerModeOf(page: SitePageData, accentInk: 'light' | 'dark'): HeaderMode {
  const style = page.settings.header.style;
  if (style === 'minimal') return 'minimal';
  if (style === 'solid') return 'solid';
  const first = page.blocks[0];
  if (!first || first.type !== 'hero') return 'solid';
  if (first.variant === 'phones' || (first.variant === 'video' && first.data.video)) return 'night';
  return isDark(toneOf(first, page.settings.mood), accentInk) ? 'night' : 'light';
}

const RADII = { sharp: '0px', soft: '0.75rem', round: '1.75rem' } as const;

/** The page itself, from its resolved inputs (SitePage reads them on the server; tests pass their own). */
export function SitePageView({ site, page, look, locale, t, phones, accent, accentInk, preview, previewToken, utm, chosenSubject }: {
  site: AcademySite;
  page: SitePageData;
  look: Branding;
  locale: string;
  t: Say;
  phones: PhoneWords;
  accent: string;
  accentInk: 'light' | 'dark';
  preview: SitePreviewInfo | null;
  previewToken?: string;
  utm: Record<string, string | undefined>;
  chosenSubject?: string;
}) {
  const { settings, blocks } = page;
  const form = blocks.find((b): b is FormBlock => b.type === 'form');
  const ctx: PageCtx = {
    site,
    page,
    look,
    locale,
    t,
    phones,
    audience: settings.audience,
    character: characterOf(page),
    accentInk,
    links: {
      locale,
      whatsapp: settings.whatsapp ?? site.whatsapp,
      form: Boolean(form),
      teach: Boolean(form?.data.teach),
      anchors: new Set(blocks.map((b) => b.anchor)),
    },
    h1: h1Of(blocks),
    preview: preview !== null,
    previewToken,
    utm,
    chosenSubject,
  };
  const mode = headerModeOf(page, accentInk);
  const style: Record<string, string> = { '--accent': accent };
  if (settings.corners !== 'brand') style['--sp-radius'] = RADII[settings.corners];
  if (settings.fonts.url) {
    style['--sp-head'] = `'${settings.fonts.heading.replace(/'/g, '')}'`;
    style['--sp-body'] = `'${settings.fonts.body.replace(/'/g, '')}'`;
  }
  const firstHero = blocks[0]?.type === 'hero';
  const body: ReactNode = (
    <>
      <PageHeader ctx={ctx} mode={mode} />
      <main id="main">
        {ctx.h1 === null ? <h1 className="ls-sr">{site.academy.name}</h1> : null}
        {blocks.map((block) => <Block key={block.id} block={block} ctx={ctx} />)}
      </main>
      <PageFooter ctx={ctx} />
      <FloatingWhatsApp ctx={ctx} />
    </>
  );
  return (
    <div
      className="ls ls-academy sp-page"
      data-character={ctx.character}
      data-motion={settings.motion}
      data-mood={settings.mood}
      data-corners={settings.corners}
      data-accent-ink={accentInk}
      data-header={mode}
      data-fonts={settings.fonts.url ? '' : undefined}
      data-preview={preview ? '' : undefined}
      data-first={firstHero ? 'hero' : 'block'}
      data-hero-width={firstHero && blocks[0]!.style.width === 'wide' ? 'wide' : undefined}
      style={style as CSSProperties}
    >
      <a className="sp-skip" href="#main">{t('blocks.skip')}</a>
      {preview ? (
        <div className="sp-preview-bar" role="status">
          <span>{t(preview.revision === preview.published_revision ? 'preview.live' : 'preview.bar')}</span>
          {!preview.enabled ? <span className="sp-preview-off">{t('preview.off')}</span> : null}
        </div>
      ) : null}
      {settings.motion === 'none' ? <div>{body}</div> : <Reveal>{body}</Reveal>}
    </div>
  );
}
