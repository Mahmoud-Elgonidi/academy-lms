/**
 * The games world's sound (the API's shared::games.sound, window.LmsIslandsSound in the panels):
 * a calm sea with notes on each island's own scale (its catalog `music`), and short effects
 * (zoom, back, locked, chirp, bubble, knock, hatch, cheep, pop, star, voice). Made in the browser
 * with Web Audio, nothing downloaded; off until the learner's first tap (the browser's rule). The
 * choice to mute is kept on this device only (lms-games-muted). Nothing touches the browser until
 * the first call; a page calls stop(true) when it unmounts.
 */

const KEY = 'lms-games-muted';

/** Raised on document when the choice to mute changes ({ muted }). */
export const MUTED_EVENT = 'lms-games:muted';

const SCALES = {
  map: [392, 440, 523.25, 587.33, 659.25, 783.99, 880],
  major: [523.25, 587.33, 659.25, 783.99, 880, 1046.5, 1174.66],
  pentatonic: [261.63, 293.66, 329.63, 392, 440, 523.25, 587.33],
  dorian: [293.66, 329.63, 349.23, 392, 440, 493.88, 523.25],
  lydian: [349.23, 392, 440, 493.88, 523.25, 587.33, 659.25],
  phrygian: [164.81, 174.61, 196, 220, 246.94, 261.63, 293.66],
} as const satisfies Record<string, readonly number[]>;

type Mode = keyof typeof SCALES;
type Music = { master: GainNode; sea: AudioBufferSourceNode; lfo: OscillatorNode; step: number; idx: number };

let ac: AudioContext | null = null;
let music: Music | null = null;
let timer: number | null = null;
let mode: Mode = 'map';
let muted = false;
let ready = false;

const own = <T>(table: Record<string, T>, key: string): T | undefined => (Object.hasOwn(table, key) ? table[key] : undefined);

/** In the browser: reads the stored choice and stops the music when the page goes out of sight, once. */
function init(): boolean {
  if (typeof window === 'undefined') return false;
  if (!ready) {
    ready = true;
    try {
      muted = window.localStorage.getItem(KEY) === '1';
    } catch {
      // A private window or blocked storage: sound stays on.
    }
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stop(true);
    });
  }
  return true;
}

