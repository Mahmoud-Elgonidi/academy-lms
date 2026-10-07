import { getTranslations } from 'next-intl/server';
import { Link } from '@/i18n/navigation';

export default async function NotFound() {
  const t = await getTranslations('errors');
  return (
    <div className="mx-auto grid min-h-[60dvh] max-w-md place-items-center px-6 text-center">
      <div className="appear">
        <h1 className="text-2xl font-bold">{t('notFoundTitle')}</h1>
        <p className="mt-2 text-muted">{t('notFoundLead')}</p>
        <Link href="/" className="mt-6 inline-flex min-h-11 items-center rounded-control bg-brand px-5 font-semibold text-white hover:bg-brand-strong">
          {t('home')}
        </Link>
      </div>
    </div>
  );
}
