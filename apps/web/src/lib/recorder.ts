import { useEffect, useRef, useState } from 'react';

/** The recording formats the API takes (SubmitHomework::AUDIO), the browser's favourite first. */
export const RECORDING_TYPES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'] as const;

/** The first recording format this browser makes that the API takes. */
export function recordingType(supports?: (type: string) => boolean): string | undefined {
  const can = supports ?? (typeof MediaRecorder === 'undefined' ? undefined : (t: string) => MediaRecorder.isTypeSupported(t));
  return can ? RECORDING_TYPES.find((t) => can(t)) : undefined;
}

/** A recording's file name and type from what the recorder made ("audio/webm;codecs=opus" → recording.webm, audio/webm). */
export function recordingFile(type: string, base = 'recording'): { name: string; mime: string } {
  const mime = type.split(';')[0]!.trim();
  const ext = mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm';
  return { name: `${base}.${ext}`, mime };
}

/** m:ss for a count of seconds. */
export function clockOf(seconds: number): string {
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export type StartResult = 'recording' | 'unsupported' | 'denied';

/**
 * A voice recording made in the page: start asks for the microphone, the
 * clock counts while it runs, it stops by itself at `limit` seconds, and the
 * file comes to `onDone` (named from `base`, in a type the API takes).
 */
export function useRecorder({ limit, base, onDone, onLimit }: { limit: number; base?: string; onDone: (file: File) => void; onLimit?: () => void }) {
  const [live, setLive] = useState(false);
  const [seconds, setSeconds] = useState(0);
  const recorder = useRef<MediaRecorder | null>(null);
  const chunks = useRef<Blob[]>([]);
  const done = useRef(onDone);
  const limited = useRef(onLimit);
  useEffect(() => {
    done.current = onDone;
    limited.current = onLimit;
  });

  // While recording: a second counter, and a stop at the limit.
  useEffect(() => {
    if (!live) return;
    const tick = setInterval(() => {
      setSeconds((s) => {
        if (s + 1 >= limit) {
          recorder.current?.stop();
          limited.current?.();
        }
        return s + 1;
      });
    }, 1000);
    return () => clearInterval(tick);
  }, [live, limit]);

  // Leaving the page while recording lets the microphone go.
  useEffect(() => () => recorder.current?.stream.getTracks().forEach((track) => track.stop()), []);

  const start = async (): Promise<StartResult> => {
    const type = recordingType();
    if (!type || !navigator.mediaDevices?.getUserMedia) return 'unsupported';
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const rec = new MediaRecorder(stream, { mimeType: type });
      chunks.current = [];
      rec.ondataavailable = (e) => {
        if (e.data.size > 0) chunks.current.push(e.data);
      };
      rec.onstop = () => {
        stream.getTracks().forEach((track) => track.stop());
        setLive(false);
        const file = recordingFile(type, base);
        done.current(new File(chunks.current, file.name, { type: file.mime }));
      };
      recorder.current = rec;
      setSeconds(0);
      rec.start(1000);
      setLive(true);
      return 'recording';
    } catch {
      return 'denied';
    }
  };

  const stop = () => {
    if (recorder.current?.state === 'recording') recorder.current.stop();
  };

  return { live, seconds, clock: clockOf(seconds), start, stop };
}
