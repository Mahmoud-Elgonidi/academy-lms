import { useTranslations } from 'next-intl';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Progress, join } from '@/components/ui';
import { wirdStatus } from '@/lib/hifz/hifz';
import type { JourneyHifz } from '@/lib/hifz/types';

/**
 * Today's hifz review on the journey (and a guardian's home): pages left
 * today, or done; it opens the hifz map with the day's pages. Only while the
 * learner has pages on their map (the journey's `hifz`).
 */
export function HifzCard({ hifz, kids = false }: { hifz: JourneyHifz; kids?: boolean }) {
  const t = useTranslations('hifz');
  const status = wirdStatus(hifz);
  const line =
    status.state === 'left' ? (kids ? t('kids.left', { n: status.left }) : t('wirdLeft', { n: status.left })) : status.state === 'done' ? (kids ? t('kids.wirdDone') : t('wirdDone')) : t('wirdNone');
  return (
    <Link href="/family/hifz" className={join('lift block rounded-card border bg-white p-4', status.state === 'left' ? 'border-brand-line' : 'border-line')} data-hifz-card={status.state}>
      <span className="flex items-center gap-3">
        <span className={join('grid size-11 shrink-0 place-items-center rounded-control', status.state === 'done' ? 'bg-brand text-white' : 'bg-brand-soft text-brand-strong')} aria-hidden="true">
          <Icon name={status.state === 'done' ? 'check' : 'book'} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block font-semibold">{t('card')}</span>
          <span className="block text-sm text-muted">{line}</span>
        </span>
        <Icon name="chevron" size={18} className="shrink-0 text-muted rtl:-scale-x-100" />
      </span>
      {status.state === 'left' && status.done > 0 ? (
        <span className="mt-3 block">
          <Progress value={status.percent} label={line} />
        </span>
      ) : null}
    </Link>
  );
}
