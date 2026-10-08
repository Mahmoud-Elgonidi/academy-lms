import type { LocalTime } from './time';

/**
 * Lesson recordings as the API gives them (Classroom's RecordingsController):
 * someone's consent, with the words to agree to; the recordings to watch; and
 * what a lesson's screen shows about recording.
 */
export type RecordingConsent = {
  person: { id: string; name: string };
  self: boolean;
  state: 'agreed' | 'none' | string;
  granted_at: LocalTime | null;
  /** Who agreed, in words; null when nobody has. */
  by: string | null;
  /** Whether the person signed in may agree or take it back. */
  can_change: boolean;
  /** Who decides, in words, when the person signed in cannot. */
  decides: string | null;
  text: { version: number; title: string; intro: string; sections: { title: string; body: string }[]; agree: string };
};

export type RecordingConsents = { recording: 'off' | 'manual' | 'auto' | string; people: RecordingConsent[] };

export type RecordingRow = {
  id: string;
  at: LocalTime;
  teacher: string;
  student: string | null;
  media: 'video' | 'audio' | string;
  duration_sec: number;
  expires_at: LocalTime | null;
};

export type RecordingList = { shared: boolean; data: RecordingRow[] };

/** What a lesson's screen shows: the academy's mode, whether this person may start and stop it, whether it runs, who has not agreed. */
export type LessonRecording = { mode: string; controls: boolean; running: boolean; missing: string[] };

/** "25:00", or "1:02:05" past an hour. */
export function length(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  const two = (n: number) => String(n).padStart(2, '0');
  return h > 0 ? `${h}:${two(m)}:${two(s)}` : `${m}:${two(s)}`;
}
