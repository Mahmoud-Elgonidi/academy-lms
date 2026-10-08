'use client';

import '@livekit/components-styles';
import { ControlBar, GridLayout, LiveKitRoom, ParticipantTile, RoomAudioRenderer, useTracks } from '@livekit/components-react';
import { Track } from 'livekit-client';
import { useTranslations } from 'next-intl';
import { useState, useTransition } from 'react';
import { joinLesson } from '@/lib/actions/shared';
import { Link } from '@/i18n/navigation';
import { Icon } from '@/components/icons';
import { Button, Card, Notice, buttonLook } from '@/components/ui';
import type { JoinTicket } from '@/lib/teach/types';

type Stage = { kind: 'ready' } | { kind: 'in'; ticket: JoinTicket } | { kind: 'left' };

/**
 * Going into the lesson: the API gives this person a seat (JoinLesson, which
 * also says when it is too early or the lesson is over). A LiveKit room opens
 * here with camera and microphone; an outside link opens in a new tab.
 * Attendance comes from the room itself (webhooks), not from this page.
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
      setStage({ kind: 'in', ticket: joined.ticket });
    });

  if (stage.kind === 'in' && stage.ticket.provider === 'livekit' && stage.ticket.token) {
    return (
      <div className="overflow-hidden rounded-card border border-line bg-black" style={{ height: 'min(78dvh, 720px)' }}>
        <LiveKitRoom
          serverUrl={stage.ticket.url}
          token={stage.ticket.token}
          connect
          video={stage.ticket.can_publish}
          audio={stage.ticket.can_publish}
          onDisconnected={() => setStage({ kind: 'left' })}
          data-lk-theme="default"
          style={{ height: '100%' }}
        >
          <Room />
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
function Room() {
  const tracks = useTracks(
    [
      { source: Track.Source.Camera, withPlaceholder: true },
      { source: Track.Source.ScreenShare, withPlaceholder: false },
    ],
    { onlySubscribed: false },
  );
  return (
    <div className="flex h-full flex-col" dir="ltr">
      <GridLayout tracks={tracks} className="min-h-0 flex-1">
        <ParticipantTile />
      </GridLayout>
      <ControlBar variation="minimal" controls={{ chat: false }} />
      <RoomAudioRenderer />
    </div>
  );
}
