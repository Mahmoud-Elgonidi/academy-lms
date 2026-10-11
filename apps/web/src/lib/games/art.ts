/**
 * The games' drawings, as the panels draw them (the API's shared::games.creature, .island and
 * .scene): markup strings for dangerouslySetInnerHTML, moved by components/games/games.css.
 * The art keeps its own palette (game art, not the academy's brand); a colour from data is used
 * only when it is #rrggbb, else the default, and words are escaped.
 */

/** A creature's look, as the API gives it (the catalog's: kind, colours, voice pitch). */
export type Look = {
  kind?: string;
  body?: string;
  wing?: string;
  belly?: string;
  tuft?: string;
  beak?: string;
  crest?: string;
  pitch?: number;
} & Record<string, unknown>;

export type CreatureOptions = { size?: number; shadow?: boolean; motion?: boolean; line?: string; className?: string };

const HEX = /^#[0-9a-f]{6}$/i;

/** A colour from data when it is #rrggbb, else the fallback. */
export function hex(value: unknown, fallback: string): string {
  return typeof value === 'string' && HEX.test(value) ? value : fallback;
}

const ENTITIES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };

/** Text made safe for markup, as Blade's {{ }} does. */
export function esc(value: string | number | null | undefined): string {
  return String(value ?? '').replace(/[&<>"']/g, (ch) => ENTITIES[ch] ?? ch);
}

/**
 * A number as PHP writes a float into the Blade: 14 significant digits (4.6 / 3 is
 * 1.5333333333333), a whole number without a point. PHP's E notation for very large or
 * very small numbers never comes up in the art, so it is not copied.
 */
export function num(n: number): string {
  if (Number.isInteger(n) && Math.abs(n) < 1e15) return String(n);
  return String(Number(n.toPrecision(14)));
}

/** PHP's round(): halves away from zero. */
const round = (n: number) => Math.sign(n) * Math.round(Math.abs(n));

/**
 * A creature, drawn: its kind and colours from the catalog's look. size the width in px
 * (84); shadow draws the locked outline only; motion flaps wings and blinks.
 */
export function creatureSvg(look: Look | null | undefined, opts: CreatureOptions = {}): string {
  const lk: Record<string, unknown> = look ?? {};
  const kind = typeof lk.kind === 'string' ? lk.kind : 'bird';
  const shadow = opts.shadow ?? false;
  const motion = opts.motion ?? false;
  const size = opts.size === undefined ? 84 : Number.isFinite(opts.size) ? Math.trunc(opts.size) : 0;
  const L = hex(opts.line, '#13294B');
  const sh = '#D7CCF5';
  const c = (k: string, d: string) => (shadow ? sh : hex(lk[k], d));
  const body = c('body', '#74C0FC');
  const wing = c('wing', '#4DABF7');
  const belly = c('belly', '#E7F5FF');
  const tuft = c('tuft', '#FF6B6B');
  const beak = c('beak', '#FF922B');
  const crest = !shadow && lk.crest !== undefined && lk.crest !== null ? c('crest', '#FF922B') : null;
  const st = shadow ? 'none' : L;
  const limb = shadow ? sh : wing;
  const wl = motion ? 'cr-wl' : '';
  const wr = motion ? 'cr-wr' : '';
  const blink = motion ? 'cr-blink' : '';
  const unless = (svg: string) => (shadow ? '' : svg);
  const eyes = (x1: number, x2: number, y: number, r = 7) =>
    shadow
      ? ''
      : `<g class="${blink}"><circle cx="${num(x1)}" cy="${num(y)}" r="${num(r)}" fill="${L}"/><circle cx="${num(x2)}" cy="${num(y)}" r="${num(r)}" fill="${L}"/></g>` +
        `<circle cx="${num(x1 - 2)}" cy="${num(y - 2.5)}" r="${num(r / 3)}" fill="#FFFFFF"/><circle cx="${num(x2 - 2)}" cy="${num(y - 2.5)}" r="${num(r / 3)}" fill="#FFFFFF"/>`;
  const cheeks = (x1: number, x2: number, y: number) =>
    shadow ? '' : `<ellipse cx="${num(x1)}" cy="${num(y)}" rx="5" ry="3.2" fill="#FFA8C5"/><ellipse cx="${num(x2)}" cy="${num(y)}" rx="5" ry="3.2" fill="#FFA8C5"/>`;
  const smile = (x: number, y: number, w = 6) =>
    shadow
      ? ''
      : `<path d="M${num(x - w)} ${num(y)} Q${num(x)} ${num(y + w * 0.8)} ${num(x + w)} ${num(y)}" fill="none" stroke="${L}" stroke-width="3" stroke-linecap="round"/>`;

  const draw = (): string[] => {
    switch (kind) {
      case 'turtle':
        return [
          `<ellipse cx="30" cy="86" rx="11" ry="7" fill="${body}" stroke="${st}" stroke-width="3.5"/><ellipse cx="90" cy="86" rx="11" ry="7" fill="${body}" stroke="${st}" stroke-width="3.5"/>`,
          `<circle cx="60" cy="30" r="18" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<path d="M16 82 C16 52 36 40 60 40 C84 40 104 52 104 82 Z" fill="${wing}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/>`,
          `<path d="M28 78 C28 58 42 48 60 48 C78 48 92 58 92 78 Z" fill="${belly}"/>`,
          `<path d="M50 58 L60 52 L70 58 L70 70 L60 76 L50 70 Z" fill="${wing}" opacity=".7"/>`,
          `<rect x="12" y="78" width="96" height="10" rx="5" fill="${wing}" stroke="${st}" stroke-width="3.5"/>`,
          eyes(53, 67, 28, 4.6) + smile(60, 36, 4),
        ];
      case 'crab':
        return [
          `<g transform="translate(14 22) scale(1.32)">`,
          `<path d="M24 14 V6 M42 14 V6" stroke="${st}" stroke-width="2.5"/>`,
          unless(`<circle cx="24" cy="6" r="4.5" fill="#FFFFFF" stroke="${L}" stroke-width="2"/><circle cx="42" cy="6" r="4.5" fill="#FFFFFF" stroke="${L}" stroke-width="2"/><circle cx="24" cy="6" r="2" fill="${L}"/><circle cx="42" cy="6" r="2" fill="${L}"/>`),
          `<path d="M14 36 L6 44 M20 38 L16 48 M46 38 L50 48 M52 36 L60 44" stroke="${limb}" stroke-width="3" stroke-linecap="round"/>`,
          `<ellipse cx="33" cy="28" rx="22" ry="14" fill="${body}" stroke="${st}" stroke-width="2.5"/>`,
          unless(`<path d="M27 32 Q33 36 39 32" fill="none" stroke="${L}" stroke-width="2" stroke-linecap="round"/>`),
          `<g class="${motion ? 'cr-claw' : ''}"><path d="M56 22 C66 14 70 4 62 0 C60 6 56 8 52 6 C50 12 52 18 56 22 Z" fill="${body}" stroke="${st}" stroke-width="2.5" stroke-linejoin="round"/></g>`,
          `<path d="M10 22 C2 16 0 8 6 4 C8 10 12 12 14 10 C16 14 14 20 10 22 Z" fill="${body}" stroke="${st}" stroke-width="2.5" stroke-linejoin="round"/>`,
          `</g>`,
        ];
      case 'octopus':
        return [
          `<path d="M28 58 C28 72 18 86 24 96 C32 90 36 82 40 76 C40 86 38 96 46 102 C52 94 52 86 54 80 C56 88 58 98 66 100 C70 92 66 84 68 80 C72 86 78 96 88 96 C90 86 84 76 82 70 C86 76 94 84 98 82 C98 72 92 62 92 56 Z" fill="${body}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/>`,
          `<ellipse cx="60" cy="44" rx="34" ry="32" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          unless(`<circle cx="44" cy="24" r="4" fill="${wing}"/><circle cx="76" cy="28" r="3" fill="${wing}"/><circle cx="66" cy="18" r="2.5" fill="${wing}"/>`),
          eyes(48, 72, 46, 7) + cheeks(38, 82, 58) + smile(60, 60, 6),
        ];
      case 'whale':
        return [
          `<path d="M100 54 C108 40 116 34 118 40 C116 46 112 52 108 56 C114 58 118 62 116 68 C110 66 104 62 100 60 Z" fill="${wing}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/>`,
          `<path d="M8 62 C8 36 34 24 64 26 C92 28 108 44 106 60 C104 80 80 92 52 92 C26 92 8 82 8 62 Z" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<path d="M16 72 C32 86 72 90 98 70 C82 84 40 88 16 72 Z" fill="${belly}"/>`,
          unless(`<path class="${motion ? 'cr-spout' : ''}" d="M50 22 C46 12 42 8 36 6 M56 22 C58 12 62 8 68 6 M53 20 V4" fill="none" stroke="#74C0FC" stroke-width="3" stroke-linecap="round"/>`),
          eyes(30, 52, 54, 5.5) + cheeks(24, 60, 66) + smile(41, 66, 5),
        ];
      case 'monkey':
        return [
          `<path d="M84 70 C104 72 106 52 94 48" fill="none" stroke="${limb}" stroke-width="6" stroke-linecap="round"/>`,
          `<ellipse cx="60" cy="74" rx="22" ry="20" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<circle cx="30" cy="40" r="10" fill="${body}" stroke="${st}" stroke-width="4"/><circle cx="90" cy="40" r="10" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<circle cx="60" cy="42" r="26" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="50" rx="18" ry="14" fill="${belly}"/>`,
          eyes(51, 69, 40, 4.5) + smile(60, 54, 5),
        ];
      case 'sloth':
        return [
          `<path d="M30 76 C20 84 18 94 26 98 M90 76 C100 84 102 94 94 98" fill="none" stroke="${limb}" stroke-width="7" stroke-linecap="round"/>`,
          `<path d="M22 62 C22 32 40 18 60 18 C80 18 98 32 98 62 C98 88 80 100 60 100 C40 100 22 88 22 62 Z" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="50" rx="28" ry="21" fill="${belly}"/>`,
          unless(
            `<ellipse cx="46" cy="49" rx="10" ry="6" fill="${wing}" transform="rotate(-20 46 49)"/><ellipse cx="74" cy="49" rx="10" ry="6" fill="${wing}" transform="rotate(20 74 49)"/>` +
              `<path d="M42 49 Q46 52 50 49 M70 49 Q74 52 78 49" stroke="#FFFFFF" stroke-width="2.5" fill="none" stroke-linecap="round"/><ellipse cx="60" cy="58" rx="5" ry="3.5" fill="${L}"/>`,
          ),
          smile(60, 64, 6),
        ];
      case 'elephant':
        return [
          `<circle cx="24" cy="50" r="22" fill="${body}" stroke="${st}" stroke-width="4"/><circle cx="96" cy="50" r="22" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          unless(`<circle cx="24" cy="50" r="13" fill="${wing}"/><circle cx="96" cy="50" r="13" fill="${wing}"/>`),
          `<circle cx="60" cy="48" r="31" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<path d="M52 64 C50 82 52 94 64 98 C72 100 74 92 68 90 C62 88 62 80 66 66" fill="${body}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/>`,
          eyes(48, 72, 44, 5) + cheeks(40, 80, 58),
        ];
      case 'tiger':
        return [
          `<circle cx="30" cy="26" r="11" fill="${body}" stroke="${st}" stroke-width="4"/><circle cx="90" cy="26" r="11" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="56" rx="40" ry="36" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="70" rx="20" ry="15" fill="${belly}"/>`,
          unless(
            `<path d="M60 22 V34 M50 24 L54 34 M70 24 L66 34 M22 52 L34 54 M22 62 L32 62 M98 52 L86 54 M98 62 L88 62" stroke="${wing}" stroke-width="4" stroke-linecap="round"/>` +
              `<path d="M55 64 L65 64 L60 70 Z" fill="${L}"/>`,
          ),
          eyes(46, 74, 50, 6) + smile(60, 74, 5),
        ];
      case 'penguin':
        return [
          `<path d="M24 60 C10 66 8 78 16 82 C22 76 26 70 30 66 Z M96 60 C110 66 112 78 104 82 C98 76 94 70 90 66 Z" fill="${wing}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/>`,
          `<ellipse cx="60" cy="58" rx="34" ry="40" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="66" rx="23" ry="29" fill="${belly}"/>`,
          unless(`<path d="M44 98 L40 104 L52 102 Z M76 98 L80 104 L68 102 Z" fill="${beak}"/>`),
          eyes(49, 71, 40, 5) + cheeks(42, 78, 52),
          unless(`<path d="M54 48 L66 48 L60 56 Z" fill="${beak}" stroke="${L}" stroke-width="2.5" stroke-linejoin="round"/>`),
        ];
      case 'seal':
        return [
          `<path d="M14 80 C14 58 34 46 56 48 C78 50 100 60 106 80 C98 92 30 94 14 80 Z" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<path d="M98 66 C108 56 116 58 116 64 C112 68 106 72 102 74 Z" fill="${wing}" stroke="${st}" stroke-width="3"/>`,
          `<circle cx="40" cy="44" r="24" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="40" cy="54" rx="12" ry="8" fill="${belly}"/>`,
          unless(`<ellipse cx="40" cy="50" rx="4" ry="3" fill="${L}"/><path d="M28 54 H18 M28 58 L18 62 M52 54 H62 M52 58 L62 62" stroke="${L}" stroke-width="2" stroke-linecap="round"/>`),
          eyes(32, 48, 40, 4.5),
        ];
      case 'bear':
        return [
          `<circle cx="30" cy="28" r="13" fill="${body}" stroke="${st}" stroke-width="4"/><circle cx="90" cy="28" r="13" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          unless(`<circle cx="30" cy="28" r="6" fill="${wing}"/><circle cx="90" cy="28" r="6" fill="${wing}"/>`),
          `<circle cx="60" cy="58" r="38" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="70" rx="17" ry="13" fill="${belly}" stroke="${st}" stroke-width="2.5"/>`,
          unless(`<ellipse cx="60" cy="64" rx="6" ry="4.5" fill="${L}"/>`),
          eyes(46, 74, 50, 5) + cheeks(36, 84, 66) + smile(60, 74, 4),
        ];
      case 'fox':
        return [
          `<path d="M84 84 C106 86 116 66 108 58 C102 70 92 74 82 74 Z" fill="${body}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/>`,
          `<path d="M24 44 L30 8 L52 32 Z M96 44 L90 8 L68 32 Z" fill="${body}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/>`,
          unless(`<path d="M30 36 L33 18 L44 32 Z M90 36 L87 18 L76 32 Z" fill="${wing}"/>`),
          `<path d="M18 46 C18 72 38 92 60 94 C82 92 102 72 102 46 C92 34 76 30 60 30 C44 30 28 34 18 46 Z" fill="${body}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/>`,
          `<path d="M22 54 C32 70 46 78 60 80 C74 78 88 70 98 54 C88 64 74 70 60 70 C46 70 32 64 22 54 Z" fill="${belly}"/>`,
          unless(`<ellipse cx="60" cy="72" rx="5" ry="4" fill="${L}"/>`),
          eyes(46, 74, 54, 5.5),
        ];
      case 'lizard':
        return [
          `<path d="M84 76 C104 80 116 66 106 54 C98 48 90 56 96 62" fill="none" stroke="${st}" stroke-width="13" stroke-linecap="round"/>`,
          `<path d="M84 76 C104 80 116 66 106 54 C98 48 90 56 96 62" fill="none" stroke="${body}" stroke-width="7" stroke-linecap="round"/>`,
          `<path d="M44 84 L38 96 M70 84 L76 96" stroke="${st}" stroke-width="5" stroke-linecap="round"/>`,
          `<ellipse cx="60" cy="74" rx="32" ry="16" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="36" cy="50" rx="24" ry="19" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          unless(`<circle cx="62" cy="66" r="4" fill="${wing}"/><circle cx="76" cy="72" r="3" fill="${wing}"/><circle cx="52" cy="76" r="3" fill="${wing}"/>`),
          eyes(28, 46, 42, 6) + smile(36, 56, 6),
        ];
      case 'chick':
        return [
          unless(`<path d="M48 26 C44 12 54 6 58 14 C60 2 72 6 66 20 C76 14 80 24 70 30 Z" fill="${tuft}" stroke="${L}" stroke-width="3" stroke-linejoin="round"/>`),
          `<circle cx="60" cy="58" r="34" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<g class="${wl}"><ellipse cx="26" cy="62" rx="9" ry="14" fill="${wing}" stroke="${st}" stroke-width="3"/></g>`,
          `<g class="${wr}"><ellipse cx="94" cy="62" rx="9" ry="14" fill="${wing}" stroke="${st}" stroke-width="3"/></g>`,
          `<path d="M28 80 L36 74 L44 82 L52 74 L60 82 L68 74 L76 82 L84 74 L92 80 C92 96 78 102 60 102 C42 102 28 96 28 80 Z" fill="${shadow ? sh : '#F3E3C3'}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/>`,
          eyes(48, 72, 50, 6) + cheeks(38, 82, 62),
          unless(`<path d="M53 58 L67 58 L60 67 Z" fill="${beak}" stroke="${L}" stroke-width="2.5" stroke-linejoin="round"/>`),
        ];
      case 'dino':
        return [
          unless(`<path d="M58 16 L64 4 L70 16 Z M74 16 L82 6 L84 20 Z M88 22 L98 14 L96 28 Z" fill="${wing}" stroke="${L}" stroke-width="2.5" stroke-linejoin="round"/>`),
          `<path d="M44 70 C40 88 46 102 62 102 C78 102 86 88 82 70 Z" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<path d="M18 50 C18 26 38 14 62 14 C88 14 104 30 104 50 C104 66 92 76 76 76 L40 76 C26 76 18 64 18 50 Z" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<path d="M50 84 C50 94 70 94 72 84 Z" fill="${belly}"/>`,
          unless(
            `<path d="M34 64 L40 70 L46 64 L52 70 L58 64 L64 70 L70 64" fill="#FFFFFF" stroke="${L}" stroke-width="2" stroke-linejoin="round"/><circle cx="96" cy="40" r="2.5" fill="${L}"/>` +
              `<path d="M44 84 L34 88" stroke="${L}" stroke-width="4" stroke-linecap="round"/>`,
          ),
          eyes(46, 70, 38, 6),
        ];
      case 'dragon':
        return [
          `<g class="${wl}"><path d="M34 46 C16 20 0 26 4 44 C8 58 22 62 36 58 Z" fill="${wing}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/></g>`,
          `<g class="${wr}"><path d="M86 46 C104 20 120 26 116 44 C112 58 98 62 84 58 Z" fill="${wing}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/></g>`,
          `<path d="M84 84 C100 92 108 86 106 78" fill="none" stroke="${st}" stroke-width="9" stroke-linecap="round"/>`,
          `<path d="M84 84 C100 92 108 86 106 78" fill="none" stroke="${body}" stroke-width="4" stroke-linecap="round"/>`,
          unless(`<path class="${motion ? 'cr-flame' : ''}" d="M106 78 C102 68 108 62 112 66 C114 60 120 64 116 72 C114 78 110 80 106 78 Z" fill="${tuft}"/>`),
          `<ellipse cx="60" cy="60" rx="33" ry="31" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="72" rx="19" ry="14" fill="${belly}"/>`,
          unless(
            `<path d="M44 32 L38 16 L52 28 Z M76 32 L82 16 L68 28 Z" fill="${tuft}" stroke="${L}" stroke-width="2.5" stroke-linejoin="round"/>` +
              `<circle cx="56" cy="66" r="1.8" fill="${L}"/><circle cx="64" cy="66" r="1.8" fill="${L}"/>`,
          ),
          eyes(48, 72, 52, 6.5) + cheeks(38, 82, 64),
        ];
      default:
        return [
          crest ? `<path d="M48 22 L44 2 L54 16 L60 0 L66 16 L76 2 L72 22" fill="${crest}" stroke="${L}" stroke-width="3" stroke-linejoin="round"/>` : '',
          `<g class="${wl}"><path d="M32 48 C14 30 -2 36 2 50 C6 62 20 64 34 60 Z" fill="${wing}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/></g>`,
          `<g class="${wr}"><path d="M88 48 C106 30 122 36 118 50 C114 62 100 64 86 60 Z" fill="${wing}" stroke="${st}" stroke-width="4" stroke-linejoin="round"/></g>`,
          `<ellipse cx="60" cy="56" rx="34" ry="31" fill="${body}" stroke="${st}" stroke-width="4"/>`,
          `<ellipse cx="60" cy="69" rx="20" ry="14" fill="${belly}"/>`,
          `<path d="M54 26 C50 14 58 12 60 20 C62 12 70 14 66 26" fill="${tuft}" stroke="${st}" stroke-width="3.5" stroke-linejoin="round"/>`,
          eyes(48, 72, 50, 7) + cheeks(38, 82, 62),
          unless(
            `<path d="M53 58 L67 58 L60 67 Z" fill="${beak}" stroke="${L}" stroke-width="3" stroke-linejoin="round"/>` +
              `<path d="M50 86 L48 94 M70 86 L72 94" stroke="#FF922B" stroke-width="4" stroke-linecap="round"/>`,
          ),
        ];
    }
  };

  return (
    `<svg class="cr ${esc(opts.className ?? '')}" width="${size}" height="${num(round((size * 105) / 120))}" viewBox="0 0 120 105" style="overflow: visible" aria-hidden="true">` +
    draw().join('') +
    '</svg>'
  );
}

const ISLAND_BOXES: Record<string, { width: number; height: number }> = {
  volcano: { width: 170, height: 150 },
  penguins: { width: 180, height: 140 },
  monkeys: { width: 190, height: 170 },
  turtles: { width: 190, height: 130 },
  birds: { width: 200, height: 190 },
};

/** The box an island's markup is drawn in (an island the studio adds later gets the plain one). */
export function islandBox(key: string): { width: number; height: number } {
  return (Object.hasOwn(ISLAND_BOXES, key) ? ISLAND_BOXES[key] : undefined) ?? { width: 180, height: 140 };
}

const ISLAND_CLOUD =
  '<path d="M10 50 C0 50 0 34 14 34 C14 18 40 14 48 28 C56 10 92 10 94 30 C108 20 130 26 128 40 C150 38 160 54 146 56 Z" fill="#FFFFFF"/>';

/** The cloud over a locked island. */
const cloudOver = (locked: boolean, style: string, width: number, height: number) =>
  locked ? `<svg aria-hidden="true" class="gw-abs" style="${style}" width="${width}" height="${height}" viewBox="0 0 170 60">${ISLAND_CLOUD}</svg>` : '';

/**
 * An island on the map: key picks the art (an island the studio adds later gets the plain one
 * in its own colours and emoji), locked greys it under clouds. The caller puts it in a
 * position: relative box of islandBox(key).
 */
export function islandMarkup(key: string, opts: { locked?: boolean; colors?: Record<string, string>; emoji?: string } = {}): string {
  const locked = opts.locked ?? false;
  const art = locked ? 'locked-art' : '';
  switch (key) {
    case 'volcano':
      return [
        `<svg class="${art}" width="170" height="150" viewBox="-85 -110 170 150" aria-hidden="true">`,
        '<ellipse class="gw-foam" cx="0" cy="26" rx="80" ry="18" fill="#BFE6FF"/>',
        '<ellipse cx="0" cy="22" rx="72" ry="16" fill="#6B5B4D"/>',
        '<path d="M-66 18 L-22 -74 L22 -74 L66 18 Z" fill="#5C4B5E"/>',
        '<path d="M-66 18 L-22 -74 L-8 -74 L-34 18 Z" fill="#7A6680"/>',
        '<path d="M-22 -74 L22 -74 L17 -84 L-17 -84 Z" fill="#3D3140"/>',
        '<path class="gw-lava" d="M-15 -82 C-8 -90 8 -90 15 -82 L12 -75 L-12 -75 Z" fill="#FF6B3D"/>',
        '<path class="gw-lava" d="M-3 -75 C-8 -55 2 -42 -6 -22" stroke="#FF922B" stroke-width="5" fill="none" stroke-linecap="round"/>',
        '</svg>',
        '<span class="gw-smoke" style="left: 74px; top: 8px; width: 22px; height: 22px; background: #CED4DA"></span>',
        '<span class="gw-smoke gw-d2" style="left: 80px; top: 6px; width: 18px; height: 18px; background: #DEE2E6"></span>',
        '<span class="gw-smoke gw-d3" style="left: 70px; top: 10px; width: 20px; height: 20px; background: #CED4DA"></span>',
        cloudOver(locked, 'left: 0; top: 78px; opacity: .92', 170, 60),
      ].join('');
    case 'penguins':
      return [
        `<svg class="${art}" width="180" height="140" viewBox="-90 -95 180 140" aria-hidden="true">`,
        '<ellipse class="gw-foam" cx="0" cy="26" rx="88" ry="20" fill="#BFE6FF"/>',
        '<path d="M-76 18 L-60 -6 L-30 -12 L-10 -40 L8 -20 L30 -54 L52 -18 L74 -8 L80 18 Z" fill="#A5D8FF"/>',
        '<path d="M-72 12 L-58 -4 L-30 -9 L-10 -36 L6 -17 L30 -50 L50 -15 L72 -5 L76 12 Z" fill="#E7F5FF"/>',
        '<path d="M30 -50 L50 -15 L36 -10 Z M-10 -36 L6 -17 L-4 -12 Z" fill="#D0EBFF"/>',
        '<path d="M-52 8 A18 18 0 0 1 -16 8 Z" fill="#FFFFFF"/>',
        '<path d="M-38 8 A6 7 0 0 1 -30 8 Z" fill="#4DABF7"/>',
        '</svg>',
        '<span class="gw-slide gw-abs" style="left: 96px; top: 66px">',
        '<svg width="26" height="32" viewBox="0 0 44 56" aria-hidden="true"><ellipse cx="22" cy="32" rx="16" ry="20" fill="#1F2A44"/><ellipse cx="22" cy="36" rx="10" ry="14" fill="#FFFFFF"/><circle cx="17" cy="20" r="2.6" fill="#FFFFFF"/><circle cx="27" cy="20" r="2.6" fill="#FFFFFF"/><path d="M19 25 L25 25 L22 29 Z" fill="#FF922B"/></svg>',
        '</span>',
        cloudOver(locked, 'left: 10px; top: 78px; opacity: .85', 160, 50),
      ].join('');
    case 'monkeys':
      return [
        `<svg class="${art}" width="190" height="170" viewBox="-95 -120 190 170" aria-hidden="true">`,
        '<ellipse class="gw-foam" cx="0" cy="28" rx="90" ry="20" fill="#BFE6FF"/>',
        '<ellipse cx="0" cy="22" rx="84" ry="20" fill="#E9B44C"/>',
        '<ellipse cx="0" cy="17" rx="82" ry="18" fill="#FFE08A"/>',
        '<path d="M-72 12 C-70 -30 -40 -58 -4 -58 C34 -58 70 -34 72 10 C40 22 -40 22 -72 12 Z" fill="#2B8A3E"/>',
        '<path d="M-56 -8 C-48 -36 -24 -50 0 -50 C-20 -40 -36 -26 -44 -4 Z" fill="#40C057"/>',
        '<path d="M-30 -50 C-34 -74 -36 -90 -44 -104" fill="none" stroke="#7A4A22" stroke-width="7" stroke-linecap="round"/>',
        '<path d="M-44 -104 C-60 -116 -78 -110 -84 -98 C-70 -102 -56 -102 -44 -104 Z M-44 -104 C-30 -118 -12 -114 -6 -102 C-20 -104 -32 -104 -44 -104 Z M-44 -104 C-52 -94 -60 -82 -62 -72 C-54 -84 -48 -94 -44 -104 Z" fill="#2F9E44"/>',
        '<circle cx="-46" cy="-98" r="4" fill="#8C5A2B"/><circle cx="-40" cy="-97" r="4" fill="#8C5A2B"/>',
        '<path d="M36 -46 C40 -66 42 -80 48 -92" fill="none" stroke="#7A4A22" stroke-width="6" stroke-linecap="round"/>',
        '<path d="M48 -92 C34 -104 18 -100 12 -90 C24 -92 36 -92 48 -92 Z M48 -92 C60 -104 76 -100 82 -88 C70 -90 58 -90 48 -92 Z" fill="#2F9E44"/>',
        '<path d="M10 -20 C16 -28 26 -28 30 -20 C24 -24 16 -24 10 -20 Z" fill="#FFD43B"/>',
        '</svg>',
        '<span class="gw-monkey gw-abs" style="left: 30px; top: 6px">',
        '<svg width="30" height="32" viewBox="0 0 60 64" aria-hidden="true"><path d="M44 44 C58 44 58 30 50 28" fill="none" stroke="#8C5A2B" stroke-width="4" stroke-linecap="round"/><ellipse cx="30" cy="46" rx="14" ry="13" fill="#A9733E"/><circle cx="14" cy="22" r="6" fill="#A9733E"/><circle cx="46" cy="22" r="6" fill="#A9733E"/><circle cx="30" cy="24" r="15" fill="#A9733E"/><ellipse cx="30" cy="28" rx="10" ry="8" fill="#F3D7AE"/><circle cx="25" cy="22" r="2.5" fill="#13294B"/><circle cx="35" cy="22" r="2.5" fill="#13294B"/></svg>',
        '</span>',
        cloudOver(locked, 'left: 20px; top: 100px; opacity: .8', 150, 48),
      ].join('');
    case 'turtles':
      return [
        `<svg class="${art}" width="190" height="130" viewBox="-95 -80 190 130" aria-hidden="true">`,
        '<ellipse class="gw-foam" cx="0" cy="26" rx="92" ry="24" fill="#BFE6FF"/>',
        '<ellipse cx="0" cy="22" rx="84" ry="21" fill="#E9B44C"/>',
        '<ellipse cx="0" cy="17" rx="82" ry="19" fill="#FFE08A"/>',
        '<path d="M-60 14 L-74 30 L-46 22 Z M36 16 L52 32 L26 24 Z" fill="#51CF66"/>',
        '<path d="M54 0 C70 -8 86 -4 88 6 C86 16 70 18 56 12 Z" fill="#8CE99A"/>',
        '<path d="M74 2 Q78 -2 82 2" stroke="#13294B" stroke-width="2.5" fill="none" stroke-linecap="round"/>',
        '<path d="M-64 12 C-64 -40 -30 -62 0 -62 C34 -62 62 -40 62 12 Z" fill="#2B8A3E"/>',
        '<path d="M-56 6 C-56 -36 -26 -54 0 -54 C28 -54 54 -36 54 6 Z" fill="#37B24D"/>',
        '<path d="M-14 -42 L0 -50 L14 -42 L14 -26 L0 -18 L-14 -26 Z M-44 -16 L-30 -24 L-18 -16 L-18 -2 L-30 4 L-44 -2 Z M18 -16 L30 -24 L44 -16 L44 -2 L30 4 L18 -2 Z" fill="#51CF66"/>',
        '<path d="M-2 -56 C0 -66 -2 -72 -6 -78" stroke="#7A4A22" stroke-width="4" fill="none" stroke-linecap="round"/>',
        '<path d="M-6 -78 C-16 -84 -26 -80 -28 -72 C-20 -76 -12 -76 -6 -78 Z M-6 -78 C2 -86 12 -82 14 -74 C6 -76 0 -76 -6 -78 Z" fill="#2F9E44"/>',
        '</svg>',
        '<span class="gw-zzz gw-abs" style="left: 172px; top: 56px; font-size: 13px">z</span>',
        '<span class="gw-zzz gw-d2 gw-abs" style="left: 176px; top: 52px; font-size: 10px">z</span>',
        cloudOver(locked, 'left: 20px; top: 70px; opacity: .85', 150, 48),
      ].join('');
    case 'birds':
      return [
        `<svg class="${art}" width="200" height="190" viewBox="-100 -140 200 190" aria-hidden="true">`,
        '<ellipse class="gw-foam" cx="0" cy="22" rx="96" ry="28" fill="#BFE6FF"/>',
        '<ellipse cx="0" cy="16" rx="86" ry="24" fill="#E9B44C"/>',
        '<ellipse cx="0" cy="10" rx="84" ry="22" fill="#FFE08A"/>',
        '<path d="M-70 8 C-66 -36 -26 -58 4 -58 C40 -58 70 -32 72 6 C40 18 -40 20 -70 8 Z" fill="#2F9E44"/>',
        '<path d="M-66 2 C-62 -38 -24 -56 4 -56 C38 -56 66 -32 68 0 C36 10 -38 12 -66 2 Z" fill="#51CF66"/>',
        '<path d="M-48 -18 C-40 -40 -16 -50 6 -50 C-12 -42 -28 -32 -36 -12 Z" fill="#8CE99A"/>',
        '<path d="M22 -48 C28 -80 26 -100 18 -122" fill="none" stroke="#7A4A22" stroke-width="9" stroke-linecap="round"/>',
        '<path d="M23 -52 C28 -80 26 -98 19 -118" fill="none" stroke="#A86B35" stroke-width="3" stroke-linecap="round"/>',
        '<path d="M18 -122 C0 -136 -26 -130 -36 -114 C-18 -120 0 -120 18 -122 Z M18 -122 C36 -138 60 -132 68 -116 C52 -122 36 -122 18 -122 Z" fill="#2F9E44"/>',
        '<path d="M18 -122 C8 -112 -6 -96 -10 -84 C2 -98 10 -108 18 -122 Z M18 -122 C30 -110 42 -96 46 -84 C34 -98 26 -108 18 -122 Z" fill="#40C057"/>',
        '<path d="M4 -122 Q18 -112 32 -122 Q30 -114 18 -112 Q6 -114 4 -122 Z" fill="#B07A3B"/>',
        '<ellipse cx="18" cy="-127" rx="6" ry="8" fill="#F3E3C3"/>',
        '<circle cx="-40" cy="-16" r="4" fill="#FF8FA3"/><circle cx="-28" cy="-28" r="3" fill="#FFD43B"/><circle cx="44" cy="-10" r="3.5" fill="#FF8FA3"/>',
        '<ellipse cx="-76" cy="14" rx="8" ry="5" fill="#9AA5B1"/><ellipse cx="72" cy="18" rx="6" ry="4" fill="#9AA5B1"/>',
        '</svg>',
        cloudOver(locked, 'left: 25px; top: 110px; opacity: .85', 150, 48),
      ].join('');
    default: {
      const land = hex(opts.colors?.land, '#51CF66');
      const accent = hex(opts.colors?.accent, '#FFD43B');
      return [
        `<svg class="${art}" width="180" height="140" viewBox="-90 -95 180 140" aria-hidden="true">`,
        '<ellipse class="gw-foam" cx="0" cy="26" rx="88" ry="22" fill="#BFE6FF"/>',
        '<ellipse cx="0" cy="20" rx="80" ry="19" fill="#E9B44C"/>',
        '<ellipse cx="0" cy="15" rx="78" ry="17" fill="#FFE08A"/>',
        `<path d="M-62 12 C-60 -32 -30 -56 0 -56 C32 -56 62 -32 62 10 C34 20 -34 20 -62 12 Z" fill="${land}"/>`,
        '<path d="M-48 -6 C-42 -32 -20 -46 2 -46 C-16 -38 -30 -26 -36 -4 Z" fill="#FFFFFF" opacity=".25"/>',
        `<circle cx="-22" cy="-14" r="4" fill="${accent}"/><circle cx="26" cy="-6" r="3.5" fill="${accent}"/>`,
        '</svg>',
        `<span class="gw-abs" style="left: 0; right: 0; top: 30px; text-align: center; font-size: 30px; line-height: 1">${esc(opts.emoji)}</span>`,
        cloudOver(locked, 'left: 15px; top: 76px; opacity: .85', 150, 48),
      ].join('');
    }
  }
}

/** Where the residents live in each scene: left, top, size, motion class. */
type Spot = readonly [number, number, number, string];

const SPOTS: Record<string, readonly Spot[]> = {
  birds: [[160, 70, 96, 'gw-fig8'], [96, 168, 70, 'gw-perch'], [318, 160, 50, 'gw-peek']],
  turtles: [[226, 226, 62, 'gw-perch'], [296, 262, 50, 'gw-perch'], [24, 150, 66, 'gw-swim']],
  monkeys: [[150, 150, 80, 'gw-monkey'], [40, 196, 66, 'gw-perch'], [290, 210, 60, 'gw-perch']],
  penguins: [[160, 196, 74, 'gw-slide'], [60, 222, 60, 'gw-perch'], [290, 214, 64, 'gw-perch']],
  volcano: [[60, 220, 70, 'gw-perch'], [270, 230, 66, 'gw-perch'], [170, 120, 80, 'gw-fig8']],
};

const PLAIN_SPOTS: readonly Spot[] = [[150, 170, 84, 'gw-perch'], [50, 210, 64, 'gw-perch'], [280, 210, 64, 'gw-perch']];

const SUN =
  '<svg class="gw-rays gw-abs" style="left: 14px; top: 14px" width="100" height="100" viewBox="-50 -50 100 100"><path d="M0 -50 L6 -34 L-6 -34 Z M0 50 L6 34 L-6 34 Z M-50 0 L-34 6 L-34 -6 Z M50 0 L34 6 L34 -6 Z M-35 -35 L-21 -27 L-27 -21 Z M35 35 L21 27 L27 21 Z M35 -35 L27 -21 L21 -27 Z M-35 35 L-27 21 L-21 27 Z" fill="#FFE066"/><circle r="26" fill="#FFE066"/></svg>';

const SCENE_CLOUD =
  '<svg class="gw-cloud gw-abs" style="left: 0; top: 40px; opacity: .9" width="110" height="44" viewBox="0 0 110 44"><path d="M14 40 C2 40 2 24 14 24 C14 10 34 6 40 18 C46 4 72 4 74 20 C86 14 100 22 96 32 C106 34 104 40 96 40 Z" fill="#FFFFFF"/></svg>';

const TURTLE_SWIMMERS: readonly (readonly [number, number, string, string, string, string])[] = [
  [110, 160, 'أ', '#2F9E44', '#40C057', ''],
  [150, 216, 'ب', '#0CA678', '#20C997', 'gw-d2'],
  [70, 262, 'ت', '#2B8A3E', '#37B24D', 'gw-d3'],
];

const SNOW: readonly (readonly [number, number, string])[] = [
  [30, 20, ''],
  [120, 60, 'gw-d2'],
  [210, 10, 'gw-d3'],
  [290, 80, ''],
  [70, 110, 'gw-d3'],
  [350, 30, 'gw-d2'],
];

/** An island's own sky, land and life, behind its residents. */
function sceneArt(key: string, colors: Record<string, string> | undefined): string {
  switch (key) {
    case 'birds':
      return [
        '<svg class="gw-abs" style="inset: 0" width="390" height="330" viewBox="0 0 390 330">',
        '<rect width="390" height="330" fill="#8ED8FF"/>',
        '<rect y="214" width="390" height="116" fill="#2F9BE3"/>',
        '<path d="M240 216 C252 200 276 198 290 216 Z M320 216 C326 208 338 207 344 216 Z" fill="#5BB0E8"/>',
        '<path d="M-20 330 L-20 236 C40 196 120 176 200 182 C280 188 350 214 410 240 L410 330 Z" fill="#E9B44C"/>',
        '<path d="M-20 330 L-20 250 C50 206 130 188 210 194 C290 200 350 226 410 252 L410 330 Z" fill="#40C057"/>',
        '<path d="M30 238 C80 208 140 196 200 198 C150 206 100 220 60 246 Z" fill="#8CE99A"/>',
        '<path d="M40 286 C40 266 72 262 78 280 C88 266 108 272 104 290 Z M300 300 C304 284 328 282 334 296 C342 288 356 294 352 306 Z" fill="#2F9E44"/>',
        '<circle cx="150" cy="250" r="5" fill="#FF8FA3"/><circle cx="170" cy="262" r="4" fill="#FFD43B"/><circle cx="250" cy="250" r="5" fill="#FFFFFF"/><circle cx="90" cy="252" r="4" fill="#FFD43B"/>',
        '<path d="M318 312 C312 260 316 200 302 136" fill="none" stroke="#7A4A22" stroke-width="13" stroke-linecap="round"/>',
        '<path d="M320 300 C315 256 318 204 306 146" fill="none" stroke="#A86B35" stroke-width="4" stroke-linecap="round"/>',
        '<path d="M302 136 C276 116 236 122 222 146 C250 136 276 134 302 136 Z M302 136 C330 112 370 118 384 144 C356 134 330 134 302 136 Z" fill="#2F9E44"/>',
        '<path d="M302 136 C288 152 270 176 266 196 C282 172 294 156 302 136 Z M302 136 C318 154 334 176 338 196 C324 174 312 156 302 136 Z" fill="#40C057"/>',
        '<path d="M280 134 Q302 150 324 134 Q320 146 302 148 Q284 146 280 134 Z" fill="#B07A3B"/>',
        '</svg>',
        SUN + SCENE_CLOUD,
        '<span class="gw-egg-wob gw-abs" style="left: 293px; top: 118px">',
        '<svg width="18" height="22" viewBox="0 0 18 22"><ellipse cx="9" cy="12" rx="8" ry="10" fill="#F3E3C3" stroke="#13294B" stroke-width="2"/><circle cx="6" cy="9" r="2" fill="#E0C79A"/></svg>',
        '</span>',
      ].join('');
    case 'turtles':
      return [
        '<svg class="gw-abs" style="inset: 0" width="390" height="330" viewBox="0 0 390 330">',
        '<rect width="390" height="330" fill="#9BE3FF"/>',
        '<circle cx="322" cy="56" r="26" fill="#FFE066"/>',
        '<rect y="118" width="390" height="212" fill="#22B8CF"/>',
        '<rect y="118" width="390" height="10" fill="#66D9E8"/>',
        '<path d="M190 330 L190 238 C230 200 310 190 410 200 L410 330 Z" fill="#FFE08A"/>',
        '<path d="M190 250 C232 214 310 204 410 210 L410 218 C310 214 240 226 196 262 Z" fill="#E9B44C"/>',
        '<ellipse cx="60" cy="300" rx="34" ry="14" fill="#5C7080"/><ellipse cx="54" cy="294" rx="24" ry="10" fill="#7D90A0"/>',
        '<path d="M222 304 L232 290 L242 304 Z" fill="#FF8FA3"/><circle cx="276" cy="296" r="6" fill="#FFFFFF"/><circle cx="276" cy="296" r="2.5" fill="#FFC9C9"/>',
        '<path d="M300 236 L284 250 L306 246 Z M370 236 L388 250 L366 246 Z" fill="#51CF66"/>',
        '<path d="M378 220 C392 212 404 216 406 226 C404 236 392 238 380 232 Z" fill="#8CE99A"/>',
        '<path d="M392 222 Q396 218 400 222" stroke="#13294B" stroke-width="2.5" fill="none" stroke-linecap="round"/>',
        '<path d="M286 238 C286 196 312 178 336 178 C362 178 384 196 384 238 Z" fill="#2B8A3E"/>',
        '<path d="M294 232 C294 200 316 186 336 186 C358 186 376 200 376 232 Z" fill="#37B24D"/>',
        '<path d="M324 202 L336 196 L348 202 L348 214 L336 220 L324 214 Z" fill="#51CF66"/>',
        '</svg>',
        '<svg class="gw-caustic gw-abs" style="left: 0; top: 140px" width="390" height="100" viewBox="0 0 390 100"><path d="M10 20 Q30 10 50 20 T90 20 M130 50 Q150 40 170 50 T210 50 M20 80 Q40 70 60 80 T100 80 M250 30 Q270 20 290 30" stroke="#99E9F2" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/></svg>',
        '<span class="gw-zzz gw-abs" style="left: 370px; top: 196px; font-size: 16px; color: #13294B">z</span>',
        '<span class="gw-zzz gw-d2 gw-abs" style="left: 374px; top: 190px; font-size: 12px; color: #13294B">z</span>',
        ...TURTLE_SWIMMERS.map(
          ([x, y, letter, shell, plate, d]) =>
            `<span class="gw-abs" style="left: ${x}px; top: ${y}px"><span class="gw-swim ${d}" style="display: block">` +
            `<svg width="58" height="39" viewBox="0 0 90 60"><g class="gw-paddle" style="transform-origin: 30px 46px"><path d="M30 46 L20 58 L36 52 Z" fill="#69DB7C" stroke="#13294B" stroke-width="2"/></g><ellipse cx="18" cy="34" rx="12" ry="10" fill="#8CE99A" stroke="#13294B" stroke-width="2.5"/><circle cx="14" cy="31" r="2.6" fill="#13294B"/><ellipse cx="48" cy="34" rx="28" ry="20" fill="${shell}" stroke="#13294B" stroke-width="2.5"/><path d="M36 26 L48 20 L60 26 L60 40 L48 46 L36 40 Z" fill="${plate}"/><text x="48" y="40" text-anchor="middle" font-size="17" font-weight="800" fill="#FFFFFF">${letter}</text></svg>` +
            '</span></span>',
        ),
        '<span class="gw-bubble gw-abs" style="left: 96px; top: 200px; width: 8px; height: 8px"></span>',
        '<span class="gw-bubble gw-d2 gw-abs" style="left: 150px; top: 250px; width: 6px; height: 6px"></span>',
        '<span class="gw-bubble gw-d3 gw-abs" style="left: 60px; top: 280px; width: 10px; height: 10px"></span>',
      ].join('');
    case 'monkeys':
      return [
        '<svg class="gw-abs" style="inset: 0" width="390" height="330" viewBox="0 0 390 330">',
        '<rect width="390" height="330" fill="#B8F0A0"/>',
        '<path d="M-10 200 C60 150 140 160 200 180 C260 150 340 150 400 190 L400 330 L-10 330 Z" fill="#69DB7C"/>',
        '<path d="M-10 240 C70 210 150 220 210 236 C280 214 340 214 400 236 L400 330 L-10 330 Z" fill="#40C057"/>',
        '<path d="M-10 290 C80 270 300 270 400 290 L400 330 L-10 330 Z" fill="#2F9E44"/>',
        '<path d="M40 330 C44 250 36 170 52 90" fill="none" stroke="#7A4A22" stroke-width="13" stroke-linecap="round"/>',
        '<path d="M52 90 C30 70 -4 76 -14 98 C14 88 32 88 52 90 Z M52 90 C76 64 114 72 124 96 C98 86 74 86 52 90 Z M52 90 C40 104 26 128 22 146 C36 124 46 108 52 90 Z" fill="#2F9E44"/>',
        '<path d="M340 330 C336 260 346 190 332 120" fill="none" stroke="#7A4A22" stroke-width="12" stroke-linecap="round"/>',
        '<path d="M332 120 C306 100 270 108 260 132 C288 122 310 120 332 120 Z M332 120 C360 98 394 106 404 130 C378 120 354 120 332 120 Z" fill="#2B8A3E"/>',
        '<path d="M120 0 C126 40 116 70 128 110 M250 0 C244 30 256 60 246 96" fill="none" stroke="#2F9E44" stroke-width="5" stroke-linecap="round"/>',
        '<path d="M118 108 C110 116 112 128 122 128 C118 122 118 114 124 110 Z M130 106 C138 114 136 126 128 128 C132 120 130 112 126 108 Z" fill="#FFD43B" stroke="#13294B" stroke-width="2"/>',
        '<path d="M244 94 C236 102 238 114 248 114 C244 108 244 100 250 96 Z" fill="#FFD43B" stroke="#13294B" stroke-width="2"/>',
        '<circle cx="160" cy="300" r="5" fill="#FF8FA3"/><circle cx="220" cy="306" r="4" fill="#FFD43B"/><circle cx="96" cy="296" r="4" fill="#FFFFFF"/>',
        '</svg>',
        SUN,
      ].join('');
    case 'penguins':
      return [
        '<svg class="gw-abs" style="inset: 0" width="390" height="330" viewBox="0 0 390 330">',
        '<rect width="390" height="330" fill="#D6F1FF"/>',
        '<rect y="170" width="390" height="160" fill="#5AA9E6"/>',
        '<path d="M0 330 L0 250 C60 226 140 220 200 230 C270 220 340 224 390 244 L390 330 Z" fill="#E7F5FF"/>',
        '<path d="M0 260 C70 238 150 234 210 242 C150 246 80 252 0 270 Z" fill="#FFFFFF"/>',
        '<path d="M210 200 L250 120 L280 160 L300 96 L340 170 L360 150 L390 200 Z" fill="#A5D8FF"/>',
        '<path d="M216 198 L250 126 L276 164 L300 104 L336 172 L356 156 L384 198 Z" fill="#E7F5FF"/>',
        '<path d="M40 210 L90 210 L84 196 L46 196 Z M120 186 L150 186 L146 178 L124 178 Z" fill="#FFFFFF" stroke="#A5D8FF" stroke-width="2"/>',
        '<circle cx="322" cy="50" r="24" fill="#FFF3BF"/>',
        '</svg>',
        ...SNOW.map(([x, y, d]) => `<span class="gw-snow ${d} gw-abs" style="left: ${x}px; top: ${y}px"></span>`),
      ].join('');
    case 'volcano':
      return [
        '<svg class="gw-abs" style="inset: 0" width="390" height="330" viewBox="0 0 390 330">',
        '<rect width="390" height="330" fill="#FFCF9E"/>',
        '<rect y="210" width="390" height="120" fill="#6B2737"/>',
        '<path d="M40 220 L150 60 L230 60 L340 220 Z" fill="#5C4B5E"/>',
        '<path d="M40 220 L150 60 L176 60 L110 220 Z" fill="#7A6680"/>',
        '<path d="M150 60 L230 60 L222 46 L158 46 Z" fill="#3D3140"/>',
        '<path class="gw-lava" d="M160 48 C176 34 204 34 220 48 L214 58 L166 58 Z" fill="#FF6B3D"/>',
        '<path class="gw-lava" d="M186 60 C176 100 198 130 182 180" stroke="#FF922B" stroke-width="8" fill="none" stroke-linecap="round"/>',
        '<path d="M-10 330 L-10 250 C60 226 140 230 200 244 C270 228 340 230 400 250 L400 330 Z" fill="#3D2C2E"/>',
        '<circle cx="90" cy="290" r="5" fill="#FF922B"/><circle cx="300" cy="296" r="4" fill="#FF6B3D"/>',
        '</svg>',
        '<span class="gw-smoke gw-abs" style="left: 176px; top: 20px; width: 30px; height: 30px; background: #CED4DA"></span>',
        '<span class="gw-smoke gw-d2 gw-abs" style="left: 186px; top: 16px; width: 24px; height: 24px; background: #DEE2E6"></span>',
        '<span class="gw-smoke gw-d3 gw-abs" style="left: 168px; top: 22px; width: 26px; height: 26px; background: #CED4DA"></span>',
      ].join('');
    default:
      return [
        '<svg class="gw-abs" style="inset: 0" width="390" height="330" viewBox="0 0 390 330">',
        `<rect width="390" height="330" fill="${hex(colors?.sky, '#8ED8FF')}"/>`,
        `<rect y="200" width="390" height="130" fill="${hex(colors?.sea, '#2F9BE3')}"/>`,
        `<path d="M-20 330 L-20 250 C50 206 130 188 210 194 C290 200 350 226 410 252 L410 330 Z" fill="${hex(colors?.land, '#40C057')}"/>`,
        '</svg>',
        SUN + SCENE_CLOUD,
      ].join('');
  }
}

/**
 * An opened island's scene (390 x 330): its sky, land and life, with the creatures the learner
 * hatched there living in it (residents: up to three looks; the island's first creature waits
 * there before any hatch).
 */
export function sceneMarkup(key: string, residents: (Look | null | undefined)[], colors?: Record<string, string>): string {
  const spots = (Object.hasOwn(SPOTS, key) ? SPOTS[key] : undefined) ?? PLAIN_SPOTS;
  const living = residents.slice(0, 3).map((look, i) => {
    const spot = spots[i];
    if (!spot) return '';
    const [x, y, size, move] = spot;
    return `<span class="${move} gw-abs" style="left: ${x}px; top: ${y}px; transform-origin: 50% 100%">${creatureSvg(look, { size, motion: true })}</span>`;
  });
  return `<div class="gw-scene" aria-hidden="true">${sceneArt(key, colors)}${living.join('')}</div>`;
}
