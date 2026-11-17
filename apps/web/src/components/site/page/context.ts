import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';
import { defaultKey, type LinkContext, type PageSettings, type SitePageData } from '@/lib/site/page';
import type { PhoneWords } from '../phones';

/** The page's messages under `site` (next-intl's translator, on the server or in a test). */
export type Say = {
  (key: string, values?: Record<string, string | number>): string;
  raw(key: string): unknown;
  has(key: string): boolean;
};

/** Everything a block needs besides its own data, made once per page by SitePage. */
export type PageCtx = {
  site: AcademySite;
  page: SitePageData;
  look: Branding;
  locale: string;
  t: Say;
  phones: PhoneWords;
  audience: PageSettings['audience'];
  character: 'playful' | 'editorial';
  /** Ink on the accent colour (brand tone, buttons). */
  accentInk: 'light' | 'dark';
  links: LinkContext;
  /** The block whose title is the page's one h1 (null: a hidden h1 with the academy's name). */
  h1: string | null;
  preview: boolean;
  utm: Record<string, string | undefined>;
  chosenSubject?: string;
};

/** A message for this page's audience (site.adults.* where it differs). */
export function audienceSay(ctx: PageCtx, key: string, values?: Record<string, string | number>): string {
  return ctx.t(defaultKey(key, ctx.audience), values);
}

/** A list message for this page's audience. */
export function audienceRaw<T>(ctx: PageCtx, key: string): T {
  return ctx.t.raw(defaultKey(key, ctx.audience)) as T;
}

/** The list separator of the page's language ("، " or ", "). */
export function comma(ctx: Pick<PageCtx, 'locale'>): string {
  return ctx.locale === 'ar' ? '، ' : ', ';
}
