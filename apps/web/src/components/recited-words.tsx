import { useTranslations } from 'next-intl';
import { join } from '@/components/ui';
import { tokensOf, type RecitationCheck } from '@/lib/teach/recitation';

const wordClass = {
  ok: '',
  missing: 'bg-attention-soft text-attention line-through decoration-dotted',
  wrong: 'bg-danger-soft text-danger',
} as const;

/**
 * A recitation as it was heard, word by word: words missed or said wrong
 * (with what was said), words said that are not in the text, each verse's
 * number at its end, and what the colours mean. The teacher's check of a
 * homework recording and a learner's page recited for the hifz review.
 */
export function RecitedWords({ check, large = false }: { check: Pick<RecitationCheck, 'verses' | 'extra'>; large?: boolean }) {
  const t = useTranslations('recited');
  return (
    <>
      <p dir="rtl" lang="ar" className={join('leading-[2.3]', large ? 'text-xl' : 'text-lg')}>
        {tokensOf(check).map((token, i) =>
          token.kind === 'verse' ? (
            <span key={i} className="mx-1 text-xs text-muted">
              ({token.number})
            </span>
          ) : token.kind === 'extra' ? (
            <span key={i}>
              <span className="rounded-sm bg-info-soft px-0.5 italic text-info" title={t('extra')}>
                {token.text}
              </span>{' '}
            </span>
          ) : (
            <span key={i}>
              <span className={join('rounded-sm px-0.5', wordClass[token.state])} title={token.state === 'wrong' ? t('said', { word: token.heard ?? '' }) : token.state === 'missing' ? t('missing') : undefined}>
                {token.text}
                {token.state === 'wrong' && token.heard ? <small className="ms-1 text-[0.7em] text-muted">({token.heard})</small> : null}
              </span>{' '}
            </span>
          ),
        )}
      </p>
      <p className="flex flex-wrap gap-x-3 gap-y-1 text-xs">
        <span className={join('rounded-sm px-1', wordClass.missing)}>{t('missing')}</span>
        <span className={join('rounded-sm px-1', wordClass.wrong)}>{t('wrong')}</span>
        <span className="rounded-sm bg-info-soft px-1 italic text-info">{t('extra')}</span>
      </p>
    </>
  );
}
