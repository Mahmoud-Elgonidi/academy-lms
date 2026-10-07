import { redirect } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { signOut } from '@/app/[locale]/sign-in/actions';
import { Button, Card } from '@/components/ui';
import { homeOf } from '@/lib/session';
import { branding, me } from '@/lib/viewer';

/** Students and guardians: their area on this site comes next; until then, the one they have (/my). */
export default async function Family({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const [person, look, t, common] = await Promise.all([me(), branding(), getTranslations('family'), getTranslations('common')]);
  if (homeOf(person.roles) !== 'family') redirect(`/${locale}`);
  return (
    <div className="mx-auto grid min-h-dvh max-w-md place-items-center px-5">
      <Card className="appear w-full text-center">
        <p className="text-sm text-muted">{locale === 'ar' ? look.name.ar : look.name.en}</p>
        <h1 className="mt-1 text-2xl font-bold">{t('title')}</h1>
        <p className="mt-3 text-muted">{t('soon')}</p>
        <a href="/my" className="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-control bg-brand px-5 font-semibold text-white hover:bg-brand-strong">
          {t('open')}
        </a>
        <form action={signOut} className="mt-3">
          <Button variant="quiet" wide>{common('signOut')}</Button>
        </form>
      </Card>
    </div>
  );
}
