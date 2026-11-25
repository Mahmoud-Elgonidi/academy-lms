/*
 * The academy's page as blocks (docs/architecture/site-builder.md, 8.3): the
 * types GET /v1/site writes under `page` (localized, visible blocks only), and
 * the pure helpers the blocks share: default wording for fields the academy
 * left unset, where a button leads, which ink reads on the accent, and the
 * search engines' structured data. No server-only imports: unit tests and
 * client components read it too.
 */

export type Tone = 'paper' | 'white' | 'tint' | 'brand' | 'night';
export type BlockStyle = { tone: Tone; align: 'start' | 'center'; space: 'compact' | 'normal' | 'roomy'; width: 'narrow' | 'normal' | 'wide' | 'full'; eyebrow: boolean };

export type Media = { url: string; width: number | null; height: number | null; alt: string; mime: string };

export type Action =
  | { kind: 'trial' } | { kind: 'teach' } | { kind: 'sign_in' } | { kind: 'whatsapp' }
  | { kind: 'block'; block: string /* the target's anchor */ }
  | { kind: 'url'; url: string };
export type PageLink = { label: string /* '' = default wording */; action: Action };

export type Inline = { text: string; b?: true; i?: true; href?: string };
export type RichNode =
  | { t: 'p' | 'h3' | 'h4' | 'quote'; c: Inline[] }
  | { t: 'ul' | 'ol'; items: Inline[][] };

export type Heading = { eyebrow: string | null; title: string | null; lead: string | null };
type Base<T extends string, V extends string, D> = { id: string; type: T; variant: V; anchor: string; nav: string | null; style: BlockStyle; data: D };

export type HeroBlock = Base<'hero', 'centered' | 'split' | 'video' | 'phones' | 'minimal', {
  kicker: string | null; title: string; lead: string | null;
  primary: PageLink | null; secondary: PageLink | null; trust: string[] | null;
  media: Media | null; video: Media | null; poster: Media | null;
  media_side: 'start' | 'end'; media_shape: 'rounded' | 'arch' | 'circle' | 'bleed'; scrim: 'light' | 'medium' | 'strong';
  show: { kicker: boolean; lead: boolean; buttons: boolean; trust: boolean; media: boolean };
}>;
export type FeaturesBlock = Base<'features', 'cards' | 'icons' | 'list' | 'numbers', Heading & {
  items: { icon: string; title: string; text: string }[]; columns: 2 | 3 | 4;
}>;
export type SubjectsBlock = Base<'subjects', 'cards' | 'list' | 'chips', Heading & {
  subject_ids: string[]; show_levels: boolean; show_programs: boolean; show_categories: boolean; ask_label: string | null;
}>;
export type TeachersBlock = Base<'teachers', 'cards' | 'portraits' | 'compact', Heading & {
  show_bio: boolean; show_languages: boolean; show_ages: boolean; show_badges: boolean;
}>;
export type PricingBlock = Base<'pricing', 'cards' | 'table', Heading & {
  highlight: string | null; highlight_label: string | null; per_lesson: boolean; note: string | null;
}>;
export type StepsBlock = Base<'steps', 'line' | 'cards', Heading & { items: { title: string; text: string }[] | null }>;
export type AppBlock = Base<'app', 'phones' | 'image' | 'plain', Heading & {
  points: { title: string; text: string }[] | null; show_code: boolean; show_stores: boolean; media: Media | null;
}>;
export type TestimonialsBlock = Base<'testimonials', 'marquee' | 'grid' | 'single', Heading & {
  items: { name: string; role: string | null; quote: string; photo: Media | null; rating: number }[];
}>;
export type FaqBlock = Base<'faq', 'accordion' | 'two_columns', Heading & { items: { q: string; a: string }[] }>;
export type StatsBlock = Base<'stats', 'band' | 'cards', Heading & { items: { value: string; label: string }[] }>;
export type RichTextBlock = Base<'rich_text', 'article' | 'two_columns', Heading & { body: RichNode[] }>;
export type ImageTextBlock = Base<'image_text', 'side' | 'overlap' | 'stacked', Heading & {
  body: RichNode[]; media: Media | null; video: Media | null; poster: Media | null;
  media_side: 'start' | 'end'; bullets: string[]; link: PageLink | null;
}>;
export type GalleryBlock = Base<'gallery', 'grid' | 'masonry' | 'carousel', Heading & {
  items: { media: Media; caption: string | null }[]; ratio: 'auto' | '1:1' | '4:3' | '3:2' | '16:9';
}>;
export type VideoSource =
  | { kind: 'upload'; video: Media; poster: Media | null }
  | { kind: 'youtube'; id: string; poster: Media | null }
  | { kind: 'vimeo'; id: string; poster: Media | null };
