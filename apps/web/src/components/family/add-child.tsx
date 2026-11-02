'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { addChild } from '@/app/[locale]/family/actions';
import { Icon } from '@/components/icons';
import { usePressKey } from '@/components/start/press-key';
import { Button, Field, Input, Notice, Select, join } from '@/components/ui';
import { useRouter } from '@/i18n/navigation';
import { EMPTY_CHILD, birthYears, childBody, filledWording, missing, refusalsOf, type ChildField, type ChildForm } from '@/lib/family/add-child';
import type { ConsentText } from '@/lib/family/types';

/**
 * The form of «إضافة طفل» (J5a): the child's name, birth (the day, or only
 * the year), whether a girl or a boy (optional), and the consent wording with
 * the name as it is typed and the box to agree; sent with one key per press,
 * then straight to the start flow for the child. A refusal stands under the
 * field it is about (the same child already there, too many children, a
 * birth that does not make sense), anything else above the button.
 */
export function AddChildForm({ wording, thisYear, today }: { wording: ConsentText; thisYear: number; today: string }) {
  const t = useTranslations('addChild');
  const router = useRouter();
  const key = usePressKey();
  const [form, setForm] = useState<ChildForm>(EMPTY_CHILD);
  const [errors, setErrors] = useState<Partial<Record<ChildField, string>>>({});
  const [problem, setProblem] = useState<string | null>(null);
  const [added, setAdded] = useState<string | null>(null);
  const [pending, start] = useTransition();
  const text = filledWording(wording, form.name, t('yourChild'));

  const set = <K extends keyof ChildForm>(field: K, value: ChildForm[K]) => {
    setForm((f) => ({ ...f, [field]: value }));
    const which: ChildField | null = field === 'name' ? 'name' : field === 'agree' ? 'agree' : field.startsWith('birth') ? 'birth' : null;
    if (which && errors[which]) setErrors((e) => ({ ...e, [which]: undefined }));
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    const left = missing(form);
    if (left.length > 0) {
      setErrors(Object.fromEntries(left.map((f) => [f, f === 'agree' ? t('agreeFirst') : f === 'name' ? t('nameMissing') : t('birthMissing')])));
      document.getElementById(`child-${left[0]}`)?.focus();
      return;
    }
    setProblem(null);
    start(async () => {
      const done = await addChild(childBody(form, wording.version), key.take());
      key.answered();
      if (done.ok) {
        setAdded(t('added', { name: done.child.name }));
        router.push(`/family/start?learner=${encodeURIComponent(done.child.start.learner_id)}`);
        return;
      }
      const found = refusalsOf(done.errors, done.message);
      setErrors(found.fields);
      if (found.wordingChanged) {
        // The academy changed its wording meanwhile: read it again, the box unticked.
        setForm((f) => ({ ...f, agree: false }));
        router.refresh();
      }
      setProblem(found.form ?? (Object.keys(found.fields).length === 0 ? t('problem') : null));
      if (found.fields.name) document.getElementById('child-name')?.focus();
    });
  };

  if (added) {
    return (
      <div role="status" className="flex items-center gap-3 rounded-card border border-brand-line bg-brand-soft p-5 font-semibold text-brand-strong" data-child-added>
        <Icon name="check" size={22} className="shrink-0" />
        {added}
      </div>
    );
  }

  return (
    <form onSubmit={submit} noValidate className="space-y-5" aria-busy={pending}>
      <section className="space-y-4 rounded-card border border-line bg-white p-5">
        <Field label={t('name')} hint={t('nameHint')} error={errors.name}>
          <Input
            id="child-name"
            name="name"
            value={form.name}
            onChange={(e) => set('name', e.target.value)}
            autoComplete="off"
            minLength={2}
            maxLength={80}
            required
            aria-invalid={Boolean(errors.name)}
            data-child-name
          />
        </Field>

        <fieldset>
          <legend className="mb-1.5 text-sm font-semibold">{t('birth')}</legend>
          <div role="radiogroup" className="mb-2 inline-flex rounded-control border border-line bg-paper p-1 text-sm">
            {(['year', 'date'] as const).map((by) => (
              <button
                key={by}
                type="button"
                role="radio"
                aria-checked={form.birthBy === by}
                onClick={() => set('birthBy', by)}
                className={join('min-h-9 rounded-control px-3 font-semibold transition-colors', form.birthBy === by ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink')}
              >
                {by === 'year' ? t('byYear') : t('byDate')}
              </button>
            ))}
          </div>
          {form.birthBy === 'year' ? (
            <Field label={<span className="sr-only">{t('birthYear')}</span>} hint={t('birthHint')} error={errors.birth}>
              <Select id="child-birth" value={form.birthYear} onChange={(e) => set('birthYear', e.target.value)} aria-invalid={Boolean(errors.birth)} aria-label={t('birthYear')} data-child-year>
                <option value="">{t('yearPlaceholder')}</option>
                {birthYears(thisYear).map((y) => (
                  <option key={y} value={y}>{y}</option>
                ))}
              </Select>
            </Field>
          ) : (
            <Field label={<span className="sr-only">{t('birthDate')}</span>} hint={t('birthHint')} error={errors.birth}>
              <Input id="child-birth" type="date" max={today} value={form.birthDate} onChange={(e) => set('birthDate', e.target.value)} aria-invalid={Boolean(errors.birth)} aria-label={t('birthDate')} data-child-date />
            </Field>
          )}
        </fieldset>

        <fieldset>
          <legend className="mb-1.5 text-sm font-semibold">
            {t('gender')} <span className="font-normal text-muted">({t('genderOptional')})</span>
          </legend>
          <div className="flex flex-wrap gap-2">
            {(['female', 'male', ''] as const).map((g) => (
              <label
                key={g || 'none'}
                className={join(
                  'inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-control border px-4 text-sm font-semibold transition-colors',
                  form.gender === g ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line bg-white hover:border-brand-line',
                )}
              >
                <input type="radio" name="gender" value={g} checked={form.gender === g} onChange={() => set('gender', g)} className="sr-only" />
                {g === 'female' ? t('female') : g === 'male' ? t('male') : t('noGender')}
              </label>
            ))}
          </div>
        </fieldset>
      </section>

      <section className="rounded-card border border-brand-line bg-white p-5" aria-labelledby="child-consent" data-child-consent>
        <p className="flex items-center gap-2 text-sm font-semibold text-brand-strong">
          <Icon name="shield" size={18} />
          {t('consentTitle')}
        </p>
        <h2 id="child-consent" className="mt-1 text-lg font-bold">{text.title}</h2>
        <p className="mt-1 text-sm text-muted">{t('consentLead')}</p>
        <p className="mt-3 leading-7">{text.intro}</p>
        <ul className="mt-3 grid gap-2 sm:grid-cols-2">
          {text.sections.map((part) => (
            <li key={part.title} className="rounded-control bg-brand-soft px-3 py-2 text-sm leading-6">
              <strong className="block">{part.title}</strong>
              {part.body}
            </li>
          ))}
        </ul>
        <label
          className={join(
            'mt-4 flex cursor-pointer items-start gap-3 rounded-control border p-3 text-sm font-semibold',
            errors.agree ? 'border-danger/50 bg-danger-soft' : form.agree ? 'border-brand bg-brand-soft' : 'border-line',
          )}
        >
          <input id="child-agree" type="checkbox" className="mt-1 size-5 accent-brand" checked={form.agree} onChange={(e) => set('agree', e.target.checked)} aria-invalid={Boolean(errors.agree)} data-child-agree />
          <span>{text.agree}</span>
        </label>
        {errors.agree ? <p className="mt-1 text-sm text-danger">{errors.agree}</p> : null}
      </section>

      {problem ? <Notice tone="danger">{problem}</Notice> : null}
      <Button type="submit" wide disabled={pending} data-child-submit>
        {pending ? t('sending') : t('submit')}
        {pending ? null : <Icon name="chevron" size={18} className="rtl:-scale-x-100" />}
      </Button>
    </form>
  );
}
