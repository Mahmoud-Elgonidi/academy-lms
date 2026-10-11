import { redirect } from '@/i18n/navigation';

/** The week's missions and its chest are on the journey now; an old link goes there. */
export default async function ChallengesPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  redirect({ href: '/family/journey', locale });
}
