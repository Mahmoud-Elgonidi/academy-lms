'use client';

import {
  CarouselLayout,
  FocusLayout,
  FocusLayoutContainer,
  useConnectionQualityIndicator,
  useConnectionState,
  useLocalParticipant,
  useParticipants,
  useRoomContext,
  useTrackToggle,
  useTracks,
  type TrackReferenceOrPlaceholder,
} from '@livekit/components-react';
import { ConnectionState, Track } from 'livekit-client';
import { useTranslations } from 'next-intl';
import { useCallback, useEffect, useId, useLayoutEffect, useMemo, useRef, useState, useSyncExternalStore, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import type { LessonRecording } from '@/lib/recordings';
import { canShareScreen, isApple, shortcutLabel, shortcutOf } from './browser';
import { RoomIcon, type RoomIconName } from './icons';
import { LAYOUT_MODES, arrange, elapsed, gridShape, mainOf, nearestCorner, startCorner, type Arrangement, type Corner, type LayoutMode, type TileFact } from './layout';
import { usePictureInPicture } from './pip';
import { useRecording, type RecordingState } from './recording';
import { AudioOnly, RoomTile, TileBody, tileKey } from './tile';
import './room.css';

type Props = {
  lesson: string;
  title: string;
  subtitle?: string;
  recording: LessonRecording;
  /** Whether this person sends sound and video (a watcher only follows). */
  canPublish: boolean;
  /** The side panel (the shared mushaf), with its name, when the lesson has one. */
  panel: { name: string; content: ReactNode } | null;
};

/**
 * The lesson room, inside LiveKit's room, as on Meet or Zoom: a slim top bar
 * (the lesson, the time since joining, the connection, the recording sign,
 * how many are in), the stage (the other person large and one's own picture
 * small in a corner that can be dragged, a grid for three or more, a shared
 * screen in the spotlight with the cameras in a strip), the side panel, and
 * round controls with their shortcuts. Picture-in-picture keeps the lesson
 * in view when the person leaves the tab.
 */
export function LessonRoom({ lesson, title, subtitle, recording, canPublish, panel }: Props) {
  const t = useTranslations('room');
  const room = useRoomContext();
  const connection = useConnectionState();
  const rec = useRecording(lesson, recording);
  const [mode, setMode] = useState<LayoutMode>('auto');
  const [pinned, setPinned] = useState<string | null>(null);
  const [audioOnly, setAudioOnly] = useState(false);
  const [panelOpen, setPanelOpen] = useState(() => panel !== null && window.matchMedia('(min-width: 1024px)').matches);
  const [problem, setProblem] = useState<string>();
  const stageRef = useRef<HTMLDivElement>(null);
  // Measured here, where the stage's ref is set before the effect runs.
  const size = useSize(stageRef);

  const all = useTracks(
    [
      { source: Track.Source.Camera, withPlaceholder: true },
      { source: Track.Source.ScreenShare, withPlaceholder: false },
    ],
    { onlySubscribed: false },
  );
  // Someone only watching sends nothing: no empty tile of their own.
  const tracks = useMemo(() => all.filter((ref) => canPublish || !ref.participant.isLocal), [all, canPublish]);
  const byKey = useMemo(() => new Map(tracks.map((ref) => [tileKey(ref), ref])), [tracks]);
  const facts = useMemo<TileFact[]>(() => tracks.map((ref) => ({ key: tileKey(ref), local: ref.participant.isLocal, screen: ref.source === Track.Source.ScreenShare })), [tracks]);
  const arrangement = arrange(facts, mode, pinned);
  const main = byKey.get(mainOf(arrangement) ?? '') ?? null;
  const pin = useCallback((key: string) => setPinned((now) => (now === key ? null : key)), []);

  const connected = connection === ConnectionState.Connected || connection === ConnectionState.Reconnecting;
  const pip = usePictureInPicture({ active: connected, video: () => stageRef.current?.querySelector<HTMLVideoElement>('.room-main-tile video') ?? null });

  // Audio only: other people's cameras stop coming (the sound and a shared screen stay).
  useEffect(() => {
    for (const person of room.remoteParticipants.values()) {
      for (const publication of person.videoTrackPublications.values()) {
        if (publication.source === Track.Source.Camera) publication.setEnabled(!audioOnly);
      }
    }
  }, [room, audioOnly, tracks]);

  // The page under the room does not scroll while it is open.
  useEffect(() => {
    const html = document.documentElement;
    const before = html.style.overflow;
    html.style.overflow = 'hidden';
    return () => {
      html.style.overflow = before;
    };
  }, []);

  const leave = useCallback(() => {
    pip.exit();
    void room.disconnect();
  }, [pip, room]);

  const deviceFailed = useCallback(() => setProblem(t('deviceFailed')), [t]);
  // Closing the browser's own screen picker is not a failure.
  const shareFailed = useCallback(
    (error: Error) => {
      if (error.name !== 'NotAllowedError' && error.name !== 'AbortError') setProblem(t('deviceFailed'));
    },
    [t],
  );

  const sharingMine = tracks.some((ref) => ref.participant.isLocal && ref.source === Track.Source.ScreenShare);
  const alone = room.remoteParticipants.size === 0;

  return (
    <AudioOnly.Provider value={audioOnly}>
      <TopBar title={title} subtitle={subtitle} on={rec.shown && rec.on} />
      {connection === ConnectionState.Reconnecting ? (
        <p className="room-note" role="status">
          {t('reconnecting')}
        </p>
      ) : null}
      {rec.error ? <Note text={rec.error} onClose={rec.dismiss} /> : null}
      {problem ? <Note text={problem} onClose={() => setProblem(undefined)} /> : null}
      <div className="room-main" data-panel={panel && panelOpen ? 'open' : 'closed'}>
        <section className="room-stage" ref={stageRef} aria-label={t('stage')}>
          <Stage arrangement={arrangement} byKey={byKey} pinned={pinned} onPin={pin} stage={stageRef} size={size} alone={alone} />
        </section>
        {panel ? (
          <aside className="room-side" hidden={!panelOpen} aria-label={panel.name}>
            <div className="room-side-head">
              <span>{panel.name}</span>
              <button type="button" onClick={() => setPanelOpen(false)} aria-label={t('panelClose', { name: panel.name })}>
                <RoomIcon name="x" />
              </button>
            </div>
            <div className="room-side-body">{panel.content}</div>
          </aside>
        ) : null}
      </div>
      <ControlBar
        canPublish={canPublish}
        mode={mode}
        setMode={(next) => {
          setMode(next);
          setPinned(null);
        }}
        audioOnly={audioOnly}
        setAudioOnly={setAudioOnly}
        panel={panel ? { name: panel.name, open: panelOpen, toggle: () => setPanelOpen((open) => !open) } : null}
        pip={pip.way === 'none' ? null : { open: pip.open, toggle: () => (pip.open ? pip.exit() : void pip.enter(false)) }}
        rec={rec}
        sharingMine={sharingMine}
        onDeviceError={deviceFailed}
        onShareError={shareFailed}
        onLeave={leave}
      />
      {pip.window && main
        ? createPortal(
            <div className="room room-pip-root" dir={document.documentElement.dir || undefined}>
              <div className="room-pip-video">
                <RoomTile trackRef={main} />
                <div className="room-pip-bar">
                  {canPublish ? <MicButton compact onDeviceError={deviceFailed} /> : null}
                  <button type="button" className="room-round" onClick={() => pip.exit()} aria-label={t('pipClose')}>
                    <RoomIcon name="pip" />
                  </button>
                  <button type="button" className="room-leave" onClick={leave} aria-label={t('leaveHint')}>
                    <RoomIcon name="leave" />
                  </button>
                </div>
              </div>
            </div>,
            pip.window.document.body,
          )
        : null}
    </AudioOnly.Provider>
  );
}

function Note({ text, onClose }: { text: string; onClose: () => void }) {
  const t = useTranslations('room');
  return (
    <p className="room-note" data-tone="danger" role="alert">
      {text}
      <button type="button" className="room-round" style={{ width: 32, height: 32 }} onClick={onClose} aria-label={t('close')}>
        <RoomIcon name="x" />
      </button>
    </p>
  );
}

/** The lesson's name, the time since joining, the connection, the recording sign and how many are in. */
function TopBar({ title, subtitle, on }: { title: string; subtitle?: string; on: boolean }) {
  const t = useTranslations('room');
  const r = useTranslations('recordings');
  const { localParticipant } = useLocalParticipant();
  const { quality } = useConnectionQualityIndicator({ participant: localParticipant });
  const people = useParticipants().length;
  const [joined] = useState(() => Date.now());
  const [seconds, setSeconds] = useState(0);
  useEffect(() => {
    const tick = setInterval(() => setSeconds(Math.floor((Date.now() - joined) / 1000)), 1000);
    return () => clearInterval(tick);
  }, [joined]);
  const said = t(`quality.${quality}`);

  return (
    <header className="room-top">
      <div className="room-title">
        <strong>{title}</strong>
        {subtitle ? <small>{subtitle}</small> : null}
      </div>
      {on ? (
        <span className="room-rec" role="status">
          {r('on')}
        </span>
      ) : null}
      <span className="room-stat room-quality" data-q={quality} title={said}>
        <span className="sr-only">{said}</span>
        <span className="room-quality-text" aria-hidden="true">
          {said}
        </span>
      </span>
      <span className="room-stat room-people" title={t('people', { count: people })}>
        <RoomIcon name="people" />
        <span aria-hidden="true">{people}</span>
        <span className="sr-only">{t('people', { count: people })}</span>
      </span>
      <span className="room-stat room-timer" title={t('timer')}>
        <span className="sr-only">{t('timer')}</span>
        <time dir="ltr">{elapsed(seconds)}</time>
      </span>
    </header>
  );
}

/** The stage's size, for a grid that fits it. */
function useSize(ref: RefObject<HTMLElement | null>): { width: number; height: number } {
  const [size, setSize] = useState({ width: 0, height: 0 });
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el || typeof ResizeObserver === 'undefined') return;
    const observer = new ResizeObserver(([entry]) => {
      if (entry) setSize({ width: entry.contentRect.width, height: entry.contentRect.height });
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, [ref]);
  return size;
}

function useMedia(query: string): boolean {
  return useSyncExternalStore(
    (change) => {
      const list = window.matchMedia(query);
      list.addEventListener('change', change);
      return () => list.removeEventListener('change', change);
    },
    () => window.matchMedia(query).matches,
    () => false,
  );
}

function Stage({
  arrangement,
  byKey,
  pinned,
  onPin,
  stage,
  size,
  alone,
}: {
  arrangement: Arrangement;
  byKey: Map<string, TrackReferenceOrPlaceholder>;
  pinned: string | null;
  onPin: (key: string) => void;
  stage: RefObject<HTMLDivElement | null>;
  size: { width: number; height: number };
  alone: boolean;
}) {
  const t = useTranslations('room');
  const wide = useMedia('(min-width: 768px)');
  const refs = (keys: string[]) => keys.map((key) => byKey.get(key)).filter((ref): ref is TrackReferenceOrPlaceholder => ref !== undefined);

  switch (arrangement.kind) {
    case 'empty':
      return null;
    case 'solo': {
      const ref = byKey.get(arrangement.main);
      return ref ? (
        <div className="room-solo">
          <RoomTile trackRef={ref} className="room-main-tile" />
          {alone ? <p className="room-alone">{t('alone')}</p> : null}
        </div>
      ) : null;
    }
    case 'pair': {
      const other = byKey.get(arrangement.main);
      const self = byKey.get(arrangement.self);
      return (
        <div className="room-pair">
          {other ? <RoomTile trackRef={other} pinned={pinned} onPin={onPin} className="room-main-tile" /> : null}
          {self ? <SelfView trackRef={self} stage={stage} onPin={onPin} /> : null}
        </div>
      );
    }
    case 'grid': {
      const shape = gridShape(arrangement.tiles.length, size.width, size.height);
      return (
        <div className="room-grid">
          {refs(arrangement.tiles).map((ref, i) => (
            <RoomTile
              key={tileKey(ref)}
              trackRef={ref}
              pinned={pinned}
              onPin={onPin}
              className={i === 0 ? 'room-main-tile' : undefined}
              style={{ width: shape.width || undefined, height: shape.height || undefined }}
            />
          ))}
        </div>
      );
    }
    case 'spotlight': {
      const focus = byKey.get(arrangement.main);
      const strip = refs(arrangement.strip);
      if (!focus) return null;
      const key = tileKey(focus);
      return (
        <FocusLayoutContainer data-strip={strip.length > 0 ? 'some' : 'none'}>
          <FocusLayout
            trackRef={focus}
            onParticipantClick={() => onPin(key)}
            className="room-tile room-main-tile"
            data-fit={focus.source === Track.Source.ScreenShare ? 'contain' : 'cover'}
            title={pinned === key ? t('unpin') : t('pin')}
          >
            <TileBody pinned={pinned === key} />
          </FocusLayout>
          {strip.length > 0 ? (
            <CarouselLayout tracks={strip} orientation={wide ? 'vertical' : 'horizontal'}>
              <RoomTile pinned={pinned} onPin={onPin} />
            </CarouselLayout>
          ) : null}
        </FocusLayoutContainer>
      );
    }
  }
}

/**
 * One's own small picture over the other person: dragged anywhere, it
 * snaps to the nearest corner (gliding there when motion is allowed).
 */
function SelfView({ trackRef, stage, onPin }: { trackRef: TrackReferenceOrPlaceholder; stage: RefObject<HTMLDivElement | null>; onPin: (key: string) => void }) {
  const t = useTranslations('room');
  const box = useRef<HTMLDivElement>(null);
  const [corner, setCorner] = useState<Corner>(() => startCorner(document.documentElement.dir === 'rtl'));
  const drag = useRef<{ x: number; y: number; moved: boolean } | null>(null);
  const dropped = useRef<DOMRect | null>(null);
  const suppress = useRef(false);

  // From where it was dropped to its corner: transform only.
  useLayoutEffect(() => {
    const el = box.current;
    const from = dropped.current;
    dropped.current = null;
    if (!el || !from) return;
    const to = el.getBoundingClientRect();
    if (!window.matchMedia('(prefers-reduced-motion: no-preference)').matches) return;
    el.style.transition = 'none';
    el.style.transform = `translate(${from.left - to.left}px, ${from.top - to.top}px)`;
    void el.offsetWidth;
    el.style.transition = 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)';
    el.style.transform = '';
  }, [corner]);

  return (
    <div
      ref={box}
      className="room-self"
      data-corner={corner}
      title={t('selfView')}
      onPointerDown={(e) => {
        if (e.button !== 0) return;
        drag.current = { x: e.clientX, y: e.clientY, moved: false };
        e.currentTarget.setPointerCapture(e.pointerId);
        e.currentTarget.style.transition = 'none';
      }}
      onPointerMove={(e) => {
        const d = drag.current;
        if (!d) return;
        const dx = e.clientX - d.x;
        const dy = e.clientY - d.y;
        if (!d.moved && Math.hypot(dx, dy) < 5) return;
        d.moved = true;
        e.currentTarget.style.transform = `translate(${dx}px, ${dy}px)`;
      }}
      onPointerUp={(e) => {
        const d = drag.current;
        drag.current = null;
        if (!d?.moved) return;
        suppress.current = true;
        const el = e.currentTarget;
        const rect = el.getBoundingClientRect();
        const area = stage.current?.getBoundingClientRect();
        el.style.transform = '';
        if (!area) return;
        const next = nearestCorner(rect.left + rect.width / 2 - area.left, rect.top + rect.height / 2 - area.top, area.width, area.height);
        dropped.current = rect;
        if (next === corner) {
          // Same corner: glide back from where it was let go.
          const to = el.getBoundingClientRect();
          if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
            el.style.transform = `translate(${rect.left - to.left}px, ${rect.top - to.top}px)`;
            void el.offsetWidth;
            el.style.transition = 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)';
            el.style.transform = '';
          }
          dropped.current = null;
        } else {
          setCorner(next);
        }
      }}
      onPointerCancel={(e) => {
        drag.current = null;
        e.currentTarget.style.transform = '';
      }}
      onClickCapture={(e) => {
        if (suppress.current) {
          suppress.current = false;
          e.stopPropagation();
          e.preventDefault();
        }
      }}
    >
      <RoomTile trackRef={trackRef} onPin={onPin} />
    </div>
  );
}

