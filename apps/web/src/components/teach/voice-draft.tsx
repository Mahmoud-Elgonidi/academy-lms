'use client';

import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { draftReport } from '@/app/[locale]/teach/actions';
import { Icon } from '@/components/icons';
import { Badge, Button, Notice } from '@/components/ui';
import { useRecorder } from '@/lib/recorder';
import type { VoiceDraft as Draft } from '@/lib/teach/voice-draft';

/** Two minutes: a note about one lesson. */
const LIMIT_SECONDS = 120;

/**
 * The teacher's voice note, recorded here, heard by the API and deleted
 * there at once: what was said fills the empty fields of the open form
 * (`current`, then `onDraft`). Nothing is saved until the teacher sends the
 * report. Shows what to check first, what the report still needs, and the
 * words heard.
 */
export function VoiceDraft({
  reportId,
  current,
  onDraft,
  names,
}: {
  reportId: string;
  current: () => unknown;
  onDraft: (draft: Draft) => void;
  names: (fields: string[]) => string[];
}) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [pending, start] = useTransition();
  const [result, setResult] = useState<Draft>();
  const [message, setMessage] = useState<{ tone: 'danger' | 'brand' | 'attention'; text: string }>();

  const recorder = useRecorder({
    limit: LIMIT_SECONDS,
    base: 'note',
    onDone: (file) => {
      const form = new FormData();
      form.set('audio', file, file.name);
      form.set('current', JSON.stringify(current()));
      start(async () => {
        const done = await draftReport(reportId, form);
        if (!done.ok) {
          setMessage({ tone: 'danger', text: done.message ?? common('problem') });
          return;
        }
        setResult(done.draft);
        onDraft(done.draft);
        setMessage({ tone: done.draft.filled.length > 0 ? 'brand' : 'attention', text: t('voiceDone', { n: done.draft.filled.length }) });
      });
    },
  });

  const record = async () => {
    setMessage(undefined);
    const started = await recorder.start();
    if (started === 'unsupported') setMessage({ tone: 'attention', text: t('voiceNoRecorder') });
    if (started === 'denied') setMessage({ tone: 'danger', text: t('voiceMic') });
  };

  const unsure = result ? names(result.unsure) : [];
  const left = result ? names(result.left) : [];
  return (
    <section className="space-y-3 rounded-card border border-dashed border-brand-line bg-brand-soft/60 p-4" aria-labelledby="voice-draft-title">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <h2 id="voice-draft-title" className="flex items-center gap-2 font-semibold">
            <Icon name="mic" size={18} /> {t('voiceTitle')}
          </h2>
          <p className="mt-1 text-sm text-muted">{t('voiceHint')}</p>
        </div>
        {recorder.live ? (
          <Button type="button" variant="danger" onClick={recorder.stop}>
            <Icon name="stop" size={18} />
            {t('voiceStop')} · <span className="tabular-nums" dir="ltr">{recorder.clock}</span>
          </Button>
        ) : (
          <Button type="button" onClick={record} disabled={pending}>
            <Icon name="mic" size={18} />
            {pending ? t('voiceWorking') : result ? t('voiceAgain') : t('voiceRecord')}
          </Button>
        )}
      </div>
      {recorder.live ? (
        <p className="flex items-center gap-2 text-sm text-danger" role="status">
          <span className="size-2.5 rounded-full bg-danger motion-safe:animate-pulse" aria-hidden="true" />
          {t('voiceNow', { time: recorder.clock })}
        </p>
      ) : null}
      <p className="text-xs text-muted">
        {t('voiceLimit')} · {t('voiceKept')}
      </p>
      {message ? <Notice tone={message.tone}>{message.text}</Notice> : null}
      {result ? (
        <div className="space-y-2 rounded-control border border-line bg-white p-3">
          {unsure.length > 0 ? (
            <div>
              <p className="text-sm font-semibold">{t('voiceUnsure')}</p>
              <div className="mt-1 flex flex-wrap gap-1.5">
                {unsure.map((name) => (
                  <Badge key={name} tone="attention">
                    {name}
                  </Badge>
                ))}
              </div>
            </div>
          ) : null}
          {left.length > 0 ? <p className="text-sm text-muted">{t('voiceLeft', { fields: left.join(t('listJoin')) })}</p> : null}
          <details>
            <summary className="cursor-pointer text-sm text-muted">{t('voiceHeard')}</summary>
            <p className="mt-1 whitespace-pre-line text-sm">{result.heard}</p>
          </details>
        </div>
      ) : null}
    </section>
  );
}
