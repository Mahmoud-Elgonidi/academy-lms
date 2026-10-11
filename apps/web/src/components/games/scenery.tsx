import { LockIcon } from './art';
import type { TileArt } from '@/lib/games/world';

/*
 * The world's small pictures around the islands (the approved design, as the
 * /my panel draws them): a game's tile, the music button's note, the waves
 * and twinkles of the sea, and its life (a fish, a boat, clouds). Game art:
 * its own palette, not the academy's. Decorative, aria-hidden.
 */

/** A game's tile picture. */
export function Tile({ art }: { art: TileArt }) {
  switch (art) {
    case 'gates':
      return (
        <svg width="50" height="44" viewBox="0 0 50 44" aria-hidden="true">
          <ellipse cx="34" cy="22" rx="10" ry="16" fill="none" stroke="#FFD43B" strokeWidth="5" />
          <g transform="translate(2 12) scale(0.3)">
            <ellipse cx="50" cy="44" rx="30" ry="25" fill="#FFFFFF" stroke="#13294B" strokeWidth="7" />
            <path d="M22 40 L6 46 L22 52 Z" fill="#FF922B" />
            <path d="M48 40 C50 14 80 8 84 24 C78 34 66 42 56 46 Z" fill="#4DABF7" stroke="#13294B" strokeWidth="7" />
          </g>
        </svg>
      );
    case 'balloons':
      return (
        <svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true">
          <ellipse cx="16" cy="18" rx="11" ry="13" fill="#FF6B6B" stroke="#13294B" strokeWidth="2.5" />
          <ellipse cx="32" cy="16" rx="10" ry="12" fill="#38D9A9" stroke="#13294B" strokeWidth="2.5" />
          <path d="M16 31 Q18 38 15 44 M32 28 Q34 36 31 44" stroke="#13294B" strokeWidth="1.6" fill="none" />
        </svg>
      );
    case 'cards':
      return (
        <svg width="46" height="46" viewBox="0 0 46 46" aria-hidden="true">
          <g transform="rotate(-12 18 24)">
            <rect x="6" y="8" width="22" height="30" rx="4" fill="#6236E0" stroke="#13294B" strokeWidth="2.5" />
          </g>
          <g transform="rotate(10 30 24)">
            <rect x="20" y="6" width="22" height="30" rx="4" fill="#FFFFFF" stroke="#13294B" strokeWidth="2.5" />
            <path d="M24 16 h14 M24 22 h10" stroke="#13294B" strokeWidth="2.5" strokeLinecap="round" />
          </g>
        </svg>
      );
    case 'bridge':
      return (
        <svg width="50" height="40" viewBox="0 0 90 60" aria-hidden="true">
          <ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" strokeWidth="3" />
          <ellipse cx="48" cy="34" rx="28" ry="20" fill="#2F9E44" stroke="#13294B" strokeWidth="3" />
          <text x="48" y="41" textAnchor="middle" fontSize="20" fontWeight="800" fill="#FFFFFF">
            م
          </text>
        </svg>
      );
    default:
      return <LockIcon size={26} color="#13294B" strokeWidth={2.6} />;
  }
}

export function MusicIcon({ off }: { off: boolean }) {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M9 18V5l12-2v13" />
      <circle cx="6" cy="18" r="3" fill={off ? 'none' : '#FFD43B'} />
      <circle cx="18" cy="16" r="3" fill={off ? 'none' : '#FFD43B'} />
      {off ? <path d="M3 3l18 18" /> : null}
    </svg>
  );
}

/** Waves and twinkles over a sea this tall (390 wide), spread down it. */
export function Waves({ height }: { height: number }) {
  const rows = Math.max(1, Math.floor(height / 120));
  const waves = Array.from({ length: rows }, (_, i) => [i % 2 ? 30 : 300, 110 + i * 120, ['', 'gw-d2', 'gw-d3'][i % 3]!, i % 2 ? 0.55 : 0.6] as const);
  const twinkles = Array.from({ length: Math.ceil(rows / 2) }, (_, i) => [i % 2 ? 350 : 200, 170 + i * 240, i % 2 ? 10 : 12, ['', 'gw-d2', 'gw-d3'][i % 3]!] as const);
  return (
    <div aria-hidden="true">
      {waves.map(([x, y, d, o]) => (
        <svg key={`${x}-${y}`} className={`gw-wave ${d} gw-abs`} style={{ left: x, top: y }} width="40" height="12" viewBox="0 0 40 12">
          <path d="M2 8 Q10 0 18 8 T34 8" stroke="#FFFFFF" strokeWidth="3" fill="none" strokeLinecap="round" opacity={o} />
        </svg>
      ))}
      {twinkles.map(([x, y, s, d]) => (
        <svg key={`${x}-${y}`} className={`gw-twinkle ${d} gw-abs`} style={{ left: x, top: y }} width={s} height={s} viewBox="0 0 14 14">
          <path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="#FFFFFF" />
        </svg>
      ))}
    </div>
  );
}

/** A fish that jumps, a boat that rocks, and clouds drifting over. */
export function SeaLife({ height }: { height: number }) {
  const cloud = 'M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z';
  return (
    <>
      <div aria-hidden="true" className="gw-fish gw-abs" style={{ left: 340, top: Math.max(200, height - 140) }}>
        <svg width="24" height="14" viewBox="0 0 24 14">
          <path d="M2 7 C6 1 14 1 18 7 C14 13 6 13 2 7 Z" fill="#FF922B" />
          <path d="M18 7 L24 2 L24 12 Z" fill="#FF922B" />
          <circle cx="6" cy="6" r="1.4" fill="#13294B" />
        </svg>
      </div>
      <div aria-hidden="true" className="gw-boat gw-abs" style={{ left: 18, top: Math.max(240, height - 96) }}>
        <svg width="58" height="54" viewBox="0 0 58 54">
          <path d="M28 4 V40" stroke="#7A4A22" strokeWidth="3" />
          <path d="M30 6 L52 36 L30 36 Z" fill="#FFFFFF" />
          <path d="M26 10 L10 34 L26 34 Z" fill="#FFE066" />
          <path d="M28 2 L38 6 L28 10 Z" fill="#FF6B6B" />
          <path d="M4 40 H54 L46 52 H12 Z" fill="#C9622B" />
          <path d="M4 40 H54" stroke="#8C3F16" strokeWidth="2" />
        </svg>
      </div>
      <div aria-hidden="true">
        <svg className="gw-cloud gw-abs" style={{ left: 0, top: 96, opacity: 0.85 }} width="120" height="48" viewBox="0 0 110 44">
          <path d={cloud} fill="#FFFFFF" />
        </svg>
        <svg className="gw-cloud gw-d2 gw-abs" style={{ left: 0, top: 400, opacity: 0.6 }} width="90" height="36" viewBox="0 0 110 44">
          <path d={cloud} fill="#FFFFFF" />
        </svg>
      </div>
    </>
  );
}