export type VideoBlock = Base<'video', 'framed' | 'wide' | 'side', Heading & {
  source: VideoSource; caption: string | null; ratio: '16:9' | '4:3' | '1:1' | '9:16'; autoplay_muted: boolean;
}>;
export type LogosBlock = Base<'logos', 'row' | 'marquee' | 'grid', Heading & {
  items: { media: Media; name: string; url: string | null }[]; mono: boolean;
}>;
/** The academy's own courses, programmes or topics (not the catalogue's subjects). */
export type CardItem = { media: Media | null; label: string | null; title: string; text: string | null; link: PageLink | null };
export type CardsBlock = Base<'cards', 'grid' | 'list' | 'overlay', Heading & { items: CardItem[]; columns: 2 | 3 | 4 }>;
export type CtaBlock = Base<'cta', 'banner' | 'card' | 'split', {
  title: string; lead: string | null; primary: PageLink | null; secondary: PageLink | null; media: Media | null;
}>;
/** Who may ask for a trial from the form (the API sends adults alone on a page for adults). */
export type Bookers = 'both' | 'adults' | 'children';
export type FormBlock = Base<'form', 'split' | 'centered', Heading & {
  points: string[] | null; teach: boolean; whatsapp_button: boolean;
  /** The WhatsApp button's words (null: the page's own). Absent from a page published before it. */
  whatsapp_label?: string | null;
  /** Absent from a page published before it: both. */
  who?: Bookers;
}>;
export type ContactBlock = Base<'contact', 'cards' | 'band', Heading & {
  email: string | null; phone: string | null; address: string | null; hours: string | null; whatsapp: boolean;
}>;
export type SpacerBlock = Base<'spacer', 'space' | 'divider', { size: 'sm' | 'md' | 'lg' }>;

export type PageBlock =
  | HeroBlock | FeaturesBlock | SubjectsBlock | TeachersBlock | PricingBlock | StepsBlock | AppBlock
  | TestimonialsBlock | FaqBlock | StatsBlock | RichTextBlock | ImageTextBlock | GalleryBlock | VideoBlock
  | LogosBlock | CardsBlock | CtaBlock | FormBlock | ContactBlock | SpacerBlock;

export type BlockType = PageBlock['type'];
export type BlockOf<T extends BlockType> = Extract<PageBlock, { type: T }>;

export type PageSettings = {
  audience: 'families' | 'adults';
  fonts: { heading: string; body: string; url: string | null };
  accent: 'primary' | 'attention' | 'info';
  mood: 'light' | 'warm' | 'night';
  corners: 'brand' | 'sharp' | 'soft' | 'round';
  motion: 'full' | 'subtle' | 'none';
  header: { style: 'overlay' | 'solid' | 'minimal'; cta: PageLink | null; sign_in: boolean; language_switch: boolean };
  footer: { text: string | null; links: { label: string; url: string }[]; social: { network: string; url: string }[] };
  whatsapp: string | null;
  floating_whatsapp: boolean;
  seo: { title: string | null; description: string | null; image: Media | null };
};

export type SitePageData = { revision: number; settings: PageSettings; blocks: PageBlock[] };
export type SitePreviewInfo = { revision: number; published_revision: number | null; enabled: boolean };

export const BLOCK_TYPES: readonly BlockType[] = [
  'hero', 'features', 'subjects', 'teachers', 'pricing', 'steps', 'app', 'testimonials', 'faq', 'stats',
  'rich_text', 'image_text', 'gallery', 'video', 'logos', 'cards', 'cta', 'form', 'contact', 'spacer',
];

/** A block of a type this portal draws (a newer server may send one it does not know: it is skipped). */
export function isKnownBlock(block: { type: string }): block is PageBlock {
  return (BLOCK_TYPES as readonly string[]).includes(block.type);
}

// ── Default wording (the (d) fields, section 6.6) ────────────────────────────

/** The messages under `site` that differ for adults (`site.adults.*`). */
const ADULT_KEYS = new Set([
  'trust', 'why.title', 'why.lead', 'subjects.lead', 'steps.items', 'app.lead', 'app.steps', 'testimonials.title', 'join.title', 'join.lead', 'join.leadBook', 'join.points', 'phonesLabel', 'floating.report', 'floating.reportValue', 'pricing.shared',
]);

