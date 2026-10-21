import { describe, expect, it } from 'vitest';
import { clockOf, recordingFile, recordingType } from './recorder';

describe('recordings made in the page', () => {
  it('records in the first format the browser makes that the API takes', () => {
    expect(recordingType((t) => t === 'audio/mp4' || t === 'audio/webm')).toBe('audio/webm');
    expect(recordingType((t) => t === 'audio/mp4')).toBe('audio/mp4');
    expect(recordingType(() => false)).toBeUndefined();
  });

  it('records nothing where the browser has no recorder', () => {
    expect(recordingType()).toBeUndefined();
  });

  it('names the file by what was made', () => {
    expect(recordingFile('audio/webm;codecs=opus')).toEqual({ name: 'recording.webm', mime: 'audio/webm' });
    expect(recordingFile('audio/mp4', 'note')).toEqual({ name: 'note.m4a', mime: 'audio/mp4' });
    expect(recordingFile('audio/ogg;codecs=opus')).toEqual({ name: 'recording.ogg', mime: 'audio/ogg' });
  });

  it('shows the time as m:ss', () => {
    expect(clockOf(0)).toBe('0:00');
    expect(clockOf(75)).toBe('1:15');
  });
});
