import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { watchAutoplay } from './player';

/* The page's videos start by themselves (6.4): muted first, near the screen, and never twice. */

type Listener = () => void;

class FakeVideo {
  muted = false;
  defaultMuted = false;
  preload = 'none';
  readyState = 0;
  paused = true;
  attrs = new Map<string, string>();
  listeners = new Map<string, Set<Listener>>();
  played: { muted: boolean }[] = [];
  pauses = 0;
  loads = 0;
  refuse: Error | null = null;
  setAttribute(name: string, value: string) { this.attrs.set(name, value); }
  addEventListener(type: string, fn: Listener) { (this.listeners.get(type) ?? this.listeners.set(type, new Set()).get(type)!).add(fn); }
  removeEventListener(type: string, fn: Listener) { this.listeners.get(type)?.delete(fn); }
  fire(type: string) { for (const fn of [...(this.listeners.get(type) ?? [])]) fn(); }
  load() { this.loads++; }
  /** As a browser does: the pause event comes a moment later. */
  pause() {
    this.pauses++;
    if (this.paused) return;
    this.paused = true;
    queueMicrotask(() => this.fire('pause'));
  }
  play() {
    this.played.push({ muted: this.muted });
    if (this.refuse) return Promise.reject(this.refuse);
    this.paused = false;
    return Promise.resolve();
  }
  /** The browser has enough of the file. */
  ready() { this.readyState = 4; this.fire('canplay'); }
  /** The visitor turns the sound on with the controls. */
  unmute() { this.muted = false; this.fire('volumechange'); }
}

let observed: ((entries: { isIntersecting: boolean }[]) => void) | null = null;
const env = { reduced: false, saveData: false };

beforeEach(() => {
  observed = null;
  env.reduced = false;
  env.saveData = false;
  vi.stubGlobal('window', { matchMedia: () => ({ matches: env.reduced }) });
  vi.stubGlobal('navigator', { connection: { get saveData() { return env.saveData; } } });
  vi.stubGlobal('IntersectionObserver', class {
    constructor(cb: (entries: { isIntersecting: boolean }[]) => void) { observed = cb; }
    observe() {}
    disconnect() {}
  });
});
afterEach(() => vi.unstubAllGlobals());

const flush = () => new Promise((r) => setTimeout(r, 0));
const start = (video: FakeVideo, anyMotion = false, onPlaying = vi.fn()) => ({
  stop: watchAutoplay(video as unknown as HTMLVideoElement, anyMotion, onPlaying),
  onPlaying,
});

describe('watchAutoplay', () => {
  it('loads nothing before the video nears the screen, then mutes it before play()', async () => {
    const video = new FakeVideo();
    const { onPlaying } = start(video);
    expect(video.loads).toBe(0);
    observed!([{ isIntersecting: true }]);
    expect(video.loads).toBe(1);
    expect(video.preload).toBe('auto');
    expect(video.played).toHaveLength(0);
    video.ready();
    await flush();
    expect(video.played).toEqual([{ muted: true }]);
    expect(video.defaultMuted).toBe(true);
    expect(video.attrs.get('muted')).toBe('');
    expect(onPlaying).toHaveBeenLastCalledWith(true);
  });

  it('plays a video that already has its data at once, and pauses it when it leaves', async () => {
    const video = new FakeVideo();
    video.readyState = 4;
    const { onPlaying } = start(video);
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(video.played).toHaveLength(1);
    observed!([{ isIntersecting: false }]);
    expect(video.pauses).toBe(1);
    expect(onPlaying).toHaveBeenLastCalledWith(false);
  });

  it('plays the academy’s own autoplay under reduced motion, but not a background or ambient one', async () => {
    env.reduced = true;
    expect(start(new FakeVideo()).stop).toBeNull();
    const video = new FakeVideo();
    video.readyState = 4;
    expect(start(video, true).stop).not.toBeNull();
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(video.played).toEqual([{ muted: true }]);
  });

  it('starts nothing for a visitor saving data', () => {
    env.saveData = true;
    expect(start(new FakeVideo(), true).stop).toBeNull();
  });

  it('leaves the video paused when the browser refuses to play', async () => {
    const video = new FakeVideo();
    video.readyState = 4;
    video.refuse = Object.assign(new Error('no'), { name: 'NotAllowedError' });
    const { onPlaying } = start(video);
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(onPlaying).toHaveBeenCalledWith(false);
    expect(onPlaying).not.toHaveBeenCalledWith(true);
  });

  it('says nothing once the page has left', async () => {
    const video = new FakeVideo();
    const { stop, onPlaying } = start(video);
    observed!([{ isIntersecting: true }]);
    stop!();
    video.readyState = 4;
    video.fire('canplay');
    await flush();
    expect(video.played).toHaveLength(0);
    expect(onPlaying).not.toHaveBeenCalled();
  });

  it('plays again when the video comes back after the page paused it', async () => {
    const video = new FakeVideo();
    video.readyState = 4;
    start(video);
    observed!([{ isIntersecting: true }]);
    await flush();
    observed!([{ isIntersecting: false }]);
    await flush();
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(video.played).toHaveLength(2);
  });

  it('leaves a video the visitor paused alone, also when it comes back into view', async () => {
    const video = new FakeVideo();
    video.readyState = 4;
    start(video, true);
    observed!([{ isIntersecting: true }]);
    await flush();
    video.paused = true;
    video.fire('pause');
    observed!([{ isIntersecting: false }]);
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(video.played).toHaveLength(1);
    expect(video.pauses).toBe(0);
  });

  it('never mutes again a video the visitor turned the sound on for', async () => {
    const video = new FakeVideo();
    video.readyState = 4;
    start(video, true);
    observed!([{ isIntersecting: true }]);
    await flush();
    video.unmute();
    observed!([{ isIntersecting: false }]);
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(video.muted).toBe(false);
    expect(video.pauses).toBe(0);
    expect(video.played).toHaveLength(1);
  });

  it('counts a play() that throws at once as refused', async () => {
    const video = new FakeVideo();
    video.readyState = 4;
    video.play = () => { throw new Error('old browser'); };
    const { onPlaying } = start(video);
    observed!([{ isIntersecting: true }]);
    await flush();
    expect(onPlaying).toHaveBeenCalledWith(false);
  });
});
