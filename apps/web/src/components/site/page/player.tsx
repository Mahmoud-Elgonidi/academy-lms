'use client';

import { useEffect, useRef, useState, type CSSProperties } from 'react';
import type { Media } from '@/lib/site/page';

/*
 * The page's videos (6.4). Nothing plays or loads before it is near the
 * screen. An autoplay the academy switched on plays muted in view even when
 * the visitor asks for less motion (it keeps its controls, so it can be
 * paused); a background or ambient video plays only when the visitor allows
 * motion. Nothing starts by itself for a visitor saving data. YouTube and
 * Vimeo load nothing from the provider before a click (a facade); then the
 * player opens without cookies where it allows.
 */

const still = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const saving = () => Boolean((navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData);

/**
 * Makes sure the browser sees the video as muted before play(): a muted
 * video is the only one Chrome, Safari and Firefox let start without a click,
 * and the property is what they check, not React's attribute.
 */
export function silence(video: HTMLVideoElement) {
  video.defaultMuted = true;
  video.muted = true;
  video.setAttribute('muted', '');
}

/** A solid play mark (the dot around it is the button's). */
function PlayMark() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M9 6.5v11l9-5.5z" fill="currentColor" />
    </svg>
  );
}

/**
 * Starts a muted video once it nears the screen and pauses it when it leaves
 * (not saving data; motion allowed unless `anyMotion`: the academy's own
 * choice of autoplay with controls). A play the browser refuses leaves the
 * video paused (`onPlaying(false)`). Once the visitor pauses or unmutes it
 * with its controls, the video is theirs: nothing starts, stops or mutes it
 * again. Returns the clean-up, or null when the video does not start by itself
 * here.
 */
export function watchAutoplay(video: HTMLVideoElement, anyMotion: boolean, onPlaying: (playing: boolean) => void): (() => void) | null {
  if ((!anyMotion && still()) || saving() || typeof IntersectionObserver === 'undefined') return null;
  let active = true;
  let inView = false;
  let waitingForData = false;
  let ownPause = false;
  const play = () => {
    video.removeEventListener('canplay', play);
    waitingForData = false;
    if (!active || !inView) return;
    silence(video);
    let started: Promise<void>;
    try {
      started = Promise.resolve(video.play());
    } catch (e) {
      started = Promise.reject(e);
    }
    started.then(
      () => { if (active) onPlaying(inView); },
      () => { if (active) onPlaying(false); },
    );
  };
  const seen = new IntersectionObserver(([entry]) => {
    if (!active) return;
    if (entry?.isIntersecting) {
      inView = true;
      if (video.readyState < 3 /* HAVE_FUTURE_DATA */) {
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
      if (!video.paused) {
        ownPause = true;
        video.pause();
      }
      video.removeEventListener('canplay', play);
      waitingForData = false;
      onPlaying(false);
    }
  }, { rootMargin: '200px 0px' });
  const stop = () => {
    active = false;
    seen.disconnect();
    video.removeEventListener('canplay', play);
    video.removeEventListener('pause', paused);
    video.removeEventListener('volumechange', sounded);
  };
  // The visitor's own pause or sound: the video is theirs from now on.
  const paused = () => {
    if (ownPause) ownPause = false;
    else stop();
  };
  const sounded = () => {
    if (!video.muted) stop();
  };
  video.addEventListener('pause', paused);
  video.addEventListener('volumechange', sounded);
  seen.observe(video);
  return stop;
}

/** `watchAutoplay` for a component: whether the video is playing by itself. */
function useAutoplay(ref: React.RefObject<HTMLVideoElement | null>, on: boolean, anyMotion = false) {
  const [playing, setPlaying] = useState(false);
  useEffect(() => {
    const video = ref.current;
    if (!on || !video) return;
    return watchAutoplay(video, anyMotion, setPlaying) ?? undefined;
  }, [ref, on, anyMotion]);
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
 * (even under reduced motion: the academy chose it) and keeps its controls,
 * so it can be paused. Ambient (a section's own media: the opening, image
 * beside text), it plays muted and loops in view without controls, like a
 * moving picture; where it cannot play by itself (reduced motion, saving data)
 * the play button stays.
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
  const playing = useAutoplay(ref, (autoplay || ambient) && !asked, autoplay && !ambient);
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
