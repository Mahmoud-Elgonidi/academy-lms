'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { agreeToRecording, watchRecording, withdrawRecordingConsent } from '@/lib/actions/shared';
import { length, type RecordingConsent, type RecordingRow } from '@/lib/recordings';
import { Icon } from '@/components/icons';
import { Badge, Button, Card, Empty, Notice } from '@/components/ui';

/**
 * Someone's consent to lesson recordings: the words and "I agree" while
 * nobody has, who agreed and "withdraw" once agreed; or who decides, when
 * the person signed in cannot (a teen's guardian, an adult student).
 */
export function RecordingConsentCard({ consent, off }: { consent: RecordingConsent; off: boolean }) {
  const t = useTranslations('recordings');
  const [ticked, setTicked] = useState(false);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const agreed = consent.state === 'agreed';

  const agree = () =>
    start(async () => {
      const done = await agreeToRecording(consent.person.id, consent.text.version, ticked);
      setError(done.ok ? undefined : done.message);
    });
  const withdraw = () => {
    if (!window.confirm(t('withdrawConfirm'))) return;
    start(async () => {
      const done = await withdrawRecordingConsent(consent.person.id);
      setError(done.ok ? undefined : done.message);
      setTicked(false);
    });
  };

  return (
    <Card className={!agreed && consent.can_change ? 'ring-2 ring-attention/40' : undefined}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="font-heading text-lg font-bold">
          {consent.text.title}
          {consent.self ? '' : ` · ${consent.person.name}`}
        </h2>
        <Badge tone={agreed ? 'brand' : 'attention'}>{agreed ? t('agreed') : t('none')}</Badge>
      </div>
      {agreed ? (
        <div className="mt-2 space-y-3">
          <p className="text-sm text-muted">{consent.by}</p>
          {off ? <p className="text-sm text-muted">{t('off')}</p> : null}
          {error ? <Notice tone="danger">{error}</Notice> : null}
          {consent.can_change ? (
            <Button variant="danger" onClick={withdraw} disabled={pending}>
              {t('withdraw')}
            </Button>
          ) : null}
        </div>
      ) : consent.can_change ? (
        <div className="mt-3 space-y-3">
          <p className="leading-7">{consent.text.intro}</p>
          <ul className="grid gap-2">
            {consent.text.sections.map((part) => (
              <li key={part.title} className="rounded-control bg-brand-soft px-3 py-2 text-sm">
                <strong className="block">{part.title}</strong>
                {part.body}
              </li>
            ))}
          </ul>
          <label className="flex cursor-pointer items-start gap-3 rounded-control border border-line p-3 text-sm font-semibold">
            <input type="checkbox" className="mt-1 size-4 accent-brand" checked={ticked} onChange={(e) => setTicked(e.target.checked)} />
            <span>{consent.text.agree}</span>
          </label>
          {error ? <Notice tone="danger">{error}</Notice> : null}
          <Button onClick={agree} disabled={pending}>
            {t('agree')}
          </Button>
        </div>
      ) : (
        <p className="mt-2 text-sm text-muted">{consent.decides}</p>
      )}
    </Card>
  );
}

/** Recordings to watch: one plays at a time above the list (in the page, never as a download); each opening is logged. */
export function RecordingList({ items, withStudent = false }: { items: RecordingRow[]; withStudent?: boolean }) {
  const t = useTranslations('recordings');
  const [playing, setPlaying] = useState<{ id: string; url: string; media: string }>();
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();

  const play = (id: string) =>
    start(async () => {
      const done = await watchRecording(id);
      if (!done.ok) {
        setError(done.message);
        return;
      }
      setError(undefined);
      setPlaying({ id, url: done.url, media: done.media });
    });

  if (items.length === 0) return <Empty>{t('empty')}</Empty>;
  return (
    <div className="space-y-3">
      {playing ? (
        <div className="space-y-2 rounded-card bg-ink p-3" aria-label={t('watch')}>
          <div className="flex items-center justify-between gap-2">
            <small className="text-white/70">{t('logged')}</small>
            <button type="button" onClick={() => setPlaying(undefined)} className="rounded-full p-1 text-white/80 hover:text-white" aria-label={t('close')}>
              <Icon name="x" size={18} />
            </button>
          </div>
          {playing.media === 'audio' ? (
            <audio key={playing.id} controls autoPlay controlsList="nodownload" src={playing.url} className="w-full" onContextMenu={(e) => e.preventDefault()} />
          ) : (
            <video
              key={playing.id}
              controls
              autoPlay
              playsInline
              controlsList="nodownload noplaybackrate"
              disablePictureInPicture
              src={playing.url}
              className="max-h-[60vh] w-full rounded-control bg-black"
              onContextMenu={(e) => e.preventDefault()}
            />
          )}
        </div>
      ) : null}
      {error ? <Notice tone="danger">{error}</Notice> : null}
      <ul className="grid gap-2">
        {items.map((r) => (
          <li key={r.id} className={`flex items-center justify-between gap-3 rounded-card border bg-white px-4 py-3 ${playing?.id === r.id ? 'border-brand' : 'border-line'}`}>
            <div className="min-w-0">
              <p className="font-semibold">{r.at.label}</p>
              <p className="text-sm break-words text-muted">
                {[withStudent ? r.student : null, r.teacher, t(r.media === 'audio' ? 'audio' : 'video'), length(r.duration_sec), r.expires_at ? t('until', { date: r.expires_at.label }) : null]
                  .filter(Boolean)
                  .join(' · ')}
              </p>
            </div>
            <Button variant="quiet" onClick={() => play(r.id)} disabled={pending}>
              <Icon name="play" size={16} />
              {t('watch')}
            </Button>
          </li>
        ))}
      </ul>
    </div>
  );
}
