import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { AddChildForm } from '@/components/family/add-child';
import { Icon } from '@/components/icons';
import { Notice, buttonLook, join } from '@/components/ui';
import { Link } from '@/i18n/navigation';
import { read } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import { isGuardian, seesMoney } from '@/lib/family/rules';
import type { Consents } from '@/lib/family/types';

type Props = { searchParams: Promise<Record<string, string | string[] | undefined>> };

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('addChild');
  return { title: t('metaTitle') };
}

/**
 * «إضافة طفل» (docs/architecture/02, section 27, J5a): a guardian adds a
 * child of theirs (name, birth, optionally whether a girl or a boy) with
 * their consent to the academy's wording, then goes straight to choosing the
 * child's course (/family/start?learner=…). Guardians only, in the classic
 * look; anyone else reads why, calmly, with the way back. Opened from the
 * start flow (?from=start) it goes back there.
 */
export default async function AddChild({ searchParams }: Props) {
  const [params, t, fam] = await Promise.all([searchParams, getTranslations('addChild'), family()]);
  const fromStart = params.from === 'start';
  const back = fromStart ? '/family/start' : '/family';
  // A guardian follows children (or none yet); an adult studying for themselves alone is no guardian (the API says so again).
  const guardian = seesMoney(fam.stage) && (isGuardian(fam.learners) || fam.learners.length === 0);
  if (!guardian) {
    return (
      <section className="mx-auto max-w-xl rounded-card border border-line bg-white p-6 text-center" data-add-child="closed">
        <span className="mx-auto grid size-14 place-items-center rounded-full bg-info-soft text-info" aria-hidden="true"><Icon name="people" size={26} /></span>
        <h1 className="mt-3 text-xl font-bold">{t('metaTitle')}</h1>
        <p className="mt-2 leading-7 text-ink/80">{t('notGuardian')}</p>
        <Link href="/family" className={join(buttonLook('quiet'), 'mt-5 min-h-10 px-4 text-sm')}>{t('back')}</Link>
      </section>
    );
  }

  const consents = await read<Consents>('/v1/family/consents');
  const wording = consents.ok ? (consents.data.new_child ?? null) : null;
  const now = new Date();
  const today = now.toISOString().slice(0, 10);

  return (
    <div className="mx-auto max-w-2xl space-y-5" data-add-child="form">
      <Link href={back} className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-brand">
        <Icon name="chevron" size={18} className="ltr:-scale-x-100" />
        {t('back')}
      </Link>
      <header>
        <p className="text-sm font-semibold text-brand-strong">{t('kicker')}</p>
        <h1 className="mt-1 text-2xl font-bold sm:text-3xl">{t('title')}</h1>
        <p className="mt-2 leading-7 text-ink/80">{t('lead')}</p>
      </header>
      {wording ? (
        <AddChildForm wording={wording} thisYear={now.getUTCFullYear()} today={today} />
      ) : (
        <Notice tone="attention">{t('noWording')}</Notice>
      )}
    </div>
  );
}
