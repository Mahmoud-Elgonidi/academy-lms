import { useTranslations } from 'next-intl';
import { Badge, join } from '@/components/ui';
import { tokensOf, type RecitationCheck as Check } from '@/lib/teach/recitation';

const wordClass = {
  ok: '',
  missing: 'bg-attention-soft text-attention line-through decoration-dotted',
  wrong: 'bg-danger-soft text-danger',
} as const;

/**
 * One recording's check, word by word, on the teacher's marking page alone:
 * words missed or said wrong (with what was said), words said that are not
 * in the text, and what was heard. A help while marking, never a mark.
 */
export function RecitationCheck({ check }: { check: Check }) {
  const t = useTranslations('teach');
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
          <p dir="rtl" lang="ar" className="text-lg leading-[2.3]">
            {tokensOf(check).map((token, i) =>
              token.kind === 'verse' ? (
                <span key={i} className="mx-1 text-xs text-muted">
                  ({token.number})
                </span>
              ) : token.kind === 'extra' ? (
                <span key={i}>
                  <span className="rounded-sm bg-info-soft px-0.5 italic text-info" title={t('reciteExtra')}>
                    {token.text}
                  </span>{' '}
                </span>
              ) : (
                <span key={i}>
                  <span className={join('rounded-sm px-0.5', wordClass[token.state])} title={token.state === 'wrong' ? t('reciteSaid', { word: token.heard ?? '' }) : token.state === 'missing' ? t('reciteMissing') : undefined}>
                    {token.text}
                    {token.state === 'wrong' && token.heard ? <small className="ms-1 text-[0.7em] text-muted">({token.heard})</small> : null}
                  </span>{' '}
                </span>
              ),
            )}
          </p>
          <p className="flex flex-wrap gap-x-3 gap-y-1 text-xs">
            <span className={join('rounded-sm px-1', wordClass.missing)}>{t('reciteMissing')}</span>
            <span className={join('rounded-sm px-1', wordClass.wrong)}>{t('reciteWrong')}</span>
            <span className="rounded-sm bg-info-soft px-1 italic text-info">{t('reciteExtra')}</span>
          </p>
          {check.heard ? (
            <details className="text-sm">
              <summary className="cursor-pointer text-muted">{t('reciteHeard')}</summary>
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
