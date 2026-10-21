'use client';

import { useTranslations } from 'next-intl';
import { useEffect, useState, useTransition } from 'react';
import { handIn } from '@/app/[locale]/family/actions';
import { Icon } from '@/components/icons';
import { Button, Field, Notice, Textarea, join } from '@/components/ui';
import { useRecorder } from '@/lib/recorder';
import type { Accepts } from '@/lib/teach/types';

const MAX_BYTES = 25 * 1024 * 1024;
const MAX_SECONDS = 10 * 60;

/**
 * Handing homework in, as it asks: words, a voice recording made here (up to
 * ten minutes), a file (a picture or PDF), or any of them. The API checks the
 * kind and the size again (25 MB).
 */
export function HandIn({ id, accepts, kids }: { id: string; accepts: Accepts; kids: boolean }) {
  const t = useTranslations('family');
  const common = useTranslations('common');
  const takesText = accepts === 'text' || accepts === 'any';
  const takesAudio = accepts === 'audio' || accepts === 'any';
  const takesFile = accepts === 'file' || accepts === 'any';

  const [body, setBody] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [recording, setRecording] = useState<File | null>(null);
  const [preview, setPreview] = useState<string>();
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand' | 'attention'; text: string }>();
  const [pending, start] = useTransition();
  const { live, clock, start: startRecording, stop } = useRecorder({
    limit: MAX_SECONDS,
    onDone: (made) => {
      setRecording(made);
      setPreview(URL.createObjectURL(made));
    },
    onLimit: () => setMessage({ tone: 'attention', text: t('recordingLimit', { n: MAX_SECONDS / 60 }) }),
  });

  useEffect(() => () => (preview ? URL.revokeObjectURL(preview) : undefined), [preview]);

  const record = async () => {
    setMessage(undefined);
    const started = await startRecording();
    if (started === 'recording') {
      setRecording(null);
      setPreview(undefined);
    }
    if (started === 'unsupported') setMessage({ tone: 'attention', text: t('noRecorder') });
    if (started === 'denied') setMessage({ tone: 'danger', text: t('micDenied') });
  };

  const send = () => {
    const attached = recording ?? file;
    if (!body.trim() && !attached) {
      setMessage({ tone: 'danger', text: t('handInEmpty') });
      return;
    }
    if (attached && attached.size > MAX_BYTES) {
      setMessage({ tone: 'danger', text: t('fileTooBig') });
      return;
    }
    const form = new FormData();
    if (body.trim()) form.set('body', body.trim());
    if (attached) form.set('file', attached, attached.name);
    start(async () => {
      const done = await handIn(id, form);
      setMessage(done.ok ? { tone: 'brand', text: t('handedIn') } : { tone: 'danger', text: done.message ?? common('problem') });
      if (done.ok) {
        setBody('');
        setFile(null);
        setRecording(null);
        setPreview(undefined);
      }
    });
  };

  return (
    <div className="space-y-3">
      {takesText ? (
        <Field label={accepts === 'text' ? t('yourAnswer') : t('answerOptional')}>
          <Textarea value={body} onChange={(e) => setBody(e.target.value)} rows={3} maxLength={5000} />
        </Field>
      ) : null}
      <div className="flex flex-wrap items-center gap-2">
        {takesAudio ? (
          live ? (
            <Button type="button" variant="danger" onClick={stop}>
              <Icon name="stop" size={18} />
              {t('stopRecording')} · <span className="tabular-nums" dir="ltr">{clock}</span>
            </Button>
          ) : (
            <Button type="button" variant="quiet" onClick={record} disabled={pending}>
              <Icon name="mic" size={18} />
              {recording ? t('recordAgain') : kids ? t('recordKids') : t('recordVoice')}
            </Button>
          )
        ) : null}
        {takesFile ? (
          <label className={join('inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-control border border-line bg-white px-5 font-semibold hover:border-brand-line', pending && 'opacity-50')}>
            <Icon name="file" size={18} />
            {file ? file.name : t('attachPicture')}
            <input
              type="file"
              className="sr-only"
              accept={accepts === 'any' ? 'image/*,application/pdf,audio/*' : 'image/*,application/pdf'}
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
          </label>
        ) : null}
      </div>
      {live ? (
        <p className="flex items-center gap-2 text-sm text-danger" role="status">
          <span className="size-2.5 animate-pulse rounded-full bg-danger" aria-hidden="true" />
          {t('recordingNow', { time: clock })}
        </p>
      ) : null}
      {preview ? <audio controls src={preview} className="w-full" /> : null}
      {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      <Button type="button" onClick={send} disabled={pending || live}>
        {pending ? common('loading') : t('handIn')}
      </Button>
    </div>
  );
}
