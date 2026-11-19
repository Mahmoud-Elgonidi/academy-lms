import type { CSSProperties } from 'react';
import { ratioOf, type Media } from '@/lib/site/page';
import { FramedVideo } from './player';

/** Formats that may be see-through: their frame keeps the band's own colour, not the loading grey. */
const CLEAR = new Set(['image/png', 'image/webp', 'image/gif', 'image/svg+xml']);

/**
 * One of the academy's images, in a box of its own ratio so nothing jumps
 * while it loads (6.4). Lazy unless it is the first thing on the page.
 */
export function Picture({ media, alt, className, ratio, eager = false, shape }: {
  media: Media;
  alt?: string | null;
  className?: string;
  /** A fixed ratio ("4 / 3"); else the image's own. */
  ratio?: string | null;
  eager?: boolean;
  shape?: string;
}) {
  return (
    <div className={`sp-frame${className ? ` ${className}` : ''}`} data-shape={shape} data-clear={CLEAR.has(media.mime) ? '' : undefined} style={{ aspectRatio: ratio ?? ratioOf(media) } as CSSProperties}>
      {/* eslint-disable-next-line @next/next/no-img-element -- the academy's own file on its host; capped at 2560 px on upload, no optimiser (6.4) */}
      <img
        src={media.url}
        alt={alt ?? media.alt}
        width={media.width ?? undefined}
        height={media.height ?? undefined}
        loading={eager ? 'eager' : 'lazy'}
        fetchPriority={eager ? 'high' : undefined}
        decoding="async"
      />
    </div>
  );
}

/** A block's media: an image, or an uploaded video with its poster (it plays muted and loops in view, as a moving picture). */
export function BlockMedia({ media, video, poster, label, className, eager, shape }: {
  media: Media | null;
  video: Media | null;
  poster: Media | null;
  label: string;
  className?: string;
  eager?: boolean;
  shape?: string;
}) {
  if (video) {
    return (
      <div className={className} data-shape={shape}>
        <FramedVideo video={video} poster={poster} label={label} ratio={ratioOf(poster, '16 / 9')} ambient />
      </div>
    );
  }
  if (media) return <Picture media={media} className={className} eager={eager} shape={shape} />;
  return null;
}
