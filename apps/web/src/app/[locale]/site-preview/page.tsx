import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { SitePage } from '@/components/site/page/site-page';
import { academySitePreview } from '@/lib/site/server';
import { branding } from '@/lib/viewer';

// Always the latest draft: nothing kept, nothing indexed (site-builder.md, 3.3).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = { robots: { index: false, follow: false } };

type Props = { searchParams: Promise<Record<string, string | string[] | undefined>> };

const one = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value) ?? '';

/**
 * The builder's preview of the academy's draft, shown in /admin's frame: the
 * same page as the public one, with a bar that says it is a draft, a form
 * that sends nothing, and the scroll kept between reloads. The link's token
 * (two hours, from /admin) is the proof; a stale one gets a calm word.
 */
export default async function SitePreview({ searchParams }: Props) {
  const query = await searchParams;
  const token = one(query.t);
  const found = await academySitePreview(token);
  if (typeof found === 'string' || !found.page) {
    const t = await getTranslations('site.preview');
    return (
      <main className="grid min-h-dvh place-items-center bg-paper p-6 text-center">
        <div className="max-w-md">
          <h1 className="text-xl font-bold text-ink">{t(found === 'expired' ? 'expired' : 'invalid')}</h1>
          <p className="mt-2 text-muted">{t('again')}</p>
        </div>
      </main>
    );
  }
  return (
    <SitePage
      site={found}
      page={found.page}
      look={await branding()}
      utm={{}}
      previewToken={token}
      preview={found.preview ?? { revision: found.page.revision, published_revision: null, enabled: true }}
    />
  );
}
