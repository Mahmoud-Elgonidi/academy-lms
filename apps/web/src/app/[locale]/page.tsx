import type { Metadata } from 'next';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { AcademyPage } from '@/components/site/academy-page';
import { PlatformPage } from '@/components/site/platform-page';
import { homeOf, SESSION_COOKIE } from '@/lib/session';
import { academyHere, academySite, platformSite } from '@/lib/site/server';
import { branding, me } from '@/lib/viewer';

type Props = { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> };

const one = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

/** A visitor's page names itself: the academy's (its words from /admin), or the platform's. */
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  if ((await cookies()).has(SESSION_COOKIE)) return {};
  const { locale } = await params;
  const alternates = { canonical: `/${locale}`, languages: { ar: '/ar', en: '/en' } };
  const academy = await academySite();
  if (academy) {
    return {
      title: academy.content.seo.title || academy.academy.name,
      description: academy.content.seo.description || academy.content.hero.lead,
      alternates,
    };
  }
  if (await academyHere()) return {};
  const site = await platformSite();
  if (!site) return {};
  return {
    title: site.content.seo.title || site.content.hero.title,
    description: site.content.seo.description || site.content.hero.lead,
    alternates,
  };
}

/**
 * Each person signed in starts in their own area: teachers teach, families
 * have theirs, staff use the back office. A visitor sees the page of the
 * address they came to: an academy's own page while it shows one (else
 * signing in), and the platform's on its own address.
 */
export default async function Home({ params, searchParams }: Props) {
  const { locale } = await params;
  if ((await cookies()).has(SESSION_COOKIE)) {
    const home = homeOf((await me()).roles);
    redirect(home === 'staff' ? '/admin' : `/${locale}/${home}`);
  }
  const query = await searchParams;
  const utm = { utm_source: one(query.utm_source), utm_medium: one(query.utm_medium), utm_campaign: one(query.utm_campaign) };
  const academy = await academySite();
  if (academy) {
    return <AcademyPage site={academy} look={await branding()} utm={utm} chosenSubject={one(query.subject)} />;
  }
  if (!(await academyHere())) {
    const site = await platformSite();
    if (site) return <PlatformPage site={site} path="" chosen={one(query.plan)} utm={utm} />;
  }
  redirect(`/${locale}/sign-in`);
}
