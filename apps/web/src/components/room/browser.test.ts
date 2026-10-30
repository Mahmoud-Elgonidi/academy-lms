import { describe, expect, it } from 'vitest';
import { canShareScreen, documentPip, isApple, pipWay, shortcutLabel, shortcutOf } from './browser';

describe('picture-in-picture', () => {
  it('prefers the floating window of its own (Chrome, Edge)', () => {
    const win = { documentPictureInPicture: { requestWindow: () => Promise.resolve({}), window: null }, document: { pictureInPictureEnabled: true } };
    expect(pipWay(win)).toBe('document');
    expect(documentPip(win)).not.toBeNull();
  });

  it('falls back to the video’s own (Safari, Firefox)', () => {
    const win = { document: { pictureInPictureEnabled: true }, HTMLVideoElement: { prototype: { requestPictureInPicture() {} } } };
    expect(pipWay(win)).toBe('video');
    expect(documentPip(win)).toBeNull();
  });

  it('offers none where the browser has none, and never throws', () => {
    expect(pipWay(undefined)).toBe('none');
    expect(pipWay({})).toBe('none');
    expect(pipWay({ document: { pictureInPictureEnabled: false }, HTMLVideoElement: { prototype: { requestPictureInPicture() {} } } })).toBe('none');
    expect(pipWay({ documentPictureInPicture: { requestWindow: 'no' } })).toBe('none');
    const hostile = {
      get documentPictureInPicture(): never {
        throw new Error('blocked');
      },
      get document(): never {
        throw new Error('blocked');
      },
    };
    expect(pipWay(hostile)).toBe('none');
  });
});

describe('sharing the screen', () => {
  it('is offered only where getDisplayMedia is', () => {
    expect(canShareScreen({ mediaDevices: { getDisplayMedia() {} } })).toBe(true);
    expect(canShareScreen({ mediaDevices: {} })).toBe(false);
    expect(canShareScreen({})).toBe(false);
    expect(canShareScreen(undefined)).toBe(false);
  });
});

describe('the room’s shortcuts', () => {
  const key = (code: string, mods: Partial<{ ctrlKey: boolean; metaKey: boolean; altKey: boolean; shiftKey: boolean }> = {}) => ({
    code,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    shiftKey: false,
    ...mods,
  });

  it('are Ctrl+D and Ctrl+E, ⌘ on Apple’s keyboards, by the key’s place', () => {
    expect(shortcutOf(key('KeyD', { ctrlKey: true }), false)).toBe('mic');
    expect(shortcutOf(key('KeyE', { ctrlKey: true }), false)).toBe('camera');
    expect(shortcutOf(key('KeyD', { metaKey: true }), true)).toBe('mic');
    expect(shortcutOf(key('KeyD', { metaKey: true }), false)).toBeNull();
    expect(shortcutOf(key('KeyD', { ctrlKey: true }), true)).toBeNull();
    expect(shortcutOf(key('KeyD', { ctrlKey: true, shiftKey: true }), false)).toBeNull();
    expect(shortcutOf(key('KeyX', { ctrlKey: true }), false)).toBeNull();
    expect(shortcutOf(key('KeyD'), false)).toBeNull();
  });

  it('are written for the reader’s keyboard', () => {
    expect(isApple('MacIntel')).toBe(true);
    expect(isApple('Win32')).toBe(false);
    expect(shortcutLabel('D', false)).toBe('Ctrl+D');
    expect(shortcutLabel('E', true)).toBe('⌘E');
  });
});
