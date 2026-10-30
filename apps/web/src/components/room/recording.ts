'use client';

import { useIsRecording, useRoomInfo } from '@livekit/components-react';
import { useState, useTransition } from 'react';
import { recordLesson } from '@/lib/actions/shared';
import type { LessonRecording } from '@/lib/recordings';

export type RecordingState = {
  /** Whether the room says anything about recording (the academy records, or it runs). */
  shown: boolean;
  on: boolean;
  /** This person starts and stops it (the lesson's teacher). */
  controls: boolean;
  /** Who has not agreed yet, while that keeps it from starting. */
  missing: string[];
  pending: boolean;
  error?: string;
  toggle: () => void;
  dismiss: () => void;
};

/**
 * The recording sign, for everyone (LiveKit says when the room is recorded,
 * and so does the room's metadata), and the teacher's start and stop, once
 * everyone it records agreed.
 */
export function useRecording(lesson: string, recording: LessonRecording): RecordingState {
  const recorded = useIsRecording();
  const { metadata } = useRoomInfo();
  const [running, setRunning] = useState<boolean | undefined>(undefined);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const said = (() => {
    try {
      const meta = JSON.parse(metadata || '{}') as { recording?: boolean };
      return typeof meta.recording === 'boolean' ? meta.recording : undefined;
    } catch {
      return undefined;
    }
  })();
  const on = recorded || (running ?? said ?? recording.running);

  const toggle = () =>
    start(async () => {
      const done = await recordLesson(lesson, !on);
      if (!done.ok) {
        setError(done.message);
        return;
      }
      setError(undefined);
      setRunning(done.running);
    });

  return {
    shown: recording.mode !== 'off' || on,
    on,
    controls: recording.controls,
    missing: !on ? recording.missing : [],
    pending,
    error,
    toggle,
    dismiss: () => setError(undefined),
  };
}
