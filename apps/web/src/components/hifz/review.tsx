'use client';

import { useTranslations } from 'next-intl';
import { useEffect, useRef, useState, useTransition } from 'react';
import { gradeHifzPage, reciteHifzPage } from '@/app/[locale]/family/actions';
import { Icon } from '@/components/icons';
import { RecitedWords } from '@/components/recited-words';
import { Badge, Button, Notice, buttonLook, join } from '@/components/ui';
import { Link, useRouter } from '@/i18n/navigation';
import { GRADES, gradeTone } from '@/lib/hifz/hifz';
import type { Grade, HifzPage, Recitation } from '@/lib/hifz/types';
import { useRecorder } from '@/lib/recorder';

/** A page recited takes five minutes at most; the API takes 25 MB. */
const MAX_SECONDS = 5 * 60;
const MAX_BYTES = 25 * 1024 * 1024;

const gradeLook: Record<Grade, string> = {
  1: 'border-danger/30 bg-danger-soft text-danger hover:border-danger',
  2: 'border-attention/30 bg-attention-soft text-attention hover:border-attention',
  3: 'border-brand-line bg-brand-soft text-brand-strong hover:border-brand',
  4: 'border-brand bg-brand text-white hover:bg-brand-strong',
};

type Message = { tone: 'danger' | 'brand' | 'attention'; text: string };

/**
 * Reviewing one hifz page. With the academy's check on, the learner (or a
 * guardian with them) recites it into the microphone, listens back, sends it,
 * and the page says "listening" until the hifz signal brings the result: the
 * words coloured as they were heard, the review it counted as, and the note
 * that the teacher has the last word. Otherwise, or when the check could not
 * hear it, they recite from memory, uncover the page to compare, and say how
 * it went (forgot, hard, good, easy). A child's look has its own words.
 */
export function HifzReview({ learnerId, page, surahs, verses, attribution, mode, recitation, next, kids }: {
  learnerId: string;
  page: number;
  surahs: HifzPage['surahs'];
  verses: HifzPage['verses'];
  attribution: string;
  mode: 'check' | 'self';
  /** The recitation just sent (from the address), with its check as it stands. */
  recitation: Recitation | null;
  /** The next page of today's review. */
  next: number | null;
  kids: boolean;
}) {
  const t = useTranslations('hifz');
  const family = useTranslations('family');
  const common = useTranslations('common');
  const say = (key: 'record' | 'reveal' | 'reciteFirst' | 'listening') => (kids ? t(`kids.${key}`) : t(key));
  const router = useRouter();
  const [selfInstead, setSelfInstead] = useState(false);
  const [message, setMessage] = useState<Message>();
  const [recording, setRecording] = useState<File | null>(null);
  const [preview, setPreview] = useState<string>();
  const [pending, start] = useTransition();
  const { live, clock, start: startRecording, stop } = useRecorder({
    limit: MAX_SECONDS,
    base: 'recitation',
    onDone: (made) => {
      setRecording(made);
      setPreview(URL.createObjectURL(made));
    },
    onLimit: () => setMessage({ tone: 'attention', text: family('recordingLimit', { n: MAX_SECONDS / 60 }) }),
  });

  useEffect(() => () => (preview ? URL.revokeObjectURL(preview) : undefined), [preview]);

  const failed = recitation?.status === 'failed';
  const selfReview = mode === 'self' || selfInstead || failed;

  const record = async () => {
    setMessage(undefined);
    const started = await startRecording();
    if (started === 'recording') {
      setRecording(null);
      setPreview(undefined);
    }
    if (started === 'unsupported') setMessage({ tone: 'attention', text: t('noRecorder') });
    if (started === 'denied') setMessage({ tone: 'danger', text: family('micDenied') });
  };

  const send = () => {
    if (!recording) return;
    if (recording.size > MAX_BYTES) {
      setMessage({ tone: 'danger', text: family('fileTooBig') });
      return;
    }
    const form = new FormData();
    form.set('audio', recording, recording.name);
    setMessage(undefined);
    start(async () => {
      const done = await reciteHifzPage(learnerId, page, form);
      if (done.ok && done.id) {
        router.replace(`/family/hifz/${page}?recitation=${done.id}`);
        return;
      }
      if (done.off) setSelfInstead(true);
      setMessage({ tone: 'danger', text: done.message ?? common('problem') });
    });
  };

  const again = () => router.replace(`/family/hifz/${page}`);

  return (
    <div className="space-y-5">
      {recitation && recitation.status !== 'failed' ? (
        <RecitationResult recitation={recitation} next={next} kids={kids} onAgain={again} />
      ) : null}

      {failed ? (
        <Notice tone="danger">
          <span className="block font-semibold">{recitation.status_label}</span>
          {recitation.failure ? <span className="block">{recitation.failure}</span> : null}
          <span className="mt-1 block">{t('selfFallback')}</span>
        </Notice>
      ) : null}

      {mode === 'check' && !recitation && !selfInstead ? (
        <section className="rounded-card border border-brand-line bg-white p-5" aria-label={say('record')}>
          <p className="text-muted">{t('recordLead')}</p>
          <div className="mt-4 flex flex-wrap items-center gap-2">
            {live ? (
              <Button type="button" variant="danger" onClick={stop}>
                <Icon name="stop" size={18} />
                {t('stop')} · <span className="tabular-nums" dir="ltr">{clock}</span>
              </Button>
            ) : recording ? (
              <>
                <Button type="button" onClick={send} disabled={pending}>
                  <Icon name="send" size={18} className="rtl:-scale-x-100" />
                  {pending ? common('loading') : t('send')}
                </Button>
                <Button type="button" variant="quiet" onClick={record} disabled={pending}>
                  <Icon name="mic" size={18} />
                  {t('recordAgain')}
                </Button>
              </>
            ) : (
              <Button type="button" onClick={record} className={kids ? 'min-h-14 px-7 text-lg' : undefined}>
                <Icon name="mic" size={kids ? 24 : 18} />
                {say('record')}
              </Button>
            )}
          </div>
          {live ? (
            <p className="mt-3 flex items-center gap-2 text-sm text-danger" role="status">
              <span className="size-2.5 rounded-full bg-danger motion-safe:animate-pulse" aria-hidden="true" />
              {family('recordingNow', { time: clock })}
            </p>
          ) : null}
          {preview && !live ? <audio controls src={preview} className="mt-3 w-full" /> : null}
          {message ? (
            <div className="mt-3">
              <Notice tone={message.tone}>{message.text}</Notice>
            </div>
          ) : null}
          {message && message.tone !== 'brand' ? (
            <button type="button" onClick={() => setSelfInstead(true)} className="mt-3 text-sm font-semibold text-brand-strong hover:underline">
              {t('selfInstead')}
            </button>
          ) : null}
        </section>
      ) : null}

      {selfInstead && message ? <Notice tone={message.tone}>{message.text}</Notice> : null}

      {selfReview ? (
        <SelfReview learnerId={learnerId} page={page} next={next} kids={kids} surahs={surahs} verses={verses} attribution={attribution} say={say} />
      ) : recitation ? null : (
        // Before reciting, the page stays covered unless asked for.
        <PageText surahs={surahs} verses={verses} attribution={attribution} label={say('reveal')} />
      )}
    </div>
  );
}