function ctx(): AudioContext | null {
  try {
    const AC = window.AudioContext || (window as Window & { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!AC) return null;
    ac = ac || new AC();
    if (ac.state === 'suspended') void ac.resume().catch(() => undefined);
    return ac;
  } catch {
    return null;
  }
}

function tone(a: AudioContext, f1: number, f2: number, at: number, dur: number, type: OscillatorType, vol: number, dest?: AudioNode): void {
  const t = a.currentTime + 0.02 + at;
  const o = a.createOscillator();
  const g = a.createGain();
  o.type = type;
  o.frequency.setValueAtTime(f1, t);
  if (f2) o.frequency.exponentialRampToValueAtTime(f2, t + dur);
  g.gain.setValueAtTime(0.0001, t);
  g.gain.exponentialRampToValueAtTime(vol, t + 0.015);
  g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
  o.connect(g);
  g.connect(dest || a.destination);
  o.start(t);
  o.stop(t + dur + 0.05);
}

function noise(a: AudioContext, at: number, dur: number, f1: number, f2: number, vol: number, dest?: AudioNode): void {
  const t = a.currentTime + 0.02 + at;
  const len = Math.floor(a.sampleRate * dur);
  const buf = a.createBuffer(1, len, a.sampleRate);
  const d = buf.getChannelData(0);
  for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
  const s = a.createBufferSource();
  const bp = a.createBiquadFilter();
  const g = a.createGain();
  s.buffer = buf;
  bp.type = 'bandpass';
  bp.Q.value = 1.2;
  bp.frequency.setValueAtTime(f1, t);
  bp.frequency.exponentialRampToValueAtTime(f2, t + dur);
  g.gain.setValueAtTime(0.0001, t);
  g.gain.exponentialRampToValueAtTime(vol, t + dur * 0.4);
  g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
  s.connect(bp);
  bp.connect(g);
  g.connect(dest || a.destination);
  s.start(t);
  s.stop(t + dur);
}

const EFFECTS = {
  zoom: (a) => {
    noise(a, 0, 0.7, 300, 3000, 0.25);
    [523, 659, 784, 1047, 1319].forEach((f, i) => tone(a, f, 0, 0.2 + i * 0.07, 0.4, 'triangle', 0.12));
  },
  back: (a) => {
    noise(a, 0, 0.5, 2500, 300, 0.2);
    [1047, 784, 659].forEach((f, i) => tone(a, f, 0, i * 0.07, 0.3, 'triangle', 0.09));
  },
  locked: (a) => {
    tone(a, 330, 0, 0, 0.12, 'triangle', 0.16);
    tone(a, 262, 0, 0.12, 0.25, 'triangle', 0.14);
    noise(a, 0.05, 0.2, 4000, 6000, 0.05);
  },
  chirp: (a) => {
    tone(a, 1800, 2700, 0, 0.09, 'sine', 0.14);
    tone(a, 2100, 3100, 0.12, 0.08, 'sine', 0.12);
  },
  bubble: (a) => [0, 0.1, 0.22].forEach((d, i) => tone(a, 300 + i * 120, 900 + i * 200, d, 0.12, 'sine', 0.14)),
  jungle: (a) => {
    tone(a, 700, 1400, 0, 0.12, 'square', 0.05);
    tone(a, 900, 500, 0.14, 0.14, 'triangle', 0.1);
  },
  ice: (a) => [1568, 2093, 2637].forEach((f, i) => tone(a, f, 0, i * 0.06, 0.5, 'sine', 0.07)),
  rumble: (a) => {
    noise(a, 0, 0.9, 120, 60, 0.35);
    tone(a, 80, 50, 0, 0.8, 'sawtooth', 0.04);
  },
  knock: (a) => {
    tone(a, 320, 180, 0, 0.08, 'triangle', 0.3);
    noise(a, 0.02, 0.1, 2500, 1200, 0.25);
  },
  hatch: (a) => {
    noise(a, 0, 0.25, 3000, 600, 0.5);
    [523, 659, 784, 1047, 1319, 1568].forEach((f, i) => tone(a, f, 0, 0.15 + i * 0.08, 0.35, 'triangle', 0.16));
  },
  cheep: (a) => {
    tone(a, 2200, 3000, 0, 0.07, 'sine', 0.16);
    tone(a, 2400, 3200, 0.1, 0.07, 'sine', 0.14);
    tone(a, 2600, 2000, 0.2, 0.09, 'sine', 0.12);
  },
  pop: (a) => tone(a, 520, 980, 0, 0.09, 'sine', 0.22),
  star: (a) => {
    tone(a, 880, 1760, 0, 0.12, 'triangle', 0.16);
    tone(a, 1320, 0, 0.08, 0.25, 'sine', 0.1);
  },
  win: (a) => [523, 659, 784, 1047].forEach((f, i) => tone(a, f, 0, i * 0.11, 0.4, 'triangle', 0.14)),
} satisfies Record<string, (a: AudioContext) => void>;

/** A short effect's name (anything else plays pop). */
export type Effect = keyof typeof EFFECTS;

/** Each island's own little cue when it opens. */
const CUES: Record<string, Effect> = { major: 'chirp', pentatonic: 'bubble', dorian: 'jungle', lydian: 'ice', phrygian: 'rumble' };

function play(name: Effect | string): void {
  if (!init() || muted) return;
  const a = ctx();
  if (!a) return;
  try {
    (own(EFFECTS, name) ?? EFFECTS.pop)(a);
  } catch {
    // The browser refused; the game goes on without it.
  }
}

/** A creature's voice: a chirp at its own pitch (the catalog's look.pitch). */
function voice(pitch: unknown): void {
  if (!init() || muted) return;
  const a = ctx();
  if (!a) return;
  const p = Math.max(0.3, Math.min(2, Number(pitch) || 1));
  try {
    tone(a, 1200 * p, 1800 * p, 0, 0.1, 'sine', 0.16);
    tone(a, 1400 * p, 2000 * p, 0.13, 0.09, 'sine', 0.14);
    tone(a, 1600 * p, 1100 * p, 0.26, 0.14, 'triangle', 0.1);
  } catch {
    // As above.
  }
}

const STEPS = [-2, -1, 1, 2, 0] as const;

function beat(): void {
  const m = music;
  const a = ac;
  if (!m || !a) return;
  try {
    const sc = SCALES[mode];
    m.step += 1;
    const bar = m.step % 16;
    if (bar === 0 || bar === 8) tone(a, sc[0] / 2, 0, 0, 0.9, 'sine', 0.05, m.master);
    if (bar === 4 || bar === 12) tone(a, sc[2] / 2, 0, 0, 0.7, 'sine', 0.04, m.master);
    if (bar % 4 === 3 && Math.random() < 0.5) return;
    m.idx = Math.max(0, Math.min(sc.length - 1, m.idx + (STEPS[Math.floor(Math.random() * 5)] ?? 0)));
    const f = sc[m.idx] ?? sc[0];
    if (mode === 'major') {
      tone(a, f, f * 1.01, 0, 0.35, 'sine', 0.035, m.master);
      if (Math.random() < 0.05) {
        tone(a, 2200, 3000, 0.1, 0.07, 'sine', 0.04, m.master);
        tone(a, 2400, 3200, 0.2, 0.07, 'sine', 0.035, m.master);
      }
    } else if (mode === 'pentatonic') {
      tone(a, f, 0, 0, 0.22, 'triangle', 0.05, m.master);
      if (Math.random() < 0.06) tone(a, 400, 1200, 0.05, 0.12, 'sine', 0.04, m.master);
    } else if (mode === 'dorian') {
      tone(a, f, 0, 0, 0.16, 'triangle', 0.055, m.master);
      if (bar % 2 === 0) tone(a, f * 2, 0, 0.08, 0.06, 'square', 0.012, m.master);
    } else if (mode === 'lydian') {
      tone(a, f * 2, 0, 0, 0.6, 'sine', 0.03, m.master);
    } else if (mode === 'phrygian') {
      tone(a, f, 0, 0, 0.5, 'triangle', 0.05, m.master);
    } else {
      tone(a, f, 0, 0, 0.45, 'triangle', 0.035, m.master);
      if (Math.random() < 0.03) tone(a, 1300, 900, 0, 0.25, 'sine', 0.03, m.master);
    }
  } catch {
    // As above.
  }
}

/** The sea and the island's notes, until stop() (nothing while muted). */
function start(): void {
  if (music || !init() || muted) return;
  const a = ctx();
  if (!a) return;
  try {
    const master = a.createGain();
    master.gain.setValueAtTime(0.0001, a.currentTime);
    master.gain.exponentialRampToValueAtTime(1, a.currentTime + 1.6);
    master.connect(a.destination);
    const len = a.sampleRate * 2;
    const buf = a.createBuffer(1, len, a.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    const sea = a.createBufferSource();
    sea.buffer = buf;
    sea.loop = true;
    const lp = a.createBiquadFilter();
    lp.type = 'lowpass';
    lp.frequency.value = 420;
    const sg = a.createGain();
    sg.gain.value = 0.035;
    const lfo = a.createOscillator();
    lfo.frequency.value = 0.13;
    const lg = a.createGain();
    lg.gain.value = 0.03;
    lfo.connect(lg);
    lg.connect(sg.gain);
    sea.connect(lp);
    lp.connect(sg);
    sg.connect(master);
    sea.start();
    lfo.start();
    music = { master, sea, lfo, step: 0, idx: 3 };
    timer = window.setInterval(beat, 280);
  } catch {
    // As above.
  }
}

/** Fades the music out (now: at once, as when the page goes). */
function stop(now = false): void {
  if (timer !== null) window.clearInterval(timer);
  timer = null;
  const m = music;
  music = null;
  if (!m || !ac) return;
  try {
    const t = ac.currentTime;
    m.master.gain.cancelScheduledValues(t);
    m.master.gain.setValueAtTime(Math.max(m.master.gain.value, 0.0001), t);
    m.master.gain.exponentialRampToValueAtTime(0.0001, t + (now ? 0.05 : 0.6));
    m.sea.stop(t + (now ? 0.1 : 0.7));
    m.lfo.stop(t + (now ? 0.1 : 0.7));
  } catch {
    // As above.
  }
}

function setMuted(on: boolean): void {
  if (!init()) return;
  muted = !!on;
  try {
    window.localStorage.setItem(KEY, muted ? '1' : '0');
  } catch {
    // Kept for this page only.
  }
  if (muted) stop(false);
  else start();
  document.dispatchEvent(new CustomEvent(MUTED_EVENT, { detail: { muted } }));
}

/** The games world's sound, one for the page: the same calls as the panels' window.LmsIslandsSound. */
export const sound = {
  play,
  voice,
  start,
  stop,
  /** An island's cue when it opens, by its catalog music. */
  cue: (scale: string) => play(own(CUES, scale) ?? 'chirp'),
  /** The scale the music plays on (the island's catalog music; map elsewhere). */
  scale: (m: string) => {
    mode = Object.hasOwn(SCALES, m) ? (m as Mode) : 'map';
  },
  muted: (): boolean => {
    init();
    return muted;
  },
  setMuted,
  playing: (): boolean => music !== null,
};

export type IslandsSound = typeof sound;
