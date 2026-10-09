'use client';

import { useTranslations } from 'next-intl';
import { useRef, useState, useTransition } from 'react';
import { raiseConcern } from '@/lib/actions/shared';
import { Icon } from '@/components/icons';
import { Button, Field, Notice, Select, Textarea, join } from '@/components/ui';
import { CATEGORY_ICONS, type ConcernOptions } from '@/lib/safeguarding/types';

/**
 * The concern itself, in the reporter's own words: what it is about (big
 * buttons for a child), maybe a lesson and, for a teacher, a student, what
 * happened (a child may leave it out), and whether someone may be in danger
 * now. The thanks replaces the form; one key per submit.
 */
export function RaiseConcern({ options }: { options: ConcernOptions }) {
  const t = useTranslations('safeguarding');
  const common = useTranslations('common');
  const kids = options.look === 'kids';
  const teacher = options.look === 'teacher';
  const [category, setCategory] = useState<string>();
  const [text, setText] = useState('');
  const [urgent, setUrgent] = useState(false);
  const [sent, setSent] = useState<{ number: string; message: string }>();
  const [message, setMessage] = useState<string>();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, start] = useTransition();
  const key = useRef<string | null>(null);

  if (sent) {
    return (
      <section className="space-y-3 rounded-card border border-brand-line bg-brand-soft p-5" aria-live="polite">
        <Icon name="check" size={32} className="text-brand" />
        <p className="text-lg font-semibold">{sent.message}</p>
        <p className="text-sm text-muted">{t('number', { number: sent.number })}</p>
        <Button type="button" variant="quiet" onClick={() => setSent(undefined)}>
          <Icon name="plus" size={18} />
          {t(teacher ? 'againTeacher' : 'again')}
        </Button>
      </section>
    );
  }

  const submit = (form: FormData) => {
    if (!category) {
      setErrors({ category: [t('pick')] });
      return;
    }
    key.current ??= crypto.randomUUID();
    start(async () => {
      setMessage(undefined);
      const done = await raiseConcern(
        {
          category,
          text,
          urgent: options.urgent_allowed && urgent,
          learner_id: options.learner?.id,
          session_id: String(form.get('session_id') ?? '') || undefined,
          student_id: String(form.get('student_id') ?? '') || undefined,
        },
        key.current!,
      );
      key.current = null;
      if (!done.ok) {
        setErrors(done.errors ?? {});
        setMessage(done.message ?? common('problem'));
        return;
      }
      setErrors({});
      setCategory(undefined);
      setText('');
      setUrgent(false);
      setSent({ number: done.number, message: done.message });
    });
  };

  return (
    <form action={submit} className="space-y-4 rounded-card border border-line bg-white p-4 sm:p-5">
      {options.learner && !teacher ? <p className="text-sm text-muted">{t('about', { name: options.learner.name })}</p> : null}
      <fieldset className="space-y-2">
        <legend className="mb-1 text-sm font-semibold">{t(`what.${teacher ? 'classic' : options.look}`)}</legend>
        <div className={join('grid gap-2', kids ? 'grid-cols-2 gap-3' : 'sm:grid-cols-2')}>
          {options.categories.map((c) => (
            <label
              key={c.key}
              className={join(
                'flex cursor-pointer gap-3 rounded-control border text-sm has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand',
                kids ? 'flex-col items-center p-4 text-center text-base font-semibold' : 'items-center p-3',
                category === c.key ? 'border-brand bg-brand-soft' : 'border-line hover:border-brand-line',
              )}
            >
              <input type="radio" name="category" value={c.key} className="sr-only" checked={category === c.key} onChange={() => setCategory(c.key)} />
              <Icon name={CATEGORY_ICONS[c.key as keyof typeof CATEGORY_ICONS] ?? 'flag'} size={kids ? 36 : 22} className="shrink-0 text-brand" />
              <span>{c.label}</span>
            </label>
          ))}
        </div>
        {errors.category?.[0] ? <p className="text-sm text-danger">{errors.category[0]}</p> : null}
      </fieldset>
      {teacher ? (
        <Field label={t('student')} error={errors.student_id?.[0] ?? errors.student?.[0]}>
          <Select name="student_id" defaultValue="">
            <option value="">{t('noStudent')}</option>
            {options.students.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        </Field>
      ) : null}
      {options.lessons.length > 0 ? (
        <Field label={t('lesson')} error={errors.session_id?.[0] ?? errors.session?.[0]}>
          <Select name="session_id" defaultValue="">
            <option value="">{t('noLesson')}</option>
            {options.lessons.map((l) => (
              <option key={l.id} value={l.id}>
                {[l.subject, l.starts_at?.label, l.with].filter(Boolean).join(' · ')}
              </option>
            ))}
          </Select>
        </Field>
      ) : null}
      <Field label={t(`text.${teacher ? 'classic' : options.look}`)} hint={kids ? undefined : t('textHint')} error={errors.text?.[0] ?? errors.body?.[0]}>
        <Textarea name="text" rows={kids ? 3 : 5} maxLength={5000} required={options.text_required} value={text} onChange={(e) => setText(e.target.value)} />
      </Field>
      {options.urgent_allowed ? (
        <label className="flex items-center gap-2 font-semibold text-danger">
          <input type="checkbox" className="size-4 accent-danger" checked={urgent} onChange={(e) => setUrgent(e.target.checked)} />
          {t('urgent')}
        </label>
      ) : null}
      {message ? <Notice tone="danger">{message}</Notice> : null}
      <Button type="submit" disabled={pending} wide={kids}>
        <Icon name="send" size={18} className="rtl:-scale-x-100" />
        {t(`send.${options.look}`)}
      </Button>
    </form>
  );
}
