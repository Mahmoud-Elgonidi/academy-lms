import type { CSSProperties } from 'react';
import { creatureSvg, islandBox, islandMarkup, sceneMarkup, type Look } from '@/lib/games/art';

/*
 * The world's drawings in the page (lib/games/art.ts, the port of
 * shared::games.*): the markup is built only from the catalog's checked
 * colours and the art's own numbers, any word in it escaped, never from what
 * a person typed. Decorative: each is aria-hidden, and the screen says in
 * words what it shows.
 */

/** A creature: its look drawn, a shadow when not hatched yet, alive (wings, blinks) with motion. */
export function CreatureArt({ look, size = 84, shadow = false, motion = false, line, className, style }: {
  look: Look | null | undefined;
  size?: number;
  shadow?: boolean;
  motion?: boolean;
  line?: string;
  className?: string;
  style?: CSSProperties;
}) {
  return <span aria-hidden="true" className={className} style={{ display: 'inline-block', lineHeight: 0, ...style }} dangerouslySetInnerHTML={{ __html: creatureSvg(look, { size, shadow, motion, line }) }} />;
}

/** An island on the map, in its box (greyed under a cloud while locked). */
export function IslandArt({ islandKey, locked, colors, emoji }: { islandKey: string; locked: boolean; colors?: Record<string, string>; emoji?: string }) {
  const box = islandBox(islandKey);
  return (
    <span
      aria-hidden="true"
      style={{ position: 'absolute', inset: 0, width: box.width, height: box.height, display: 'block' }}
      dangerouslySetInnerHTML={{ __html: islandMarkup(islandKey, { locked, colors, emoji }) }}
    />
  );
}

/** An opened island's scene (390 × 330) with the creatures living in it. */
export function SceneArt({ islandKey, residents, colors }: { islandKey: string; residents: (Look | null | undefined)[]; colors?: Record<string, string> }) {
  return <span aria-hidden="true" style={{ display: 'contents' }} dangerouslySetInnerHTML={{ __html: sceneMarkup(islandKey, residents, colors) }} />;
}

/** The stars count's star, the egg of the next creature, and the lock of a game without practice. */
export function StarIcon({ size = 18, stroke = '#13294B', strokeWidth = 2 }: { size?: number; stroke?: string | null; strokeWidth?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke={stroke ?? 'none'} strokeWidth={strokeWidth} strokeLinejoin="round" />
    </svg>
  );
}

export function EggIcon({ width = 22, height = 27, belly = '#F3E3C3', body }: { width?: number; height?: number; belly?: string; body?: string }) {
  return (
    <svg width={width} height={height} viewBox="0 0 18 22" aria-hidden="true">
      <ellipse cx="9" cy="12" rx="8" ry="10" fill={belly} stroke="#13294B" strokeWidth="1.6" />
      <path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" strokeWidth="1.4" />
      {body ? (
        <>
          <circle cx="6" cy="7" r="1.6" fill={body} />
          <circle cx="12" cy="16" r="2" fill={body} />
        </>
      ) : null}
    </svg>
  );
}

export function LockIcon({ size = 11, color = '#FFFFFF', strokeWidth = 3 }: { size?: number; color?: string; strokeWidth?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={strokeWidth} strokeLinecap="round" aria-hidden="true">
      <rect x="5" y="11" width="14" height="10" rx="2" />
      <path d="M8 11V7a4 4 0 0 1 8 0v4" />
    </svg>
  );
}
