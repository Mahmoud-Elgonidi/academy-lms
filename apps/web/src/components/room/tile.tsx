'use client';

import {
  ParticipantTile,
  VideoTrack,
  isTrackReference,
  useConnectionQualityIndicator,
  useEnsureTrackRef,
  useIsMuted,
  type TrackReferenceOrPlaceholder,
} from '@livekit/components-react';
import { Track } from 'livekit-client';
import { useTranslations } from 'next-intl';
import { createContext, useContext, type CSSProperties, type Ref } from 'react';
import { RoomIcon } from './icons';

/** Audio only: other people's cameras are not coming, so their tiles show their initial. */
export const AudioOnly = createContext(false);

/** The key a tile goes by in the layout: the person and the source. */
export function tileKey(ref: TrackReferenceOrPlaceholder): string {
  return `${ref.participant.identity}:${ref.source}`;
}

/** A person's name as the room shows it (their identity when the token gave none). */
export function nameOf(ref: TrackReferenceOrPlaceholder): string {
  return ref.participant.name || ref.participant.identity;
}

/** The first letter of a name. */
export function initialOf(name: string): string {
  const first = Array.from(name.trim())[0];
  return first ? first.toLocaleUpperCase() : '?';
}

type TileProps = {
  /** The track; the carousel's context when none is given. */
  trackRef?: TrackReferenceOrPlaceholder;
  pinned?: string | null;
  onPin?: (key: string) => void;
  className?: string;
  style?: CSSProperties;
  tileRef?: Ref<HTMLDivElement>;
};

/**
 * One tile: LiveKit's tile (which marks who is speaking, for the ring) with
 * our own inside. Clicking pins it to the spotlight, or unpins it.
 */
export function RoomTile({ trackRef, pinned = null, onPin, className, style, tileRef }: TileProps) {
  const t = useTranslations('room');
  const ref = useEnsureTrackRef(trackRef);
  const key = tileKey(ref);
  const isPinned = pinned === key;
  return (
    <ParticipantTile
      ref={tileRef}
      trackRef={ref}
      onParticipantClick={onPin ? () => onPin(key) : undefined}
      className={['room-tile', className].filter(Boolean).join(' ')}
      style={style}
      data-fit={ref.source === Track.Source.ScreenShare ? 'contain' : 'cover'}
      title={onPin ? (isPinned ? t('unpin') : t('pin')) : undefined}
    >
      <TileBody pinned={isPinned} />
    </ParticipantTile>
  );
}

/**
 * What a tile shows, from the track in context: the video (a shared screen
 * whole on black, a camera filling the tile), or the person's initial when
 * their camera is off; their name with a mic-off sign, a pin, and the
 * connection when it is weak.
 */
export function TileBody({ pinned = false }: { pinned?: boolean }) {
  const t = useTranslations('room');
  const trackRef = useEnsureTrackRef();
  const screen = trackRef.source === Track.Source.ScreenShare;
  const micMuted = useIsMuted({ participant: trackRef.participant, source: Track.Source.Microphone });
  const videoMuted = useIsMuted(trackRef);
  const { quality } = useConnectionQualityIndicator({ participant: trackRef.participant });
  const name = nameOf(trackRef);
  const label = screen ? t('screenOf', { name }) : trackRef.participant.isLocal ? t('you', { name }) : name;
  const weak = quality === 'poor' || quality === 'lost';
  const held = useContext(AudioOnly) && !trackRef.participant.isLocal && !screen;

  return (
    <>
      {isTrackReference(trackRef) && !videoMuted && !held ? (
        <VideoTrack trackRef={trackRef} className="lk-participant-media-video room-tile-video" />
      ) : (
        <div className="room-avatar" aria-hidden="true">
          <span>{initialOf(name)}</span>
        </div>
      )}
      <div className="room-name">
        {!screen && micMuted ? (
          <span role="img" aria-label={t('muted')}>
            <RoomIcon name="micOff" />
          </span>
        ) : null}
        {screen ? <RoomIcon name="screen" /> : null}
        <span>{label}</span>
      </div>
      {pinned ? (
        <span className="room-badge" data-at="start" role="img" aria-label={t('pinned')}>
          <RoomIcon name="pin" />
        </span>
      ) : null}
      {weak && !screen ? (
        <span className="room-badge" data-at="end" role="img" aria-label={t(`quality.${quality}`)}>
          <span className="room-signal" data-q={quality}>
            <i />
            <i />
            <i />
          </span>
        </span>
      ) : null}
    </>
  );
}
