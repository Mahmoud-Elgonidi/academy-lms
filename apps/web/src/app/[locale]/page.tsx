import type { Metadata } from 'next';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { PlatformPage } from '@/components/site/platform-page';
import { homeOf, SESSION_COOKIE } from '@/lib/session';
import { academyHere, platformSite } from '@/lib/site/server';
import { me } from '@/lib/viewer';

type Props = { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> };

const one = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

/** On the platform's own address (no academy), the platform's page; elsewhere the layout's title stands. */
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  if ((await cookies()).has(SESSION_COOKIE) || (await academyHere())) return {};
  const { locale } = await params;
  const site = await platformSite();
  if (!site) return {};
  return {
    title: site.content.seo.title || site.content.hero.title,
    description: site.content.seo.description || site.content.hero.lead,
    alternates: { canonical: `/${locale}`, languages: { ar: '/ar', en: '/en' } },
  };
}

/**
 * Each person signed in starts in their own area: teachers teach, families
 * have theirs, staff use the back office. A visitor sees the page of the
 * address they came to: the platform's on its own address; on an
 * academy's, signing in.
 */
export default async function Home({ params, searchParams }: Props) {
  const { locale } = await params;
  if ((await cookies()).has(SESSION_COOKIE)) {
    const home = homeOf((await me()).roles);
    redirect(home === 'staff' ? '/admin' : `/${locale}/${home}`);
  }
  if (!(await academyHere())) {
    const site = await platformSite();
    if (site) {
      const query = await searchParams;
      return (
        <PlatformPage
          site={site}
          path=""
          chosen={one(query.plan)}
          utm={{ utm_source: one(query.utm_source), utm_medium: one(query.utm_medium), utm_campaign: one(query.utm_campaign) }}
        />
      );
    }
  }
  redirect(`/${locale}/sign-in`);
}
