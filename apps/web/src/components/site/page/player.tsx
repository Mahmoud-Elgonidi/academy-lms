'use client';

import { useEffect, useRef, useState, type CSSProperties } from 'react';
import type { Media } from '@/lib/site/page';

/*
 * The page's videos (6.4). Nothing plays or loads before it is near the
 * screen; a background video plays only when the visitor allows motion and is
 * not saving data. YouTube and Vimeo load nothing from the provider before a
 * click (a facade); then the player opens without cookies where it allows.
 */

const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const saving = () => Boolean((navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData);

/** A solid play mark (the dot around it is the button's). */
function PlayMark() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M9 6.5v11l9-5.5z" fill="currentColor" />
    </svg>
  );
}

/** Starts a muted video once it nears the screen (motion allowed, not saving data). */
function useAutoplay(ref: React.RefObject<HTMLVideoElement | null>, on: boolean) {
  const [playing, setPlaying] = useState(false);
  useEffect(() => {
    const video = ref.current;
    if (!on || !video || still() || saving() || !('IntersectionObserver' in window)) return;
    let active = true;
    let inView = false;
    let waitingForData = false;
    const play = () => {
      video.removeEventListener('canplay', play);
      waitingForData = false;
      if (!active || !inView) return;
      void video.play().then(
        () => { if (active) setPlaying(inView); },
        () => { if (active) setPlaying(false); },
      );
    };
    const seen = new IntersectionObserver(([entry]) => {
      if (!active) return;
      if (entry?.isIntersecting) {
        inView = true;
        if (video.readyState < HTMLMediaElement.HAVE_FUTURE_DATA) {
          if (!waitingForData) {
            waitingForData = true;
            video.addEventListener('canplay', play, { once: true });
          }
          if (video.preload === 'none') {
            video.preload = 'auto';
            video.load();
          }
        } else {
          play();
        }
      } else {
        inView = false;
        video.pause();
        video.removeEventListener('canplay', play);
        waitingForData = false;
        setPlaying(false);
      }
    }, { rootMargin: '200px 0px' });
    seen.observe(video);
    return () => {
      active = false;
      seen.disconnect();
      video.removeEventListener('canplay', play);
    };
  }, [ref, on]);
  return playing;
}

/** A video behind the hero's words: muted, looping, never a sound, hidden from screen readers. The poster is the first paint. */
export function BackgroundVideo({ video, poster }: { video: Media; poster: Media | null }) {
  const ref = useRef<HTMLVideoElement>(null);
  const playing = useAutoplay(ref, true);
  return (
    <div className="sp-bgvideo" aria-hidden="true" data-playing={playing ? '' : undefined}>
      {poster ? (
        // eslint-disable-next-line @next/next/no-img-element -- the academy's own poster, on its public files
        <img src={poster.url} alt="" width={poster.width ?? undefined} height={poster.height ?? undefined} loading="eager" fetchPriority="high" decoding="async" />
      ) : null}
      <video ref={ref} muted loop playsInline preload="none" poster={poster?.url} tabIndex={-1}>
        <source src={video.url} type={video.mime} />
      </video>
    </div>
  );
}

/**
 * An uploaded video in a frame: the poster with a play button, the player
 * with its controls once asked for. With autoplay, it plays muted in view
 * (motion allowed) and keeps its controls. Ambient (a section's own media:
 * the opening, image beside text), it plays muted and loops in view without
 * controls, like a moving picture; where it cannot play by itself (reduced
 * motion, saving data) the play button stays.
 */
export function FramedVideo({ video, poster, label, ratio, autoplay = false, ambient = false }: {
  video: Media;
  poster: Media | null;
  label: string;
  ratio: string;
  autoplay?: boolean;
  ambient?: boolean;
}) {
  const ref = useRef<HTMLVideoElement>(null);
  const [asked, setAsked] = useState(false);
  const playing = useAutoplay(ref, (autoplay || ambient) && !asked);
  useEffect(() => {
    if (asked) void ref.current?.play().catch(() => undefined);
  }, [asked]);
  const showCover = !asked && (ambient ? !playing : !autoplay);
  return (
    <div className="sp-frame sp-video" style={{ aspectRatio: ratio } as CSSProperties} data-ambient={ambient ? '' : undefined}>
      <video
        ref={ref}
        playsInline
        preload="none"
        poster={poster?.url}
        controls={asked || (autoplay && !ambient)}
        muted={(autoplay || ambient) && !asked}
        loop={autoplay || ambient}
        aria-label={label}
      >
        <source src={video.url} type={video.mime} />
      </video>
      {showCover ? (
        <button type="button" className="sp-play" onClick={() => setAsked(true)} aria-label={label}>
          <span className="sp-play-dot"><PlayMark /></span>
        </button>
      ) : null}
    </div>
  );
}

/** A YouTube or Vimeo video behind a click: nothing from the provider loads before it. */
export function Facade({ embed, poster, label, ratio }: { embed: string; poster: Media | null; label: string; ratio: string }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="sp-frame sp-video" style={{ aspectRatio: ratio } as CSSProperties} data-empty={poster ? undefined : ''}>
      {open ? (
        <iframe src={embed} title={label} allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" />
      ) : (
        <button type="button" className="sp-play" onClick={() => setOpen(true)} aria-label={label}>
          {poster ? (
            // eslint-disable-next-line @next/next/no-img-element -- the academy's own poster
            <img src={poster.url} alt="" width={poster.width ?? undefined} height={poster.height ?? undefined} loading="lazy" decoding="async" />
          ) : null}
          <span className="sp-play-dot"><PlayMark /></span>
        </button>
      )}
    </div>
  );
}
