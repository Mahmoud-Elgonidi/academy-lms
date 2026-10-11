import { describe, expect, it } from 'vitest';
import { creatureSvg, esc, hex, islandBox, islandMarkup, num, sceneMarkup } from './art';

const count = (markup: string, part: string) => markup.split(part).length - 1;

describe('a creature', () => {
  it('takes its colours only as #rrggbb, else the default', () => {
    expect(hex('#12aBcD', '#000000')).toBe('#12aBcD');
    expect(hex('red', '#000000')).toBe('#000000');
    expect(hex('#12345', '#000000')).toBe('#000000');
    expect(hex(12, '#000000')).toBe('#000000');

    const bird = creatureSvg({ kind: 'bird', body: '#112233', wing: '"><script>', belly: 'red' });
    expect(bird).toContain('<ellipse cx="60" cy="56" rx="34" ry="31" fill="#112233"');
    expect(bird).toContain('fill="#4DABF7"');
    expect(bird).toContain('fill="#E7F5FF"');
    expect(bird).not.toContain('<script>');
    expect(creatureSvg(null, { line: 'url(javascript:x)' })).toContain('stroke="#13294B"');
  });

  it('is 84 wide by default, with the height in proportion as PHP rounds it', () => {
    expect(creatureSvg(null)).toContain('<svg class="cr " width="84" height="74" viewBox="0 0 120 105" style="overflow: visible" aria-hidden="true">');
    expect(creatureSvg(null, { size: 96, className: 'big "one"' })).toContain('<svg class="cr big &quot;one&quot;" width="96" height="84"');
  });

  it('as a shadow draws the outline only: no eyes, no line, all #D7CCF5', () => {
    const shadow = creatureSvg({ kind: 'bird', body: '#112233', crest: '#00FF00' }, { shadow: true, motion: true });
    expect(shadow).toContain('fill="#D7CCF5" stroke="none"');
    expect(shadow).not.toContain('#13294B');
    expect(shadow).not.toContain('#112233');
    expect(shadow).not.toContain('#00FF00');
    expect(shadow).not.toContain('cr-blink');
    expect(shadow).not.toContain('<circle');
  });

  it('flaps its wings and blinks when it moves', () => {
    const still = creatureSvg({ kind: 'bird' });
    expect(still).toContain('<g class=""><path d="M32 48');
    expect(still).not.toMatch(/cr-w[lr]|cr-blink/);

    const moving = creatureSvg({ kind: 'bird' }, { motion: true });
    expect(moving).toContain('<g class="cr-wl"><path d="M32 48');
    expect(moving).toContain('<g class="cr-wr"><path d="M88 48');
    expect(moving).toContain('<g class="cr-blink"><circle cx="48" cy="50" r="7" fill="#13294B"/>');
  });

  it('wears a crest only when its look has one', () => {
    expect(creatureSvg({ kind: 'bird' })).not.toContain('M48 22 L44 2');
    expect(creatureSvg({ kind: 'bird', crest: '#00FF00' })).toContain('<path d="M48 22 L44 2 L54 16 L60 0 L66 16 L76 2 L72 22" fill="#00FF00"');
  });

  it.each([
    ['turtle', '<path d="M16 82 C16 52 36 40 60 40 C84 40 104 52 104 82 Z"'],
    ['crab', '<g transform="translate(14 22) scale(1.32)">'],
    ['octopus', '<ellipse cx="60" cy="44" rx="34" ry="32"'],
    ['whale', '<path d="M8 62 C8 36 34 24 64 26'],
    ['monkey', '<path d="M84 70 C104 72 106 52 94 48"'],
    ['sloth', 'transform="rotate(-20 46 49)"'],
    ['elephant', '<path d="M52 64 C50 82 52 94 64 98'],
    ['tiger', '<path d="M60 22 V34 M50 24 L54 34'],
    ['penguin', '<ellipse cx="60" cy="58" rx="34" ry="40"'],
    ['seal', '<path d="M14 80 C14 58 34 46 56 48'],
    ['bear', '<circle cx="60" cy="58" r="38"'],
    ['fox', '<path d="M24 44 L30 8 L52 32 Z M96 44 L90 8 L68 32 Z"'],
    ['lizard', 'stroke-width="13" stroke-linecap="round"'],
    ['chick', 'fill="#F3E3C3"'],
    ['dino', '<path d="M34 64 L40 70 L46 64 L52 70'],
    ['dragon', '<path d="M86 46 C104 20 120 26 116 44'],
    ['someday-new', '<path d="M88 48 C106 30 122 36 118 50'],
  ])('draws a %s', (kind, part) => {
    expect(creatureSvg({ kind })).toContain(part);
  });

  it('moves the crab\'s claw, the whale\'s spout and the dragon\'s flame', () => {
    expect(creatureSvg({ kind: 'crab' }, { motion: true })).toContain('<g class="cr-claw">');
    expect(creatureSvg({ kind: 'whale' }, { motion: true })).toContain('<path class="cr-spout"');
    expect(creatureSvg({ kind: 'dragon' }, { motion: true })).toContain('<path class="cr-flame"');
  });

  it('writes numbers as PHP writes them', () => {
    expect(num(51)).toBe('51');
    expect(num(4.6 / 3)).toBe('1.5333333333333');
    expect(num(6.5 / 3)).toBe('2.1666666666667');
    expect(num(60 + 6 * 0.8)).toBe('64.8');
    expect(creatureSvg({ kind: 'turtle' })).toContain('<circle cx="51" cy="25.5" r="1.5333333333333" fill="#FFFFFF"/>');
  });
});

