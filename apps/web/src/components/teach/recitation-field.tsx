'use client';

import { useTranslations } from 'next-intl';
import { Field, Input, Select, join } from '@/components/ui';
import { samePassage, surahLabel, type Expected, type Surah } from '@/lib/teach/recitation';

/**
 * What homework handed in by voice asks to recite, so each recording is
 * checked word by word for the teacher: a switch, then the surah and the
 * verses (the lesson's own passage ready when it has one). The passages the
 * report suggests (the lesson's text, the verses with the words marked in the
 * lesson's shared mushaf) are each one tap: on, and filled in.
 */
export function RecitationField({
  surahs,
  suggestions,
  on,
  onToggle,
  value,
  onChange,
  error,
}: {
  surahs: Surah[];
  suggestions: { from: 'text' | 'mistakes'; passage: Expected }[];
  on: boolean;
  onToggle: (on: boolean) => void;
  value: Partial<Expected>;
  onChange: (value: Partial<Expected>) => void;
  error: (key: string) => string | undefined;
}) {
  const t = useTranslations('teach');
  const verses = surahs.find((s) => s.number === value.surah)?.verses;
  const number = (v: string) => (v === '' ? undefined : Number(v));

  return (
    <div className="rounded-control border border-line p-3.5">
      <label className="flex items-center gap-3 font-semibold">
        <input type="checkbox" checked={on} onChange={(e) => onToggle(e.target.checked)} className="size-5 accent-[var(--color-brand)]" />
        {t('reciteToggle')}
      </label>
      <p className="mt-1 text-sm text-muted">{t('reciteHint')}</p>
      {error('homework.expected') ? <p className="mt-1 text-sm text-danger">{error('homework.expected')}</p> : null}
      {suggestions.length > 0 ? (
        <div role="group" aria-label={t('reciteSuggestions')} className="mt-3 flex flex-wrap gap-2">
          {suggestions.map((s) => {
            const taken = on && samePassage(value, s.passage);
            const title = s.passage.title ?? `${s.passage.surah}:${s.passage.from}–${s.passage.to}`;
            return (
              <button
                key={s.from}
                type="button"
                aria-pressed={taken}
                data-suggestion={s.from}
                onClick={() => {
                  onToggle(true);
                  onChange({ surah: s.passage.surah, from: s.passage.from, to: s.passage.to });
                }}
                className={join(
                  'min-h-9 rounded-full border px-3.5 py-1 text-start text-sm font-semibold transition-colors',
                  taken ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line bg-white text-ink hover:border-brand-line hover:bg-brand-soft',
                )}
              >
                {s.from === 'mistakes' ? t('reciteFromMistakes', { title }) : t('reciteSuggestion', { title })}
              </button>
            );
          })}
        </div>
      ) : null}
      {on ? (
        <div className="mt-3 space-y-3">
          <Field label={t('reciteSurah')} error={error('homework.expected.surah')}>
            <Select value={value.surah ?? ''} onChange={(e) => onChange({ ...value, surah: number(e.target.value) })} required>
              <option value="">—</option>
              {surahs.map((s) => (
                <option key={s.number} value={s.number}>
                  {surahLabel(s)}
                </option>
              ))}
            </Select>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label={t('reciteFrom')} error={error('homework.expected.from')}>
              <Input type="number" inputMode="numeric" min={1} max={verses} value={value.from ?? ''} onChange={(e) => onChange({ ...value, from: number(e.target.value) })} required />
            </Field>
            <Field label={t('reciteTo')} error={error('homework.expected.to')}>
              <Input type="number" inputMode="numeric" min={value.from ?? 1} max={verses} value={value.to ?? ''} onChange={(e) => onChange({ ...value, to: number(e.target.value) })} required />
            </Field>
          </div>
        </div>
      ) : null}
    </div>
  );
}
