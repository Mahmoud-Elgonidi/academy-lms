'use client';

import { useTranslations } from 'next-intl';
import { useEffect, useRef, useState, useTransition } from 'react';
import { handIn } from '@/app/[locale]/family/actions';
import { Icon } from '@/components/icons';
import { Button, Field, Notice, Textarea, join } from '@/components/ui';
import type { Accepts } from '@/lib/teach/types';

const MAX_BYTES = 25 * 1024 * 1024;
const MAX_SECONDS = 10 * 60;

/** The first recording format this browser makes that the API takes (SubmitHomework::AUDIO). */
function recordingType(): string | undefined {
  if (typeof MediaRecorder === 'undefined') return undefined;
  return ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'].find((t) => MediaRecorder.isTypeSupported(t));
}

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
  const [seconds, setSeconds] = useState(0);
  const [live, setLive] = useState(false);
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand' | 'attention'; text: string }>();
  const [pending, start] = useTransition();
  const recorder = useRef<MediaRecorder | null>(null);
  const chunks = useRef<Blob[]>([]);

  // While recording: a second counter, and a stop at the limit.
  useEffect(() => {
    if (!live) return;
    const tick = setInterval(() => {
      setSeconds((s) => {
        if (s + 1 >= MAX_SECONDS) {
          recorder.current?.stop();
          setMessage({ tone: 'attention', text: t('recordingLimit', { n: MAX_SECONDS / 60 }) });
        }
        return s + 1;
      });
    }, 1000);
    return () => clearInterval(tick);
  }, [live, t]);

  useEffect(() => () => (preview ? URL.revokeObjectURL(preview) : undefined), [preview]);

  const record = async () => {
    const type = recordingType();
    if (!type || !navigator.mediaDevices?.getUserMedia) {
      setMessage({ tone: 'attention', text: t('noRecorder') });
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const rec = new MediaRecorder(stream, { mimeType: type });
      chunks.current = [];
      rec.ondataavailable = (e) => e.data.size > 0 && chunks.current.push(e.data);
      rec.onstop = () => {
        stream.getTracks().forEach((track) => track.stop());
        const kind = type.split(';')[0]!;
        const ext = kind.includes('mp4') ? 'm4a' : kind.includes('ogg') ? 'ogg' : 'webm';
        const made = new File(chunks.current, `recording.${ext}`, { type: kind });
        setRecording(made);
        setPreview(URL.createObjectURL(made));
        setLive(false);
      };
      recorder.current = rec;
      setSeconds(0);
      setRecording(null);
      setPreview(undefined);
      setMessage(undefined);
      rec.start(1000);
      setLive(true);
    } catch {
      setMessage({ tone: 'danger', text: t('micDenied') });
    }
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

  const clock = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
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
            <Button type="button" variant="danger" onClick={() => recorder.current?.stop()}>
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