describe('an island', () => {
  it('has its own box, the plain one for an island added later', () => {
    expect(islandBox('volcano')).toEqual({ width: 170, height: 150 });
    expect(islandBox('birds')).toEqual({ width: 200, height: 190 });
    expect(islandBox('meadow')).toEqual({ width: 180, height: 140 });
    expect(islandBox('constructor')).toEqual({ width: 180, height: 140 });
  });

  it('greys under a cloud when locked', () => {
    expect(islandMarkup('volcano')).not.toContain('locked-art');
    const locked = islandMarkup('volcano', { locked: true });
    expect(locked).toContain('<svg class="locked-art" width="170" height="150"');
    expect(locked).toContain('<svg aria-hidden="true" class="gw-abs" style="left: 0; top: 78px; opacity: .92" width="170" height="60" viewBox="0 0 170 60">');
  });

  it('draws one added later in its own colours, with its emoji escaped', () => {
    const island = islandMarkup('meadow', { colors: { land: '#123456', accent: 'yellow' }, emoji: '<b>&"\'' });
    expect(island).toContain('fill="#123456"');
    expect(island).toContain('<circle cx="-22" cy="-14" r="4" fill="#FFD43B"/>');
    expect(island).toContain('&lt;b&gt;&amp;&quot;&#039;</span>');
    expect(island).not.toContain('<b>');
    expect(esc(null)).toBe('');
  });
});

describe('a scene', () => {
  it('puts at most three residents, each at its spot and moving', () => {
    const scene = sceneMarkup('birds', [{ kind: 'bird' }, null, { kind: 'turtle' }, { kind: 'crab' }, { kind: 'whale' }]);
    expect(scene.startsWith('<div class="gw-scene" aria-hidden="true">')).toBe(true);
    expect(count(scene, '<svg class="cr ')).toBe(3);
    expect(scene).toContain('<span class="gw-fig8 gw-abs" style="left: 160px; top: 70px; transform-origin: 50% 100%"><svg class="cr " width="96" height="84"');
    expect(scene).toContain('<span class="gw-peek gw-abs" style="left: 318px; top: 160px; transform-origin: 50% 100%"><svg class="cr " width="50" height="44"');
    expect(scene).toContain('cr-blink');
    expect(scene).not.toContain('translate(14 22)');
  });

  it('is empty of residents before any, and plain in its own colours for an island added later', () => {
    expect(sceneMarkup('turtles', [])).not.toContain('<svg class="cr ');
    const plain = sceneMarkup('meadow', [{ kind: 'penguin' }], { sky: '#010203', land: 'green' });
    expect(plain).toContain('<rect width="390" height="330" fill="#010203"/>');
    expect(plain).toContain('fill="#40C057"');
    expect(plain).toContain('<span class="gw-perch gw-abs" style="left: 150px; top: 170px; transform-origin: 50% 100%">');
  });
});
