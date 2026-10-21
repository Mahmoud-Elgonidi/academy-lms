'use client';

import { useTranslations } from 'next-intl';
import { useMemo, useState, useTransition } from 'react';
import { clearHifz, markHifz } from '@/app/[locale]/teach/actions';
import { Button, Field, Input, Notice, join } from '@/components/ui';
import { choicePayload, chosenPages, findSurahs, MARKS, parsePages } from '@/lib/hifz/hifz';
import type { Juz, Mark, TeacherHifz } from '@/lib/hifz/types';
import { formProblem } from '@/lib/problems';
import { surahLabel } from '@/lib/teach/recitation';

type State = { ok?: string; message?: string; errors?: Record<string, string[]> };

const toggled = (values: number[], value: number) => (values.includes(value) ? values.filter((v) => v !== value) : [...values, value]);

/**
 * "Say what they know": whole parts, surahs (found by name or number) or
 * pages the student already knows by heart, and how well (the API's own
 * words). Each page starts again as memorized and comes up for review as
 * that says; the map above refreshes from the API.
 */
export function HifzMarkForm({ enrollment, juzs, surahs, marks }: { enrollment: string; juzs: Juz[]; surahs: TeacherHifz['surahs']; marks: TeacherHifz['marks'] }) {
  const t = useTranslations('hifz.teacher');
  const hifz = useTranslations('hifz');
  const common = useTranslations('common');
  const [parts, setParts] = useState<number[]>([]);
  const [chosen, setChosen] = useState<number[]>([]);
  const [search, setSearch] = useState('');
  const [typed, setTyped] = useState('');
  const [strength, setStrength] = useState<Mark>('good');
  const [state, setState] = useState<State>({});
  const [pending, start] = useTransition();
  const found = useMemo(() => findSurahs(surahs, search), [surahs, search]);
  const pages = parsePages(typed);
  const count = chosenPages({ juzs, surahs }, { juz: parts, surahs: chosen, pages: pages.pages });
  const error = (key: string) => state.errors?.[key]?.[0] ?? Object.entries(state.errors ?? {}).find(([k]) => k.startsWith(`${key}.`))?.[1][0];
  const nameOf = (number: number) => surahs.find((s) => s.number === number);

  const submit = () => {
    if (pages.invalid.length > 0) {
      setState({ message: t('badPages', { pages: pages.invalid.join('، ') }) });
      return;
    }
    if (count === 0) {
      setState({ message: t('chooseFirst') });
      return;
    }
    start(async () => {
      const done = await markHifz(enrollment, choicePayload({ juz: parts, surahs: chosen, pages: pages.pages }), strength);
      if (done.ok) {
        setState({ ok: t('marked', { n: done.count ?? count }) });
        setParts([]);
        setChosen([]);
        setTyped('');
        setSearch('');
      } else {
        setState({ message: formProblem({ message: done.message ?? common('problem'), errors: done.errors }, ['juz', 'surahs', 'pages', 'strength']), errors: done.errors });
      }
    });
  };

  return (
    <form
      className="space-y-5"
      onSubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{t('parts')}</legend>
        <div className="grid grid-cols-6 gap-1.5 sm:grid-cols-10">
          {juzs.map((j) => (
            <button
              key={j.juz}
              type="button"
              aria-pressed={parts.includes(j.juz)}
              aria-label={hifz('juz', { n: j.juz })}
              onClick={() => setParts((now) => toggled(now, j.juz))}
              className={join('min-h-10 rounded-control border text-sm font-semibold tabular-nums transition-colors', parts.includes(j.juz) ? 'border-brand bg-brand text-white' : 'border-line bg-white hover:border-brand-line')}
            >
              {j.juz}
            </button>
          ))}
        </div>
        {error('juz') ? <p className="mt-1 text-sm text-danger">{error('juz')}</p> : null}
      </fieldset>

      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{t('surahs')}</legend>
        {chosen.length > 0 ? (
          <ul className="mb-2 flex flex-wrap gap-1.5">
            {chosen.map((number) => (
              <li key={number}>
                <button type="button" onClick={() => setChosen((now) => toggled(now, number))} className="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-brand-soft px-3 text-sm font-semibold text-brand-strong" aria-label={`${common('remove')}: ${nameOf(number)?.name_ar ?? number}`}>
                  {nameOf(number) ? surahLabel(nameOf(number)!) : number}
                  <span aria-hidden="true">×</span>
                </button>
              </li>
            ))}
          </ul>
        ) : null}
        <Input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('surahSearch')} aria-label={t('surahSearch')} />
        <ul className="mt-2 max-h-56 divide-y divide-line overflow-y-auto rounded-control border border-line bg-white">
          {found.length === 0 ? <li className="px-3 py-2.5 text-sm text-muted">{t('noSurah')}</li> : null}
          {found.map((s) => (
            <li key={s.number}>
              <label className="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 hover:bg-paper">
                <input type="checkbox" checked={chosen.includes(s.number)} onChange={() => setChosen((now) => toggled(now, s.number))} className="size-4.5 accent-[var(--color-brand)]" />
                <span className="min-w-0 flex-1 truncate">{surahLabel(s)}</span>
                <span className="shrink-0 text-xs tabular-nums text-muted">{hifz('pagesN', { n: s.pages.length })}</span>
              </label>
            </li>
          ))}
        </ul>
        {error('surahs') ? <p className="mt-1 text-sm text-danger">{error('surahs')}</p> : null}
      </fieldset>

      <Field label={`${t('pages')} ${common('optional')}`} hint={t('pagesHint')} error={error('pages')}>
        <Input value={typed} onChange={(e) => setTyped(e.target.value)} inputMode="numeric" dir="ltr" autoComplete="off" />
      </Field>

      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{t('howWell')}</legend>
        <div role="radiogroup" className="flex flex-wrap gap-1.5">
          {MARKS.map((mark) => (
            <button
              key={mark}
              type="button"
              role="radio"
              aria-checked={strength === mark}
              onClick={() => setStrength(mark)}
              className={join('min-h-10 rounded-control border px-4 text-sm font-semibold transition-colors', strength === mark ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line bg-white text-ink hover:border-brand-line')}
            >
              {marks[mark] ?? mark}
            </button>
          ))}
        </div>
        {error('strength') ? <p className="mt-1 text-sm text-danger">{error('strength')}</p> : null}
      </fieldset>

      {state.ok ? <Notice tone="brand">{state.ok}</Notice> : null}
      {state.message ? <Notice tone="danger">{state.message}</Notice> : null}
      <div className="flex flex-wrap items-center gap-3">
        <Button disabled={pending || count === 0}>{pending ? common('loading') : t('save')}</Button>
        <span className="text-sm text-muted" aria-live="polite">
          {t('chosen', { n: count })}
        </span>
      </div>
    </form>
  );
}

/** Pages marked by mistake come off the map: they are new again, and their past reviews stay. */
export function HifzClearForm({ enrollment }: { enrollment: string }) {
  const t = useTranslations('hifz.teacher');
  const common = useTranslations('common');
  const [typed, setTyped] = useState('');
  const [state, setState] = useState<State>({});
  const [pending, start] = useTransition();

  const submit = () => {
    const { pages, invalid } = parsePages(typed);
    if (invalid.length > 0) return setState({ message: t('badPages', { pages: invalid.join('، ') }) });
    if (pages.length === 0) return setState({ message: t('chooseFirst') });
    if (!window.confirm(t('clearConfirm'))) return;
    start(async () => {
      const done = await clearHifz(enrollment, choicePayload({ pages }));
      if (done.ok) {
        setState({ ok: t('cleared', { n: done.count ?? 0 }) });
        setTyped('');
      } else {
        setState({ message: formProblem({ message: done.message ?? common('problem'), errors: done.errors }, []) ?? common('problem') });
      }
    });
  };

  return (
    <form
      className="space-y-3"
      onSubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <Field label={t('pages')} hint={t('pagesHint')}>
        <Input value={typed} onChange={(e) => setTyped(e.target.value)} inputMode="numeric" dir="ltr" autoComplete="off" required />
      </Field>
      {state.ok ? <Notice tone="brand">{state.ok}</Notice> : null}
      {state.message ? <Notice tone="danger">{state.message}</Notice> : null}
      <Button variant="quiet" disabled={pending}>
        {pending ? common('loading') : t('clearButton')}
      </Button>
    </form>
  );
}
