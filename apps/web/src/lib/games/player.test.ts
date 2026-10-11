import { describe, expect, it } from 'vitest';
import { frameSrc, isVersion, muteMessage, readMessage, startMessage } from './player';

const VERSION = '01a12ac6-7d4c-7373-8ac0-b0ed4518a9d4';

describe('the frame', () => {
  it('opens a published version on the portal\'s own origin', () => {
    expect(frameSrc(`https://demo.example.com/games/${VERSION}`)).toBe(`/bff/games/${VERSION}`);
    expect(frameSrc(`/games/${VERSION}`)).toBe(`/bff/games/${VERSION}`);
  });

  it('opens nothing else', () => {
    expect(frameSrc('https://evil.example.com/steal')).toBeNull();
    expect(frameSrc(`https://demo.example.com/games/${VERSION}/../../ar/family`)).toBeNull();
    expect(frameSrc('/games/not-a-version')).toBeNull();
    expect(frameSrc('javascript:alert(1)')).toBeNull();
    expect(isVersion(VERSION)).toBe(true);
    expect(isVersion(`${VERSION}x`)).toBe(false);
  });
});

describe('what the game may say', () => {
  const frame = {};
  const items = new Set(['a', 'b']);
  const from = (data: unknown, source: unknown = frame) => readMessage({ source, data }, frame, items);

  it('is heard only from its own frame, as the bridge writes it', () => {
    expect(from(JSON.stringify({ lms: 1, type: 'ready' }))).toEqual({ type: 'ready' });
    expect(from(JSON.stringify({ lms: 1, type: 'ready' }), {})).toBeNull();
    expect(from(JSON.stringify({ lms: 1, type: 'ready' }), null)).toBeNull();
    expect(readMessage({ source: frame, data: JSON.stringify({ lms: 1, type: 'ready' }) }, null, items)).toBeNull();
    expect(from({ lms: 1, type: 'ready' })).toBeNull();
    expect(from('not json')).toBeNull();
    expect(from(JSON.stringify({ lms: 2, type: 'ready' }))).toBeNull();
    expect(from(JSON.stringify({ lms: 1, type: 'open', url: 'x' }))).toBeNull();
  });

  it('answers only for the round\'s items, right only when it says true', () => {
    expect(from(JSON.stringify({ lms: 1, type: 'answer', item: 'a', correct: true }))).toEqual({ type: 'answer', item: 'a', correct: true });
    expect(from(JSON.stringify({ lms: 1, type: 'answer', item: 'b', correct: 'yes' }))).toEqual({ type: 'answer', item: 'b', correct: false });
    expect(from(JSON.stringify({ lms: 1, type: 'answer', item: 'z', correct: true }))).toBeNull();
  });

  it('finishes, and reports errors short', () => {
    expect(from(JSON.stringify({ lms: 1, type: 'finish' }))).toEqual({ type: 'finish' });
    const error = from(JSON.stringify({ lms: 1, type: 'error', message: 'x'.repeat(500), line: '12' }));
    expect(error).toEqual({ type: 'error', message: 'x'.repeat(300), line: 12 });
  });
});

describe('what the page hands the game', () => {
  it('is the round from the API with the sound choice', () => {
    expect(JSON.parse(startMessage({ items: [{ id: 'a' }], stage: 'kids' }, true))).toEqual({ lms: 1, type: 'start', round: { items: [{ id: 'a' }], stage: 'kids', muted: true } });
    expect(JSON.parse(muteMessage(false))).toEqual({ lms: 1, type: 'mute', muted: false });
  });
});
