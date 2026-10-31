'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { cancelLesson, cancellationOf, rateLesson, ratingOf, refuseSubstitute } from '@/app/[locale]/family/actions';
import { Dialog } from '@/components/dialog';
import { DisputeLesson } from '@/components/dispute-lesson';
import { Icon } from '@/components/icons';
import { Button, Field, Notice, Select, Textarea, join } from '@/components/ui';
import type { CancelPreview, Rating } from '@/lib/family/types';

/**
 * What a family may do with a lesson, as the API allows it (lessonActionsFor):
 * cancel after seeing what the policy will do (on a group lesson, "I won't
 * attend": only the learner's own seat is excused, the lesson goes on), turn
 * down a substitute, rate it once it took place, dispute what it was
 * recorded as. A kid's look gets none of these; the API refuses them anyway.
 */
export function LessonActions({
  id,
  day,
  allowed,
  disputeReasons = [],
  group = false,
  learnerId,
}: {
  id: string;
  day: string;
  allowed: { cancel: boolean; refuse: boolean; rate: boolean; dispute: boolean };
  disputeReasons?: { key: string; label: string }[];
  /** A group lesson: cancelling becomes "I won't attend" (the API answers excuse_only). */
  group?: boolean;
  /** Whose seat, when a guardian follows several learners. */
  learnerId?: string;
}) {
  return (
    <div className="flex flex-wrap gap-2">
      {allowed.refuse ? <RefuseSubstitute id={id} /> : null}
      {allowed.cancel ? <CancelLesson id={id} day={day} group={group} learnerId={learnerId} /> : null}
      {allowed.rate ? <RateLesson id={id} /> : null}
      {allowed.dispute ? <DisputeLesson id={id} side="family" reasons={disputeReasons} /> : null}
    </div>
  );
}

