import { useTranslations } from 'next-intl';
import { RecitedWords } from '@/components/recited-words';
import { Badge } from '@/components/ui';
import type { RecitationCheck as Check } from '@/lib/teach/recitation';

/**
 * One recording's check, word by word, on the teacher's marking page alone:
 * words missed or said wrong (with what was said), words said that are not
 * in the text, and what was heard. A help while marking, never a mark.
 */
export function RecitationCheck({ check }: { check: Check }) {
  const t = useTranslations('teach');
  const words = useTranslations('recited');
  const tone = check.status === 'ready' ? 'brand' : check.status === 'failed' ? 'danger' : 'muted';

  return (
    <section className="mt-2 space-y-2 border-t border-dashed border-line pt-3" aria-label={t('reciteTitle')}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm font-semibold">{t('reciteTitle')}</p>
        <Badge tone={tone}>{check.status_label}</Badge>
      </div>
      {check.status === 'failed' && check.failure ? <p className="text-sm text-muted">{check.failure}</p> : null}
      {check.status === 'ready' ? (
        <>
          {check.summary_label ? <p className="text-sm font-semibold">{check.summary_label}</p> : null}
          <RecitedWords check={check} />
          {check.heard ? (
            <details className="text-sm">
              <summary className="cursor-pointer text-muted">{words('heard')}</summary>
              <p dir="rtl" lang="ar" className="mt-1">
                {check.heard}
              </p>
            </details>
          ) : null}
        </>
      ) : null}
      <p className="text-xs text-muted">{check.note}</p>
    </section>
  );
}
