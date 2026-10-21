import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { PlatformPage } from '@/components/site/platform-page';
import { platformSite } from '@/lib/site/server';

type Props = { params: Promise<{ locale: string }>; searchParams: Promise<Record<string, string | string[] | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const site = await platformSite();
  if (!site) return {};
  const seo = site.content.seo;
  return {
    title: seo.title || site.content.hero.title,
    description: seo.description || site.content.hero.lead,
    alternates: { canonical: `/${locale}/platform`, languages: { ar: '/ar/platform', en: '/en/platform' } },
    openGraph: { title: seo.title || site.content.hero.title, description: seo.description || site.content.hero.lead, type: 'website' },
  };
}

const one = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

/** The platform's page at its own address, whatever academy the host may also serve (the console's "Open the page"). */
export default async function PlatformRoute({ searchParams }: Props) {
  const site = await platformSite();
  if (!site) notFound();
  const query = await searchParams;
  return (
    <PlatformPage
      site={site}
      path="/platform"
      chosen={one(query.plan)}
      utm={{ utm_source: one(query.utm_source), utm_medium: one(query.utm_medium), utm_campaign: one(query.utm_campaign) }}
    />
  );
}
