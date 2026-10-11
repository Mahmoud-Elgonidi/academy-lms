import { redirect } from '@/i18n/navigation';

/** The games are on the journey now (each lesson's own); an old link goes there, a homework's practice with it. */
export default async function GamesPage({ params, searchParams }: { params: Promise<{ locale: string }>; searchParams: Promise<{ homework?: string; island?: string }> }) {
  const [{ locale }, asked] = await Promise.all([params, searchParams]);
  const query = new URLSearchParams();
  if (asked.homework) query.set('homework', asked.homework);
  if (asked.island) query.set('island', asked.island);
  const rest = query.toString();
  redirect({ href: rest ? `/family/journey?${rest}` : '/family/journey', locale });
}
