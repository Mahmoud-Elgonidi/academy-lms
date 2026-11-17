import { headers } from 'next/headers';
import { getLocale, getTranslations } from 'next-intl/server';
import { NONCE_HEADER } from '@/lib/csp';
import { accentOf, isKnownBlock, onAccent, structuredData, type SitePageData, type SitePreviewInfo } from '@/lib/site/page';
import type { AcademySite } from '@/lib/site/server';
import type { Branding } from '@/lib/viewer';
import { JsonLd, PreviewScript } from '../page-scripts';
import type { PhoneWords } from '../phones';
import { SitePageView } from './view';
import type { Say } from './context';

/**
 * An academy's page as it built it in /admin (GET /v1/site `page`): its
 * blocks in order between its header and footer, in its fonts and colours,
 * with the search engines' data. In the builder's preview (`preview`) a bar
 * says so, the form sends nothing and the scroll is kept between reloads.
 */
export async function SitePage({ site, page, look, utm, chosenSubject, preview = null }: {
  site: AcademySite;
  page: SitePageData;
  look: Branding;
  utm: Record<string, string | undefined>;
  chosenSubject?: string;
  preview?: SitePreviewInfo | null;
}) {
  const [t, all, locale, incoming] = await Promise.all([getTranslations('site'), getTranslations(), getLocale(), headers()]);
  const nonce = incoming.get(NONCE_HEADER) ?? undefined;
  const host = incoming.get('x-forwarded-host') ?? incoming.get('host') ?? 'localhost';
  const origin = `${incoming.get('x-forwarded-proto') ?? 'https'}://${host}`;
  const known = { ...page, blocks: page.blocks.filter(isKnownBlock) };
  const accent = accentOf(page.settings.accent, look.tokens);
  return (
    <>
      {page.settings.fonts.url ? (
        <>
          <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
          <link rel="stylesheet" href={page.settings.fonts.url} precedence="default" />
        </>
      ) : null}
      <SitePageView
        site={site}
        page={known}
        look={look}
        locale={locale}
        t={t as unknown as Say}
        phones={all.raw('sitePhones') as PhoneWords}
        accent={accent}
        accentInk={onAccent(accent)}
        preview={preview}
        utm={utm}
        chosenSubject={chosenSubject}
      />
      {preview ? <PreviewScript revision={preview.revision} nonce={nonce} /> : <JsonLd data={structuredData(known, site.academy, origin, locale, look.logo_url ?? null)} nonce={nonce} />}
    </>
  );
}
