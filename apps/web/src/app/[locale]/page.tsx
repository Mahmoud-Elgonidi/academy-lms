import { redirect } from 'next/navigation';
import { homeOf } from '@/lib/session';
import { me } from '@/lib/viewer';

/** Each person starts in their own area: teachers teach, families have theirs, staff use the back office. */
export default async function Home({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const home = homeOf((await me()).roles);
  redirect(home === 'staff' ? '/admin' : `/${locale}/${home}`);
}
