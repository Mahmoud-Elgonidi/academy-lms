import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { Shell } from '@/components/teach/shell';
import { branding, me } from '@/lib/viewer';

/** The teacher's portal: only teachers; anyone else goes to their own area. */
export default async function TeachLayout({ children, params }: { children: ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const [person, look] = await Promise.all([me(), branding()]);
  if (!person.roles.includes('teacher')) redirect(`/${locale}`);
  return (
    <Shell person={person} look={look} locale={locale}>
      {children}
    </Shell>
  );
}