/** The check of a page just recited: listening while it is heard, then the words as they were heard and the review it counted as. */
function RecitationResult({ recitation, next, kids, onAgain }: { recitation: Recitation; next: number | null; kids: boolean; onAgain: () => void }) {
  const t = useTranslations('hifz');
  if (recitation.status === 'pending') {
    return (
      <section className="rounded-card border border-info/30 bg-info-soft p-5 text-info" role="status" aria-live="polite">
        <p className="flex items-center gap-2.5 font-heading text-lg font-bold">
          <span className="size-3 rounded-full bg-info motion-safe:animate-pulse" aria-hidden="true" />
          {kids ? t('kids.listening') : t('listening')}
        </p>
        <p className="mt-1 text-sm">{t('listeningHint')}</p>
      </section>
    );
  }
  return (
    <section className="pop space-y-3 rounded-card border border-brand-line bg-white p-5" aria-labelledby="hz-result" aria-live="polite">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="hz-result" className="font-heading text-lg font-bold">
          {t('result')}
        </h2>
        {recitation.recited_at ? <span className="text-sm text-muted">{recitation.recited_at.label}</span> : null}
      </div>
      {recitation.grade && recitation.grade_label ? (
        <p className="flex flex-wrap items-center gap-2">
          <span className="text-sm text-muted">{t('counted')}</span>
          <Badge tone={gradeTone(recitation.grade)}>{recitation.grade_label}</Badge>
        </p>
      ) : null}
      {recitation.summary_label ? <p className="text-sm font-semibold">{recitation.summary_label}</p> : null}
      <RecitedWords check={recitation} large />
      <p className="text-xs text-muted">{recitation.note}</p>
      <div className="flex flex-wrap gap-2 border-t border-line pt-4">
        <NextLink next={next} />
        <Button type="button" variant="quiet" onClick={onAgain}>
          <Icon name="mic" size={18} />
          {t('again')}
        </Button>
      </div>
    </section>
  );
}

/** On to the next page of today's review, or back to the map when it is the last. */
function NextLink({ next }: { next: number | null }) {
  const t = useTranslations('hifz');
  return next ? (
    <Link href={`/family/hifz/${next}`} className={buttonLook('primary')}>
      {t('next')}
      <Icon name="chevron" size={18} className="rtl:-scale-x-100" />
    </Link>
  ) : (
    <Link href="/family/hifz" className={buttonLook('primary')}>
      {t('toMap')}
    </Link>
  );
}