type RoundProps = {
  icon: RoomIconName;
  label: string;
  tip?: string;
  state?: 'on' | 'off' | 'active';
  pressed?: boolean;
  onClick: () => void;
  disabled?: boolean;
  className?: string;
  menu?: { open: boolean; id: string };
  buttonRef?: RefObject<HTMLButtonElement | null>;
};

function Round({ icon, label, tip, state, pressed, onClick, disabled, className, menu, buttonRef }: RoundProps) {
  return (
    <button
      ref={buttonRef}
      type="button"
      className={['room-round', className].filter(Boolean).join(' ')}
      aria-label={label}
      aria-pressed={pressed}
      aria-haspopup={menu ? 'menu' : undefined}
      aria-expanded={menu ? menu.open : undefined}
      aria-controls={menu?.open ? menu.id : undefined}
      data-state={state}
      onClick={onClick}
      disabled={disabled}
    >
      <RoomIcon name={icon} />
      <span className="room-tip" aria-hidden="true">
        {tip ?? label}
      </span>
    </button>
  );
}

function useApple(): boolean {
  return useSyncExternalStore(
    () => () => undefined,
    () => isApple(navigator.platform || navigator.userAgent),
    () => false,
  );
}

/** The microphone, Ctrl/⌘+D. */
function MicButton({ compact = false, onDeviceError }: { compact?: boolean; onDeviceError: (error: Error) => void }) {
  const t = useTranslations('room');
  const apple = useApple();
  const mic = useTrackToggle({ source: Track.Source.Microphone, onDeviceError });
  const label = mic.enabled ? t('micOff') : t('micOn');
  return (
    <Round
      icon={mic.enabled ? 'mic' : 'micOff'}
      label={label}
      tip={compact ? undefined : t('shortcut', { label, keys: shortcutLabel('D', apple) })}
      state={mic.enabled ? 'on' : 'off'}
      pressed={mic.enabled}
      disabled={mic.pending}
      onClick={() => void mic.toggle()}
    />
  );
}