function CancelLesson({ id, day, group, learnerId }: { id: string; day: string; group: boolean; learnerId?: string }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [preview, setPreview] = useState<CancelPreview | null>(null);
  const [reason, setReason] = useState('');
  const [note, setNote] = useState('');
  const [message, setMessage] = useState<string>();
  const [done, setDone] = useState(false);
  // One key per confirmation: pressing again (or a retry) cancels once.
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [pending, start] = useTransition();

  const openIt = () =>
    start(async () => {
      setMessage(undefined);
      const got = await cancellationOf(id, learnerId);
      if (!got.ok) {
        setMessage(got.message ?? common('problem'));
        return;
      }
      setPreview(got.preview);
      setKey(crypto.randomUUID());
      setOpen(true);
    });

  // The API has the last word: a group lesson is only ever excused, never cancelled.
  const excuse = preview ? preview.excuse_only === true : group;
  return (
    <>
      <Button type="button" variant="quiet" className="min-h-10 px-4 text-sm" onClick={openIt} disabled={pending}>
        {group ? t('excuseAction') : t('cancelLesson')}
      </Button>
      {message && !open ? <p className="w-full text-sm text-danger">{message}</p> : null}
      {done ? <p className="w-full text-sm text-brand-strong">{excuse ? t('excused') : t('cancelled')}</p> : null}
      <Dialog open={open} onClose={() => setOpen(false)} title={excuse ? t('excuseTitle', { day }) : t('cancelTitle', { day })}>
        {preview ? (
          <>
            {excuse ? (
              <div className="flex items-start gap-3 rounded-card border border-group-line bg-group-soft p-3.5 text-sm text-group-strong">
                <Icon name="people" size={20} className="mt-0.5 shrink-0" />
                <p>{t('excuseLead')}</p>
              </div>
            ) : null}
            <Notice tone="attention">
              <span className="font-semibold">{excuse ? t('excusePolicy') : t('policySays')}</span> {preview.outcome}
            </Notice>
            <Field label={t('reason')}>
              <Select value={reason} onChange={(e) => setReason(e.target.value)} required>
                <option value="" disabled>
                  —
                </option>
                {preview.reasons.map((r) => (
                  <option key={r.value} value={r.value}>
                    {r.label}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={`${t('noteForAcademy')} ${common('optional')}`}>
              <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={500} />
            </Field>
            {message ? <Notice tone="danger">{message}</Notice> : null}
            <div className="flex flex-wrap justify-end gap-2">
              <Button type="button" variant="quiet" onClick={() => setOpen(false)}>
                {excuse ? t('excuseKeep') : t('keepIt')}
              </Button>
              <Button
                type="button"
                variant={excuse ? 'primary' : 'danger'}
                disabled={pending}
                onClick={() => {
                  if (!reason) {
                    setMessage(t('pickReason'));
                    return;
                  }
                  start(async () => {
                    const result = await cancelLesson(id, reason, note, key, learnerId);
                    if (result.ok) {
                      setOpen(false);
                      setDone(true);
                    } else {
                      setMessage(result.message ?? common('problem'));
                    }
                  });
                }}
              >
                {excuse ? t('excuseYes') : t('yesCancel')}
              </Button>
            </div>
          </>
        ) : null}
      </Dialog>
    </>
  );
}

function RefuseSubstitute({ id }: { id: string }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <>
      <Button type="button" variant="quiet" className="min-h-10 px-4 text-sm" onClick={() => setOpen(true)}>
        {t('refuseSubstitute')}
      </Button>
      {message && !open ? <p className="w-full text-sm text-brand-strong">{message}</p> : null}
      <Dialog open={open} onClose={() => setOpen(false)} title={t('refuseSubstitute')}>
        <p>{t('refuseSubstituteBody')}</p>
        {message && open ? <Notice tone="danger">{message}</Notice> : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="quiet" onClick={() => setOpen(false)}>
            {t('keepIt')}
          </Button>
          <Button
            type="button"
            disabled={pending}
            onClick={() =>
              start(async () => {
                const result = await refuseSubstitute(id);
                if (result.ok) {
                  setOpen(false);
                  setMessage(t('refusedSubstitute', { days: result.makeupDays ?? 0 }));
                } else {
                  setMessage(result.message ?? common('problem'));
                }
              })
            }
          >
            {t('refuseSubstituteYes')}
          </Button>
        </div>
      </Dialog>
    </>
  );
}

function RateLesson({ id }: { id: string }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState<Rating | null>(null);
  const [stars, setStars] = useState(0);
  const [tags, setTags] = useState<string[]>([]);
  const [comment, setComment] = useState('');
  const [message, setMessage] = useState<string>();
  const [sent, setSent] = useState(false);
  const [pending, start] = useTransition();

  const openIt = () =>
    start(async () => {
      const got = await ratingOf(id);
      if (!got.ok) {
        setMessage(got.message ?? common('problem'));
        return;
      }
      setRating(got.preview);
      setStars(got.preview.stars ?? 0);
      setTags(got.preview.tags);
      setComment(got.preview.comment ?? '');
      setOpen(true);
    });

  const choices = stars >= 4 ? (rating?.choices.good ?? []) : (rating?.choices.bad ?? []);
  return (
    <>
      <Button type="button" variant="quiet" className="min-h-10 px-4 text-sm" onClick={openIt} disabled={pending}>
        <Icon name="star" size={16} />
        {t('rateLesson')}
      </Button>
      {sent ? <p className="w-full text-sm text-brand-strong">{t('rated')}</p> : null}
      {message && !open ? <p className="w-full text-sm text-muted">{message}</p> : null}
      <Dialog open={open} onClose={() => setOpen(false)} title={t('rateTitle')}>
        {rating && !rating.can_rate ? (
          <p className="text-muted">{rating.why ?? (rating.stars ? `${t('yourRating')}: ${t('starsOf', { n: rating.stars })}` : '')}</p>
        ) : (
          <>
            <div role="radiogroup" aria-label={t('rateTitle')} className="flex justify-center gap-2">
              {[1, 2, 3, 4, 5].map((n) => (
                <button
                  key={n}
                  type="button"
                  role="radio"
                  aria-checked={stars === n}
                  aria-label={t('starsOf', { n })}
                  onClick={() => {
                    setStars(n);
                    setTags([]);
                  }}
                  className="grid size-12 place-items-center rounded-full hover:bg-reward-soft"
                >
                  <Icon name="star" size={32} fill={n <= stars ? 'var(--color-reward)' : 'none'} className={n <= stars ? 'text-reward' : 'text-line'} />
                </button>
              ))}
            </div>
            {stars > 0 ? (
              <>
                <p className="text-sm font-semibold">{stars >= 4 ? t('rateGood') : t('rateBad')}</p>
                <div className="flex flex-wrap gap-2">
                  {choices.map((c) => {
                    const on = tags.includes(c.value);
                    return (
                      <button
                        key={c.value}
                        type="button"
                        aria-pressed={on}
                        onClick={() => setTags((all) => (on ? all.filter((x) => x !== c.value) : [...all, c.value]))}
                        className={join('min-h-9 rounded-full border px-3 text-sm', on ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-line bg-white')}
                      >
                        {c.label}
                      </button>
                    );
                  })}
                </div>
                <Field label={`${t('rateComment')} ${common('optional')}`} hint={t('rateAnonymous')}>
                  <Textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={2} maxLength={500} />
                </Field>
              </>
            ) : null}
            {message ? <Notice tone="danger">{message}</Notice> : null}
            <Button
              type="button"
              wide
              disabled={pending || stars === 0}
              onClick={() =>
                start(async () => {
                  const result = await rateLesson(id, stars, tags, comment);
                  if (result.ok) {
                    setOpen(false);
                    setSent(true);
                  } else {
                    setMessage(result.message ?? common('problem'));
                  }
                })
              }
            >
              {t('sendRating')}
            </Button>
          </>
        )}
      </Dialog>
    </>
  );
}
