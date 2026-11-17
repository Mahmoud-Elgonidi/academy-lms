'use client';

import '@livekit/components-styles';
import { LiveKitRoom, RoomAudioRenderer, StartAudio } from '@livekit/components-react';
import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { createPortal } from 'react-dom';
import { joinLesson } from '@/lib/actions/shared';
import type { LessonRecording } from '@/lib/recordings';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { LessonRoom } from '@/components/room/room';
import { Button, Card, Notice, buttonLook } from '@/components/ui';
import type { JoinTicket } from '@/lib/teach/types';

type Stage = { kind: 'ready' } | { kind: 'in'; ticket: JoinTicket; recording: LessonRecording } | { kind: 'left' };

/**
 * Going into the lesson: the API gives this person a seat (JoinLesson, which
 * also says when it is too early, the lesson is over, or a guardian's consent
 * is awaited). A LiveKit room opens over the whole window (components/room:
 * as on Meet or Zoom, with picture-in-picture when the tab is left) with
 * camera and microphone (a student's camera off when the academy says so;
 * they can switch it on); an outside link opens in a new tab. Attendance
 * comes from the room itself (webhooks), not from this page. Everyone sees
 * when the lesson is being recorded; its teacher starts and stops it from the
 * room's "more", once everyone it records agreed.
 */
export function Classroom({
  id,
  back,
  title,
  subtitle,
}: {
  id: string;
  back: { href: string; label: string };
  /** The lesson as the room's top bar names it. */
  title?: string;
  subtitle?: string;
}) {
  const t = useTranslations('teach');
  const room = useTranslations('room');
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
    return createPortal(
      <div className="room" data-lk-theme="default">
        <LiveKitRoom
          serverUrl={stage.ticket.url}
          token={stage.ticket.token}
          connect
          video={stage.ticket.can_publish && stage.ticket.camera !== false}
          audio={stage.ticket.can_publish}
          onDisconnected={() => setStage({ kind: 'left' })}
          className="room-host"
        >
          <LessonRoom
            lesson={id}
            title={title ?? room('lesson')}
            subtitle={subtitle}
            recording={stage.recording}
            canPublish={stage.ticket.can_publish}
            panel={null}
          />
          {/* The sound goes on whatever the stage shows, the panel included. */}
          <RoomAudioRenderer />
          <StartAudio label={room('startAudio')} />
        </LiveKitRoom>
      </div>,
      document.body,
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