function ControlBar({
  canPublish,
  mode,
  setMode,
  audioOnly,
  setAudioOnly,
  panel,
  pip,
  rec,
  sharingMine,
  onDeviceError,
  onShareError,
  onLeave,
}: {
  canPublish: boolean;
  mode: LayoutMode;
  setMode: (mode: LayoutMode) => void;
  audioOnly: boolean;
  setAudioOnly: (on: boolean) => void;
  panel: { name: string; open: boolean; toggle: () => void } | null;
  pip: { open: boolean; toggle: () => void } | null;
  rec: RecordingState;
  sharingMine: boolean;
  onDeviceError: (error: Error) => void;
  onShareError: (error: Error) => void;
  onLeave: () => void;
}) {
  const t = useTranslations('room');
  const r = useTranslations('recordings');
  const apple = useApple();
  const camera = useTrackToggle({ source: Track.Source.Camera, onDeviceError });
  const mic = useTrackToggle({ source: Track.Source.Microphone, onDeviceError });
  const screen = useTrackToggle({
    source: Track.Source.ScreenShare,
    captureOptions: { audio: true, selfBrowserSurface: 'exclude', surfaceSwitching: 'include' },
    onDeviceError: onShareError,
  });
  const shareable = useSyncExternalStore(
    () => () => undefined,
    () => canShareScreen(navigator),
    () => false,
  );
  const [menu, setMenu] = useState<'layout' | 'more' | null>(null);
  const menuId = useId();
  const menuRef = useRef<HTMLDivElement>(null);
  const layoutButton = useRef<HTMLButtonElement>(null);
  const moreButton = useRef<HTMLButtonElement>(null);

  // Ctrl/⌘+D and Ctrl/⌘+E, as on Meet.
  useEffect(() => {
    if (!canPublish) return;
    const onKey = (e: KeyboardEvent) => {
      const which = shortcutOf(e, apple);
      if (!which) return;
      e.preventDefault();
      void (which === 'mic' ? mic.toggle() : camera.toggle());
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [canPublish, apple, mic, camera]);

  // A menu closes on Escape (back to its button) and on a click elsewhere.
  useEffect(() => {
    if (!menu) return;
    const button = menu === 'layout' ? layoutButton : moreButton;
    menuRef.current?.querySelector<HTMLElement>('[role^="menuitem"]:not(:disabled)')?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setMenu(null);
        button.current?.focus();
      }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const items = Array.from(menuRef.current?.querySelectorAll<HTMLElement>('[role^="menuitem"]:not(:disabled)') ?? []).filter((el) => el.offsetParent !== null);
        const at = items.indexOf(document.activeElement as HTMLElement);
        const next = items[(at + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];
        next?.focus();
        e.preventDefault();
      }
    };
    const onDown = (e: PointerEvent) => {
      const target = e.target as Node;
      if (menuRef.current?.contains(target) || button.current?.contains(target)) return;
      setMenu(null);
    };
    document.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onDown);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('pointerdown', onDown);
    };
  }, [menu]);

  const camLabel = camera.enabled ? t('cameraOff') : t('cameraOn');
  const layouts = (
    <>
      <p className="room-menu-head">{t('layout')}</p>
      {LAYOUT_MODES.map((m) => (
        <button
          key={m}
          type="button"
          role="menuitemradio"
          aria-checked={mode === m}
          onClick={() => {
            setMode(m);
            setMenu(null);
          }}
        >
          <span className="room-check">{mode === m ? <RoomIcon name="check" className="room-check" /> : null}</span>
          {t(`layouts.${m}`)}
        </button>
      ))}
    </>
  );

  return (
    <footer className="room-bar" role="toolbar" aria-label={t('controls')}>
      {canPublish ? (
        <>
          <Round
            icon={mic.enabled ? 'mic' : 'micOff'}
            label={mic.enabled ? t('micOff') : t('micOn')}
            tip={t('shortcut', { label: mic.enabled ? t('micOff') : t('micOn'), keys: shortcutLabel('D', apple) })}
            state={mic.enabled ? 'on' : 'off'}
            pressed={mic.enabled}
            disabled={mic.pending}
            onClick={() => void mic.toggle()}
          />
          <Round
            icon={camera.enabled ? 'camera' : 'cameraOff'}
            label={camLabel}
            tip={t('shortcut', { label: camLabel, keys: shortcutLabel('E', apple) })}
            state={camera.enabled ? 'on' : 'off'}
            pressed={camera.enabled}
            disabled={camera.pending}
            onClick={() => void camera.toggle()}
          />
          {shareable ? (
            <Round
              icon={sharingMine ? 'screenStop' : 'screen'}
              label={sharingMine ? t('screenStop') : t('screen')}
              state={sharingMine ? 'active' : undefined}
              pressed={sharingMine}
              disabled={screen.pending}
              onClick={() => void screen.toggle()}
            />
          ) : null}
        </>
      ) : null}
      {pip ? <Round icon="pip" label={pip.open ? t('pipClose') : t('pip')} state={pip.open ? 'active' : undefined} pressed={pip.open} onClick={pip.toggle} /> : null}
      <Round
        icon="layout"
        label={t('layout')}
        className="room-wide-only"
        menu={{ open: menu === 'layout', id: menuId }}
        buttonRef={layoutButton}
        onClick={() => setMenu(menu === 'layout' ? null : 'layout')}
      />
      {panel ? (
        <Round
          icon="book"
          label={panel.open ? t('panelClose', { name: panel.name }) : t('panelOpen', { name: panel.name })}
          state={panel.open ? 'active' : undefined}
          pressed={panel.open}
          onClick={panel.toggle}
        />
      ) : null}
      <Round icon="more" label={t('more')} menu={{ open: menu === 'more', id: menuId }} buttonRef={moreButton} onClick={() => setMenu(menu === 'more' ? null : 'more')} />
      <button type="button" className="room-leave" onClick={onLeave} aria-label={t('leaveHint')} title={t('leaveHint')}>
        <RoomIcon name="leave" />
        <span className="room-leave-word" aria-hidden="true">
          {t('leave')}
        </span>
      </button>

      {menu === 'layout' ? (
        <div className="room-menu" role="menu" id={menuId} ref={menuRef} aria-label={t('layout')}>
          {layouts}
        </div>
      ) : null}
      {menu === 'more' ? (
        <div className="room-menu" role="menu" id={menuId} ref={menuRef} aria-label={t('more')}>
          <div className="room-narrow-only">
            {layouts}
            <hr />
          </div>
          <button
            type="button"
            role="menuitemcheckbox"
            aria-checked={audioOnly}
            onClick={() => {
              const next = !audioOnly;
              setAudioOnly(next);
              if (next && camera.enabled) void camera.toggle(false);
              setMenu(null);
            }}
          >
            <span className="room-check">{audioOnly ? <RoomIcon name="check" className="room-check" /> : <RoomIcon name="sound" className="room-check" />}</span>
            <span>
              {t('audioOnly')}
              <small>{t('audioOnlyHint')}</small>
            </span>
          </button>
          {rec.shown && rec.controls ? (
            <button
              type="button"
              role="menuitem"
              disabled={rec.pending || (!rec.on && rec.missing.length > 0)}
              onClick={() => {
                rec.toggle();
                setMenu(null);
              }}
            >
              <span className="room-check">
                <RoomIcon name="record" className="room-check" />
              </span>
              <span>
                {rec.on ? r('stop') : r('start')}
                {rec.missing.length > 0 ? <small>{r('missing', { names: rec.missing.join(r('separator')) })}</small> : null}
              </span>
            </button>
          ) : null}
        </div>
      ) : null}
    </footer>
  );
}
