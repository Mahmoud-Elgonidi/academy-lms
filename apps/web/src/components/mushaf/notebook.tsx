import { useTranslations } from 'next-intl';
import { Fragment } from 'react';
import { quranFont } from '@/components/mushaf/font';
import { SurahName } from '@/components/mushaf/text';
import { Badge, Empty, SectionTitle, join } from '@/components/ui';
import { kindLabels, notebookClass, notebookTokens } from '@/lib/mushaf/mushaf';
import type { Notebook, NotebookMark } from '@/lib/mushaf/types';

/**
 * The mistakes notebook (GET learning/mistakes, and the teacher's
 * GET teacher/students/{enrollment}/mistakes): the words marked in the last
 * six months, a card for each surah with how many, each verse as the mushaf
 * writes it with its marked words highlighted (what they were, how many
 * times, when last; read out too). A child's look has its own words and
 * gentle colours, the academy's own rather than danger.
 */
export function MistakesNotebook({ book, title, lead, empty, kids = false, teacher = false }: {
  book: Notebook;
  title: string;
  lead: string;
  empty: string;
  /** The kids' look: their words, and every word in the academy's gentle colour. */
  kids?: boolean;
  /** On the teacher's student page: a heading of its own, as the hifz map has. */
  teacher?: boolean;
}) {
  const t = useTranslations('mushaf');
  const tip = (mark: NotebookMark) =>
    kids
      ? t('kids.markTitle', { times: mark.times, when: mark.last_at.label })
      : t('markTitle', { kinds: kindLabels(mark.kinds, book.kinds).join('، '), times: mark.times, when: mark.last_at.label });

  return (
    <section aria-labelledby="mistakes" className={join(quranFont.variable, teacher && 'mt-8')}>
      {teacher ? (
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
          <h2 id="mistakes" className="text-xl font-bold">
            {title}
          </h2>
          <span className="text-sm text-muted">{t('words', { n: book.words })}</span>
        </div>
      ) : (
        <SectionTitle id="mistakes" trailing={book.words > 0 ? t('words', { n: book.words }) : null}>
          {title}
        </SectionTitle>
      )}
      <p className="-mt-1 mb-3 text-sm text-muted">{lead}</p>
      {book.surahs.length === 0 ? (
        <Empty>{empty}</Empty>
      ) : (
        <ul className="grid gap-3 lg:grid-cols-2">
          {book.surahs.map((s) => (
            <li key={s.surah} className="rounded-card border border-line bg-white p-4 sm:p-5">
              <div className="flex items-center justify-between gap-3">
                <h3 className="text-xl text-brand-strong">
                  <SurahName surah={{ number: s.surah, name_ar: s.name_ar, name_en: s.name_en }} />
                </h3>
                <Badge tone={kids ? 'brand' : 'attention'}>{t('words', { n: s.words })}</Badge>
              </div>
              <ol className="mt-2 divide-y divide-line">
                {s.verses.map((v) => (
                  <li key={v.verse} className="py-2">
                    <p dir="rtl" lang="ar" className="font-quran text-[1.45rem] leading-[2.2] text-ink">
                      {notebookTokens(v).map((token, i) =>
                        token.kind === 'end' ? (
                          <span key={`end-${i}`} className="whitespace-nowrap text-[0.85em] text-brand-strong">
                            {token.label}
                          </span>
                        ) : token.mark ? (
                          <Fragment key={token.place}>
                            <mark className={join('rounded-[0.3em] px-[0.08em]', notebookClass(token.mark.kinds, kids))} title={tip(token.mark)}>
                              {token.text}
                              <span className="sr-only"> ({tip(token.mark)})</span>
                            </mark>{' '}
                          </Fragment>
                        ) : (
                          <Fragment key={token.place}>{token.text} </Fragment>
                        ),
                      )}
                    </p>
                  </li>
                ))}
              </ol>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
