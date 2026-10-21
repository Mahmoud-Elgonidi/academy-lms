'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { saveCard } from '@/app/[locale]/teach/actions';
import { Icon } from '@/components/icons';
import { Button, Field, Input, Notice, Textarea } from '@/components/ui';
import { initialOf } from '@/lib/site/initial';

const MAX_BYTES = 1024 * 1024;

/** The teacher's card as GET /v1/teacher/card answers it. */
export type MyCard = {
  display_name: string;
  bio: string | null;
  photo_url: string | null;
  shown: boolean;
  state: 'on' | 'off' | 'hidden' | 'none';
  state_label: string;
  subjects: { name: string; level_from: string | null; level_to: string | null }[];
};

/** The card's form beside a preview of it as the academy's page shows it. */
export function CardEditor({ card }: { card: MyCard }) {
  const t = useTranslations('teachCard');
  const common = useTranslations('common');
  const [name, setName] = useState(card.display_name);
  const [bio, setBio] = useState(card.bio ?? '');
  const [picked, setPicked] = useState<string | null>(null);
  const [remove, setRemove] = useState(false);
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand'; text: string }>();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, start] = useTransition();
  const photo = remove ? null : (picked ?? card.photo_url);
  const levels = (from: string | null, to: string | null) => (from || to ? t('levels', { from: from ?? t('fromStart'), to: to ?? t('toEnd') }) : null);

  const submit = (form: FormData) => {
    const file = form.get('photo');
    if (file instanceof File && file.size > MAX_BYTES) {
      setMessage({ tone: 'danger', text: t('tooBig') });
      return;
    }
    start(async () => {
      const done = await saveCard(form);
      if (!done.ok) {
        setErrors(done.errors ?? {});
        setMessage({ tone: 'danger', text: Object.values(done.errors ?? {})[0]?.[0] ?? done.message ?? common('problem') });
        return;
      }
      setErrors({});
      setPicked(null);
      setRemove(false);
      setMessage({ tone: 'brand', text: t('saved') });
    });
  };

  return (
    <div className="grid gap-5 lg:grid-cols-[1fr_20rem]">
      <form action={submit} className="space-y-4 rounded-card border border-line bg-white p-4 sm:p-5">
        <Notice tone={card.state === 'hidden' ? 'attention' : card.state === 'on' ? 'brand' : 'info'}>{card.state_label}</Notice>
        <label className="flex items-start gap-3 rounded-control border border-line p-3">
          <input type="checkbox" name="shown" defaultChecked={card.shown} className="mt-1 size-5 accent-brand" />
          <span>
            <span className="block font-semibold">{t('shown')}</span>
            <span className="text-sm text-muted">{t('shownHint')}</span>
          </span>
        </label>
        <Field label={t('name')} hint={t('nameHint')} error={errors.display_name?.[0]}>
          <Input name="display_name" required maxLength={60} value={name} onChange={(e) => setName(e.target.value)} />
        </Field>
        <Field label={t('bio')} hint={t('bioHint')} error={errors.bio?.[0]}>
          <Textarea name="bio" rows={4} maxLength={280} value={bio} onChange={(e) => setBio(e.target.value)} />
        </Field>
        <Field label={t('photo')} hint={t('photoHint')} error={errors.photo?.[0]}>
          <input
            name="photo"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(e) => {
              const file = e.target.files?.[0];
              setPicked(file ? URL.createObjectURL(file) : null);
              if (file) setRemove(false);
            }}
            className="block w-full text-sm file:me-3 file:rounded-control file:border-0 file:bg-brand-soft file:px-4 file:py-2 file:font-semibold file:text-brand-strong"
          />
        </Field>
        {card.photo_url ? (
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remove_photo" checked={remove} onChange={(e) => setRemove(e.target.checked)} className="size-4 accent-brand" />
            {t('removePhoto')}
          </label>
        ) : null}
        {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
        <Button type="submit" disabled={pending}>
          <Icon name="check" size={18} />
          {t('save')}
        </Button>
      </form>

      <aside aria-label={t('preview')}>
        <p className="mb-2 text-sm font-semibold text-muted">{t('preview')}</p>
        <div className="appear rounded-card border border-line bg-white p-5 text-center shadow-sm">
          <div className="mx-auto grid size-24 place-items-center overflow-hidden rounded-full bg-brand text-3xl font-bold text-white ring-4 ring-brand-soft">
            {photo ? (
              // eslint-disable-next-line @next/next/no-img-element -- the teacher's own picture, or the one just picked
              <img src={photo} alt="" className="size-full object-cover" />
            ) : (
              <span aria-hidden="true">{initialOf(name)}</span>
            )}
          </div>
          <h2 className="mt-3 font-heading text-lg font-bold">{name.trim() || card.display_name}</h2>
          {card.subjects.length > 0 ? (
            <ul className="mt-2 space-y-1 text-sm">
              {card.subjects.map((s) => (
                <li key={s.name}>
                  <strong>{s.name}</strong>
                  {levels(s.level_from, s.level_to) ? <span className="block text-xs text-muted">{levels(s.level_from, s.level_to)}</span> : null}
                </li>
              ))}
            </ul>
          ) : null}
          {bio.trim() ? <p className="mt-3 whitespace-pre-line text-sm text-muted">{bio.trim()}</p> : null}
          {card.subjects.length > 0 ? <p className="mt-3 text-xs text-muted">{t('subjectsFrom')}</p> : null}
        </div>
      </aside>
    </div>
  );
}
