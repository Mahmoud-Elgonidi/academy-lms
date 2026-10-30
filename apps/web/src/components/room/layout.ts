/**
 * How the lesson room arranges what it shows, as on Meet or Zoom, worked out
 * from plain facts about the tiles (no LiveKit objects), so it is tested on
 * its own. A tile is someone's camera (or their name when it is off) or a
 * shared screen.
 */

export type LayoutMode = 'auto' | 'grid' | 'spotlight';

export const LAYOUT_MODES: readonly LayoutMode[] = ['auto', 'grid', 'spotlight'];

export type TileFact = {
  /** Stable key: the person and the source. */
  key: string;
  /** This browser's own camera or screen. */
  local: boolean;
  /** A shared screen rather than a camera. */
  screen: boolean;
};

export type Arrangement =
  | { kind: 'empty' }
  /** One tile filling the stage. */
  | { kind: 'solo'; main: string }
  /** The other person filling the stage, one's own camera small in a corner. */
  | { kind: 'pair'; main: string; self: string }
  /** Everyone the same size. */
  | { kind: 'grid'; tiles: string[] }
  /** One large (a shared screen first), the rest in a strip. */
  | { kind: 'spotlight'; main: string; strip: string[] };

/**
 * The arrangement: a pinned tile is in the spotlight whatever the mode; a
 * shared screen puts the room in the spotlight unless the grid was chosen;
 * otherwise two people (one of them here) are a pair and more are a grid.
 */
export function arrange(tiles: readonly TileFact[], mode: LayoutMode, pinned: string | null = null): Arrangement {
  if (tiles.length === 0) return { kind: 'empty' };
  if (tiles.length === 1) return { kind: 'solo', main: tiles[0]!.key };

  const keys = tiles.map((tile) => tile.key);
  const pin = pinned && keys.includes(pinned) ? pinned : null;
  const sharing = tiles.some((tile) => tile.screen);

  if (pin || mode === 'spotlight' || (mode === 'auto' && sharing)) {
    const main = pin ?? focusOf(tiles);
    return { kind: 'spotlight', main, strip: keys.filter((key) => key !== main) };
  }
  if (mode === 'auto' && tiles.length === 2) {
    const self = tiles.find((tile) => tile.local && !tile.screen);
    const other = tiles.find((tile) => !tile.local && !tile.screen);
    if (self && other) return { kind: 'pair', main: other.key, self: self.key };
  }
  return { kind: 'grid', tiles: keys };
}

/** What deserves the large place: someone else's screen, then one's own, then someone else's camera, then one's own. */
export function focusOf(tiles: readonly TileFact[]): string {
  const ranked = [
    tiles.find((tile) => tile.screen && !tile.local),
    tiles.find((tile) => tile.screen),
    tiles.find((tile) => !tile.local),
    tiles[0],
  ];
  return ranked.find((tile) => tile !== undefined)!.key;
}

/** The key the large place shows for an arrangement (picture-in-picture shows it too). */
export function mainOf(arrangement: Arrangement): string | null {
  switch (arrangement.kind) {
    case 'empty':
      return null;
    case 'grid':
      return arrangement.tiles[0] ?? null;
    default:
      return arrangement.main;
  }
}

export type GridShape = { cols: number; rows: number; width: number; height: number };

/**
 * Columns and rows that give `count` tiles of `aspect` (width / height) the
 * largest size inside a `width` × `height` stage with `gap` between them,
 * so the grid always fits without scrolling.
 */
export function gridShape(count: number, width: number, height: number, aspect = 16 / 9, gap = 8): GridShape {
  if (count <= 0 || width <= 0 || height <= 0) return { cols: 1, rows: 1, width: 0, height: 0 };
  let best: GridShape = { cols: 1, rows: count, width: 0, height: 0 };
  for (let cols = 1; cols <= count; cols++) {
    const rows = Math.ceil(count / cols);
    const cellW = (width - gap * (cols - 1)) / cols;
    const cellH = (height - gap * (rows - 1)) / rows;
    if (cellW <= 0 || cellH <= 0) continue;
    const tileW = Math.min(cellW, cellH * aspect);
    const tileH = tileW / aspect;
    if (tileW * tileH >= best.width * best.height) best = { cols, rows, width: Math.floor(tileW), height: Math.floor(tileH) };
  }
  return best;
}

export type Corner = 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';

/** The stage's corner nearest to a point (the self-view snaps there when dropped). */
export function nearestCorner(x: number, y: number, width: number, height: number): Corner {
  const vertical = y < height / 2 ? 'top' : 'bottom';
  const horizontal = x < width / 2 ? 'left' : 'right';
  return `${vertical}-${horizontal}`;
}

/** Where one's own small picture starts: the bottom corner at the end of the line (right in English, left in Arabic). */
export function startCorner(rtl: boolean): Corner {
  return rtl ? 'bottom-left' : 'bottom-right';
}

/** "04:05", "1:02:05" past an hour: the time since joining. */
export function elapsed(seconds: number): string {
  const s = Math.max(0, Math.floor(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const two = (n: number) => String(n).padStart(2, '0');
  return h > 0 ? `${h}:${two(m)}:${two(s % 60)}` : `${two(m)}:${two(s % 60)}`;
}