/** Reciting from memory, then the page uncovered to compare, then how it went: the page's review for today. */
function SelfReview({ learnerId, page, next, kids, surahs, verses, attribution, say }: {
  learnerId: string;
  page: number;
  next: number | null;
  kids: boolean;
  surahs: HifzPage['surahs'];
  verses: HifzPage['verses'];
  attribution: string;
  say: (key: 'reveal' | 'reciteFirst') => string;
}) {
  const t = useTranslations('hifz');
  const common = useTranslations('common');
  const [shown, setShown] = useState(false);
  // Once uncovered, how it went can be said, also with the page covered again.
  const [seen, setSeen] = useState(false);
  const [graded, setGraded] = useState<Grade | null>(null);
  const [message, setMessage] = useState('');
  const [pending, start] = useTransition();
  // One key per grade pressed: a retry of the same press counts once.
  const keys = useRef<Partial<Record<Grade, string>>>({});

  const grade = (value: Grade) => {
    keys.current[value] ??= crypto.randomUUID();
    const key = keys.current[value]!;
    setMessage('');
    start(async () => {
      const done = await gradeHifzPage(learnerId, page, value, key);
      if (done.ok) setGraded(value);
      else setMessage(done.message ?? common('problem'));
    });
  };

  const label = (value: Grade) => (kids ? t(`kids.grades.${value}`) : t(`grades.${value}`));

  return (
    <div className="space-y-5">
      {!seen ? <p className="text-muted">{say('reciteFirst')}</p> : null}
      <PageText
        surahs={surahs}
        verses={verses}
        attribution={attribution}
        label={say('reveal')}
        shown={shown}
        onShown={(value) => {
          setShown(value);
          if (value) setSeen(true);
        }}
      />
      {graded ? (
        <section className="pop space-y-3 rounded-card bg-brand-soft p-5" role="status">
          <p className="font-heading text-lg font-bold text-brand-strong">{t('graded', { grade: label(graded) })}</p>
          <div className="flex flex-wrap gap-2">
            <NextLink next={next} />
          </div>
        </section>
      ) : seen ? (
        <section aria-labelledby="hz-grade" className="appear">
          <h2 id="hz-grade" className="mb-3 font-heading text-lg font-bold">
            {t('howWent')}
          </h2>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {GRADES.map((value) => (
              <button
                key={value}
                type="button"
                disabled={pending}
                onClick={() => grade(value)}
                data-grade={value}
                className={join('rounded-control border-2 px-3 font-semibold transition-colors disabled:opacity-50', kids ? 'min-h-16 text-lg' : 'min-h-12', gradeLook[value])}
              >
                {label(value)}
              </button>
            ))}
          </div>
          {message ? (
            <div className="mt-3">
              <Notice tone="danger">{message}</Notice>
            </div>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}

/** Arabic-Indic numbers for a verse's end, as the mushaf writes them. */
const verseNumber = new Intl.NumberFormat('ar-EG');

/**
 * The page's verses (perhaps the end of one surah and the start of the next),
 * covered until asked for, with the attribution the text is shown with.
 */
function PageText({ surahs, verses, attribution, label, shown: controlled, onShown }: {
  surahs: HifzPage['surahs'];
  verses: HifzPage['verses'];
  attribution: string;
  label: string;
  shown?: boolean;
  onShown?: (shown: boolean) => void;
}) {
  const t = useTranslations('hifz');
  const [own, setOwn] = useState(false);
  const shown = controlled ?? own;
  const toggle = (value: boolean) => (onShown ? onShown(value) : setOwn(value));
  const nameOf = (number: number) => surahs.find((s) => s.number === number)?.name_ar ?? '';

  if (!shown) {
    return (
      <section className="grid place-items-center gap-3 rounded-card border-2 border-dashed border-line bg-white/60 p-8 text-center">
        <Icon name="book" size={28} className="text-muted" />
        <Button type="button" variant="quiet" onClick={() => toggle(true)} aria-expanded={false}>
          {label}
        </Button>
      </section>
    );
  }
  return (
    <section className="appear rounded-card border border-line bg-white p-5" aria-label={t('text')}>
      <div id="hz-text" dir="rtl" lang="ar" className="text-2xl leading-[2.4]">
        {verses.map((v) => (
          <span key={`${v.surah}:${v.verse}`}>
            {v.verse === 1 ? <span className="my-2 block text-center font-heading text-base font-bold text-brand-strong">{t('surah', { name: nameOf(v.surah) })}</span> : null}
            {v.text} <span className="text-base text-muted">﴿{verseNumber.format(v.verse)}﴾</span>{' '}
          </span>
        ))}
      </div>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
        <p className="text-xs text-muted">{attribution}</p>
        <button type="button" onClick={() => toggle(false)} aria-expanded aria-controls="hz-text" className="text-sm font-semibold text-brand-strong hover:underline">
          {t('hide')}
        </button>
      </div>
    </section>
  );
}
