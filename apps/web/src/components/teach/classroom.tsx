'use client';

import '@livekit/components-styles';
import { ControlBar, GridLayout, LiveKitRoom, ParticipantTile, RoomAudioRenderer, useIsRecording, useRoomInfo, useTracks } from '@livekit/components-react';
import { Track } from 'livekit-client';
import { useTranslations } from 'next-intl';
import { useState, useTransition, type ReactNode } from 'react';
import { joinLesson, recordLesson } from '@/lib/actions/shared';
import type { MushafPage } from '@/lib/mushaf/types';
import type { LessonRecording } from '@/lib/recordings';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Mushaf } from '@/components/mushaf/mushaf';
import { Button, Card, Notice, buttonLook, join } from '@/components/ui';
import type { JoinTicket } from '@/lib/teach/types';

type Stage = { kind: 'ready' } | { kind: 'in'; ticket: JoinTicket; recording: LessonRecording } | { kind: 'left' };

/**
 * Going into the lesson: the API gives this person a seat (JoinLesson, which
 * also says when it is too early or the lesson is over). A LiveKit room opens
 * here with camera and microphone (a student's camera off when the academy
 * says so; they can switch it on); an outside link opens in a new tab.
 * Attendance comes from the room itself (webhooks), not from this page.
 * Everyone sees when the lesson is being recorded; its teacher starts and
 * stops it here, once everyone it records agreed. A Quran lesson of a course
 * the hifz map belongs to has its shared mushaf: beside the room on a wide
 * screen (a switch between the video and the mushaf on a phone, the sound
 * going on), and under the card otherwise, so a lesson on an outside link
 * keeps it open beside the meeting.
 */
export function Classroom({ id, back, mushaf: first = null }: { id: string; back: { href: string; label: string }; mushaf?: MushafPage | null }) {
  const t = useTranslations('teach');
  const m = useTranslations('mushaf');
  const common = useTranslations('common');
  const [stage, setStage] = useState<Stage>({ kind: 'ready' });
  const [message, setMessage] = useState<string>();
  const [pending, start] = useTransition();
  // The mushaf as last drawn, so it starts from there when the room opens or closes around it.
  const [mushaf, setMushaf] = useState(first);
  const [view, setView] = useState<'video' | 'mushaf'>('video');

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
    const video = (
      <div data-lk-theme="default" className={join('overflow-hidden rounded-card border border-line bg-black', mushaf && view === 'mushaf' && 'max-lg:hidden')} style={{ height: 'min(78dvh, 720px)' }}>
        <Room lesson={id} recording={stage.recording} />
      </div>
    );
    return (
      <div className="space-y-3">
        {mushaf ? (
          <div role="group" aria-label={m('views')} className="grid grid-cols-2 gap-1 rounded-control border border-line bg-white p-1 lg:hidden">
            {(['video', 'mushaf'] as const).map((v) => (
              <button
                key={v}
                type="button"
                aria-pressed={view === v}
                onClick={() => setView(v)}
                className={join('inline-flex min-h-10 items-center justify-center gap-2 rounded-control text-sm font-semibold', view === v ? 'bg-brand text-white' : 'text-ink hover:bg-brand-soft')}
              >
                <Icon name={v === 'video' ? 'video' : 'book'} size={16} />
                {v === 'video' ? m('video') : m('title')}
              </button>
            ))}
          </div>
        ) : null}
        <LiveKitRoom
          serverUrl={stage.ticket.url}
          token={stage.ticket.token}
          connect
          video={stage.ticket.can_publish && stage.ticket.camera !== false}
          audio={stage.ticket.can_publish}
          onDisconnected={() => setStage({ kind: 'left' })}
          className={mushaf ? 'lg:grid lg:grid-cols-2 lg:gap-4' : undefined}
          style={{ height: 'auto' }}
        >
          {video}
          {mushaf ? (
            // Inside the room, so the teacher's changes reach the others over its data channel.
            <div className={join('lg:h-[min(78dvh,720px)]', view === 'video' && 'max-lg:hidden')}>
              <Mushaf initial={mushaf} onChange={setMushaf} fill readOnMount />
            </div>
          ) : null}
          {/* The sound goes on whichever of the two a phone shows. */}
          <RoomAudioRenderer />
        </LiveKitRoom>
      </div>
    );
  }

  const withMushaf = (card: ReactNode) =>
    mushaf ? (
      <div className="space-y-4">
        {card}
        <Mushaf initial={mushaf} onChange={setMushaf} readOnMount={stage.kind !== 'ready'} />
      </div>
    ) : (
      card
    );

  if (stage.kind === 'in') {
    return withMushaf(
      <Card className="space-y-4">
        <p className="text-muted">{t('externalLead')}</p>
        <a href={stage.ticket.url} target="_blank" rel="noopener noreferrer" className={buttonLook('primary')}>
          <Icon name="video" size={18} />
          {t('openLink')}
        </a>
      </Card>,
    );
  }

  if (stage.kind === 'left') {
    return withMushaf(
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
      </Card>,
    );
  }

  return withMushaf(
    <Card className="space-y-4">
      <p className="text-muted">{t('classLead')}</p>
      {message ? <Notice tone="attention">{message}</Notice> : null}
      <Button onClick={enter} disabled={pending} wide>
        <Icon name="video" size={18} />
        {pending ? t('connecting') : t('enterRoom')}
      </Button>
    </Card>,
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
