import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { FamilyShell } from '@/components/family/shell';
import { family } from '@/lib/family/context';
import { homeOf } from '@/lib/session';
import { branding, me } from '@/lib/viewer';
import { baloo, rubik } from './fonts';

/** Students and guardians: their area, in the look of the person signed in. Anyone else goes to their own. */
export default async function FamilyLayout({ children, params }: { children: ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const person = await me();
  if (homeOf(person.roles) !== 'family') redirect(`/${locale}`);
  const [look, fam] = await Promise.all([branding(), family()]);
  return (
    <FamilyShell person={person} look={look} locale={locale} fam={fam} fontClasses={`${baloo.variable} ${rubik.variable}`}>
      {children}
    </FamilyShell>
  );
}