/** The message key (under `site`) a page for this audience says when the academy left a field unset. */
export function defaultKey(key: string, audience: PageSettings['audience']): string {
  return audience === 'adults' && ADULT_KEYS.has(key) ? `adults.${key}` : key;
}

/**
 * The age groups a teacher's card names on the page (site-builder.md, 9.2):
 * a page for adults never names a child's group, only "adults" when the
 * teacher teaches them; a page for families names them all. The server sends
 * them so already; this holds on the portal too.
 */
export function ageGroupsFor(groups: string[], audience: PageSettings['audience']): string[] {
  return audience === 'adults' ? groups.filter((group) => group === 'adults') : groups;
}

/** What a page says for a field: the academy's words, else the portal's default for the audience. */
export function say<T>(value: T | null | undefined, fallback: () => T): T {
  if (value === null || value === undefined) return fallback();
  if (typeof value === 'string' && value.trim() === '') return fallback();
  return value;
}

/** A list field: null = never set (the default list), [] = the academy cleared it on purpose. */
export function sayList<T>(value: T[] | null | undefined, fallback: () => T[]): T[] {
  return value === null || value === undefined ? fallback() : value;
}

// ── Links ──────────────────────────────────────────────────────────────────────

/** The only addresses a page may link to: https, mailto, tel (+digits), or a page of this portal. */
export function safeUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  const value = url.trim();
  if (value.length > 500) return null;
  if (/^https:\/\/[^\s/?#]+[^\s]*$/i.test(value)) return value;
  if (/^mailto:[^\s@]+@[^\s@]+$/i.test(value)) return value;
  if (/^tel:\+[0-9]{6,15}$/.test(value)) return value;
  if (/^\/(ar|en)(\/[^\s]*)?$/.test(value) && !value.startsWith('//')) return value;
  return null;
}

/** An E.164 number's WhatsApp chat. */
export function whatsappUrl(number: string | null | undefined): string | null {
  const digits = (number ?? '').replace(/[^0-9]/g, '');
  return digits.length >= 6 ? `https://wa.me/${digits}` : null;
}

export type LinkContext = {
  locale: string;
  whatsapp: string | null;
  /** The page has a visible form block (trial and teach buttons need one). */
  form: boolean;
  /** That form shows "teach with us". */
  teach: boolean;
  /** The anchors of the blocks on the page. */
  anchors: ReadonlySet<string>;
};

export type ResolvedLink = { href: string; external: boolean };

/** Where a button leads, or null when it cannot work on this page (it is then not drawn). */
export function resolveAction(action: Action, ctx: LinkContext): ResolvedLink | null {
  switch (action.kind) {
    case 'trial':
      return ctx.form ? { href: '#join', external: false } : null;
    case 'teach':
      return ctx.form && ctx.teach ? { href: '#teach', external: false } : null;
    case 'sign_in':
      return { href: `/${ctx.locale}/sign-in`, external: false };
    case 'whatsapp': {
      const href = whatsappUrl(ctx.whatsapp);
      return href ? { href, external: true } : null;
    }
    case 'block':
      return /^[a-z][a-z0-9-]{0,40}$/.test(action.block) && ctx.anchors.has(action.block) ? { href: `#${action.block}`, external: false } : null;
    case 'url': {
      const href = safeUrl(action.url);
      if (!href) return null;
      return { href, external: href.startsWith('https://') };
    }
    default:
      return null;
  }
}

/** The default label of a button with no words of its own. */
export function defaultLabelKey(action: Action): string {
  switch (action.kind) {
    case 'trial': return 'cta.trial';
    case 'teach': return 'cta.teach';
    case 'sign_in': return 'cta.signIn';
    case 'whatsapp': return 'cta.talk';
    default: return 'blocks.link.more';
  }
}

// ── Colour ─────────────────────────────────────────────────────────────────────

function channel(value: number): number {
  const c = value / 255;
  return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

/** OKLab lightness (0 black … 1 white) of a #rrggbb colour, or null when it is not one. */
export function lightnessOf(hex: string): number | null {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return null;
  const n = parseInt(m[1]!, 16);
  const r = channel((n >> 16) & 255);
  const g = channel((n >> 8) & 255);
  const b = channel(n & 255);
  const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
  const mm = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
  const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
  return 0.2104542553 * l + 0.793617785 * mm - 0.0040720468 * s;
}

/** The ink that reads on a background of this colour: white on dark and mid colours, dark ink on light ones. */
export function onAccent(hex: string): 'light' | 'dark' {
  const lightness = lightnessOf(hex);
  return lightness !== null && lightness > 0.72 ? 'dark' : 'light';
}

/** The branding colour a page's accent names. */
export function accentOf(accent: PageSettings['accent'], tokens: { primary: string; attention: string; info: string }): string {
  return accent === 'attention' ? tokens.attention : accent === 'info' ? tokens.info : tokens.primary;
}

// ── Page shape ─────────────────────────────────────────────────────────────────

/**
 * Two characters (6.2): playful (today's page: night hero with phones,
 * starfield, tilt, marquee) and editorial (courses and training). Motion
 * "full" or a phones hero keeps the playful one.
 */
export function characterOf(page: SitePageData): 'playful' | 'editorial' {
  if (page.settings.motion === 'full') return 'playful';
  return page.blocks.some((b) => b.type === 'hero' && b.variant === 'phones') ? 'playful' : 'editorial';
}

/** The tone a block is drawn in: the page's mood "night" turns paper blocks to night. */
export function toneOf(block: PageBlock, mood: PageSettings['mood']): Tone {
  return mood === 'night' && block.style.tone === 'paper' ? 'night' : block.style.tone;
}

/** Whether a tone is dark (light ink on it). */
export function isDark(tone: Tone, accentInk: 'light' | 'dark'): boolean {
  return tone === 'night' || (tone === 'brand' && accentInk === 'light');
}

/** The header's menu: blocks that carry a menu label, in page order. */
export function navOf(blocks: PageBlock[]): { href: string; label: string }[] {
  return blocks.filter((b) => b.nav && b.nav.trim()).map((b) => ({ href: `#${b.anchor}`, label: b.nav!.trim() }));
}

/** An image's aspect ratio from its sizes ("1600 / 900"), or the fallback. */
export function ratioOf(media: Media | null | undefined, fallback = '16 / 9'): string {
  return media?.width && media.height ? `${media.width} / ${media.height}` : fallback;
}

/** A ratio field ("4:3") as CSS ("4 / 3"); null for auto. */
export function cssRatio(ratio: string): string | null {
  const m = /^(\d+):(\d+)$/.exec(ratio);
  return m ? `${m[1]} / ${m[2]}` : null;
}

/** The player a YouTube or Vimeo video opens in, once clicked (no cookies, no tracking where the provider allows). */
export function embedUrl(source: VideoSource): string | null {
  if (source.kind === 'youtube') return /^[A-Za-z0-9_-]{11}$/.test(source.id) ? `https://www.youtube-nocookie.com/embed/${source.id}?autoplay=1&rel=0` : null;
  if (source.kind === 'vimeo') return /^[0-9]{6,12}$/.test(source.id) ? `https://player.vimeo.com/video/${source.id}?autoplay=1&dnt=1` : null;
  return null;
}

/** Splits a list in two halves for a two-column layout that reads down each column. */
export function halves<T>(items: T[]): [T[], T[]] {
  const cut = Math.ceil(items.length / 2);
  return [items.slice(0, cut), items.slice(cut)];
}

// ── Structured data (6.7) ──────────────────────────────────────────────────────

/** JSON for a <script type="application/ld+json">: "<" escaped, so no text can close the script. */
export function jsonLd(value: unknown): string {
  return JSON.stringify(value).replace(/</g, '\\u003c').replace(/\u2028/g, '\\u2028').replace(/\u2029/g, '\\u2029');
}

/** The academy and its questions, for search engines. */
export function structuredData(page: SitePageData, academy: { name: string }, origin: string, locale: string, logo: string | null): object[] {
  const absolute = (url: string | null | undefined) => (!url ? undefined : url.startsWith('http') ? url : `${origin}${url}`);
  const organization: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'EducationalOrganization',
    name: academy.name,
    url: `${origin}/${locale}`,
  };
  if (logo) organization.logo = absolute(logo);
  const sameAs = page.settings.footer.social.map((s) => safeUrl(s.url)).filter((u): u is string => Boolean(u));
  if (sameAs.length > 0) organization.sameAs = sameAs;
  const out: object[] = [organization];
  const faq = page.blocks.find((b): b is FaqBlock => b.type === 'faq' && b.data.items.length > 0);
  if (faq) {
    out.push({
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: faq.data.items.map((item) => ({ '@type': 'Question', name: item.q, acceptedAnswer: { '@type': 'Answer', text: item.a } })),
    });
  }
  return out;
}

/** Who the form takes: the block's choice, and adults alone on a page for adults. */
export function bookersOf(block: FormBlock, audience: PageSettings['audience']): Bookers {
  if (audience === 'adults') return 'adults';
  return block.data.who ?? 'both';
}
