'use client';

import '@livekit/components-styles';
import { ControlBar, GridLayout, LiveKitRoom, ParticipantTile, RoomAudioRenderer, useIsRecording, useRoomInfo, useTracks } from '@livekit/components-react';
import { Track } from 'livekit-client';
import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { joinLesson, recordLesson } from '@/lib/actions/shared';
import type { LessonRecording } from '@/lib/recordings';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Button, Card, Notice, buttonLook } from '@/components/ui';
import type { JoinTicket } from '@/lib/teach/types';

type Stage = { kind: 'ready' } | { kind: 'in'; ticket: JoinTicket; recording: LessonRecording } | { kind: 'left' };

/**
 * Going into the lesson: the API gives this person a seat (JoinLesson, which
 * also says when it is too early or the lesson is over). A LiveKit room opens
 * here with camera and microphone (a student's camera off when the academy
 * says so; they can switch it on); an outside link opens in a new tab.
 * Attendance comes from the room itself (webhooks), not from this page.
 * Everyone sees when the lesson is being recorded; its teacher starts and
 * stops it here, once everyone it records agreed.
 */
export function Classroom({ id, back }: { id: string; back: { href: string; label: string } }) {
  const t = useTranslations('teach');
  const common = useTranslations('common');
  const [stage, setStage] = useState<Stage>({ kind: 'ready' });
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();

  const enter = () =>
    start(async () => {
      const joined = await joinLesson(id);
      if (!joined.ok) {
        setMessage(joined.message ?? common('problem'));
        return;
      }
      setMessage(undefined);
      if (joined.ticket.provider !== 'livekit' || !joined.ticket.token) {
        window.open(joined.ticket.url, '_blank', 'noopener');
      }
      setStage({ kind: 'in', ticket: joined.ticket, recording: joined.recording });
    });

  if (stage.kind === 'in' && stage.ticket.provider === 'livekit' && stage.ticket.token) {
    return (
      <div className="overflow-hidden rounded-card border border-line bg-black" style={{ height: 'min(78dvh, 720px)' }}>
        <LiveKitRoom
          serverUrl={stage.ticket.url}
          token={stage.ticket.token}
          connect
          video={stage.ticket.can_publish && stage.ticket.camera !== false}
          audio={stage.ticket.can_publish}
          onDisconnected={() => setStage({ kind: 'left' })}
          data-lk-theme="default"
          style={{ height: '100%' }}
        >
          <Room lesson={id} recording={stage.recording} />
        </LiveKitRoom>
      </div>
    );
  }

  if (stage.kind === 'in') {
    return (
      <Card className="space-y-4">
        <p className="text-muted">{t('externalLead')}</p>
        <a href={stage.ticket.url} target="_blank" rel="noopener noreferrer" className={buttonLook('primary')}>
          <Icon name="video" size={18} />
          {t('openLink')}
        </a>
      </Card>
    );
  }

  if (stage.kind === 'left') {
    return (
      <Card className="space-y-4 text-center">
        <p>{t('roomEnded')}</p>
        <div className="flex flex-wrap justify-center gap-2">
          <Button onClick={enter} disabled={pending}>
            {t('enterRoom')}
          </Button>
          <Link href={back.href} className={buttonLook('quiet')}>
            {back.label}
          </Link>
        </div>
      </Card>
    );
  }

  return (
    <Card className="space-y-4">
      <p className="text-muted">{t('classLead')}</p>
      {message ? <Notice tone="attention">{message}</Notice> : null}
      <Button onClick={enter} disabled={pending} wide>
        <Icon name="video" size={18} />
        {pending ? t('connecting') : t('enterRoom')}
      </Button>
    </Card>
  );
}

/**
 * The room: everyone's camera (or their name), a shared screen when there is
 * one, and icon-only controls (LiveKit's own labels are English only).
 */
function Room({ lesson, recording }: { lesson: string; recording: LessonRecording }) {
  const tracks = useTracks(
    [
      { source: Track.Source.Camera, withPlaceholder: true },
      { source: Track.Source.ScreenShare, withPlaceholder: false },
    ],
    { onlySubscribed: false },
  );
  return (
    <div className="flex h-full flex-col" dir="ltr">
      <RecordingBar lesson={lesson} recording={recording} />
      <GridLayout tracks={tracks} className="min-h-0 flex-1">
        <ParticipantTile />
      </GridLayout>
      <ControlBar variation="minimal" controls={{ chat: false }} />
      <RoomAudioRenderer />
    </div>
  );
}

/**
 * The recording sign, for everyone (LiveKit says when the room is recorded,
 * and so does the room's metadata), and the teacher's start and stop.
 */
function RecordingBar({ lesson, recording }: { lesson: string; recording: LessonRecording }) {
  const t = useTranslations('recordings');
  const recorded = useIsRecording();
  const { metadata } = useRoomInfo();
  const [running, setRunning] = useState<boolean | undefined>(undefined);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const said = (() => {
    try {
      const meta = JSON.parse(metadata || '{}') as { recording?: boolean };
      return typeof meta.recording === 'boolean' ? meta.recording : undefined;
    } catch {
      return undefined;
    }
  })();
  const on = recorded || (running ?? said ?? recording.running);
  if (recording.mode === 'off' && !on) return null;

  const toggle = () =>
    start(async () => {
      const done = await recordLesson(lesson, !on);
      if (!done.ok) {
        setError(done.message);
        return;
      }
      setError(undefined);
      setRunning(done.running);
    });

  return (
    <div className="flex flex-wrap items-center gap-2 bg-ink/90 px-3 py-2 text-sm text-white" dir="auto">
      {on ? (
        <span role="status" className="inline-flex items-center gap-1.5 rounded-full bg-danger px-2.5 py-0.5 text-xs font-bold">
          <span className="size-2 rounded-full bg-white motion-safe:animate-pulse" />
          {t('on')}
        </span>
      ) : null}
      {recording.controls ? (
        <button
          type="button"
          onClick={toggle}
          disabled={pending || (!on && recording.missing.length > 0)}
          className={`rounded-control px-3 py-1 font-semibold disabled:opacity-50 ${on ? 'bg-danger text-white' : 'bg-white/15 text-white hover:bg-white/25'}`}
        >
          {on ? t('stop') : t('start')}
        </button>
      ) : null}
      {recording.controls && !on && recording.missing.length > 0 ? <span className="text-white/75">{t('missing', { names: recording.missing.join(t('separator')) })}</span> : null}
      {error ? <span className="text-danger-soft">{error}</span> : null}
    </div>
  );
}
