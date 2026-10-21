import { useLocale, useTranslations } from 'next-intl';
import { Fragment } from 'react';
import { join } from '@/components/ui';
import type { SurahRef } from '@/lib/hifz/types';
import { earlierClass, markClass, type MushafPiece, type MushafWord } from '@/lib/mushaf/mushaf';

/**
 * A surah's name as a heading of the Quran's text: "سورة البقرة" in the
 * mushaf's font; in English its English name, with the Arabic beside it.
 */
export function SurahName({ surah, className }: { surah: SurahRef; className?: string }) {
  const t = useTranslations('mushaf');
  const locale = useLocale();
  if (locale === 'ar' || !surah.name_en) {
    return <span className={join('font-quran', className)}>{t('surahHead', { name: surah.name_ar })}</span>;
  }
  return (
    <span className={join('inline-flex flex-wrap items-baseline justify-center gap-x-2', className)}>
      <span dir="ltr" lang="en" className="font-sans text-[0.6em] font-semibold">
        {t('surahHead', { name: surah.name_en })}
      </span>
      <span lang="ar" className="font-quran">
        {surah.name_ar}
      </span>
    </span>
  );
}

/**
 * A page of the shared mushaf as it is drawn: right to left in Amiri Quran,
 * each surah's header before its first verse (and the basmala under it, from
 * the platform's copy), each word its own element in its mark's colour
 * (earlier lessons' marks a faint dotted underline), each verse ending with
 * its number. The teacher's words are buttons while they may mark; anyone
 * else's are plain words.
 */
export function MushafText({ pieces, kinds, onWord, openWord }: {
  pieces: MushafPiece[];
  kinds: Record<string, string>;
  /** The teacher may mark: a word opens its menu. */
  onWord?: (word: MushafWord, button: HTMLButtonElement) => void;
  /** The word whose menu is open. */
  openWord?: string | null;
}) {
  const t = useTranslations('mushaf');
  const about = (w: MushafWord) =>
    [w.mark ? t('nowMark', { kind: kinds[w.mark] ?? w.mark }) : null, w.earlier ? t('earlierMark', { kind: kinds[w.earlier.kind] ?? w.earlier.kind, times: w.earlier.times }) : null]
      .filter(Boolean)
      .join(' · ');

  return (
    <div dir="rtl" lang="ar" className="font-quran text-[1.6rem] leading-[2.35] text-ink sm:text-[1.75rem]">
      {pieces.map((piece) => {
        if (piece.kind === 'surah') {
          return (
            <div key={piece.key} role="heading" aria-level={3} className="my-3 rounded-control border border-brand-line bg-brand-soft px-4 py-0.5 text-center text-brand-strong">
              <SurahName surah={piece.surah} />
            </div>
          );
        }
        if (piece.kind === 'basmala') {
          return (
            <p key={piece.key} className="mb-1 text-center">
              {piece.words.join(' ')}
            </p>
          );
        }
        return (
          <p key={piece.key} className="text-justify">
            {piece.items.map((item) => {
              if (item.kind === 'end') {
                return (
                  <Fragment key={item.key}>
                    <span className="whitespace-nowrap text-[0.85em] text-brand-strong">{item.label}</span>{' '}
                  </Fragment>
                );
              }
              const said = about(item);
              const look = join('rounded-[0.3em] px-[0.06em]', item.mark ? markClass(item.mark) : null, item.earlier ? earlierClass(item.earlier.kind) : null);
              if (onWord) {
                return (
                  <Fragment key={item.key}>
                    <button
                      type="button"
                      data-word={item.key}
                      aria-haspopup="menu"
                      aria-expanded={openWord === item.key}
                      aria-label={said ? `${item.text} (${said})` : undefined}
                      title={said || undefined}
                      onClick={(e) => onWord(item, e.currentTarget)}
                      className={join(look, 'cursor-pointer transition-colors hover:bg-brand-soft focus-visible:bg-brand-soft', openWord === item.key && 'ring-2 ring-brand')}
                    >
                      {item.text}
                    </button>{' '}
                  </Fragment>
                );
              }
              return (
                <Fragment key={item.key}>
                  <span className={look || undefined} title={said || undefined}>
                    {item.text}
                    {said ? <span className="sr-only"> ({said})</span> : null}
                  </span>{' '}
                </Fragment>
              );
            })}
          </p>
        );
      })}
    </div>
  );
}
