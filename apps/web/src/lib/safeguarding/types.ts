import type { LocalTime } from '@/lib/time';

/** kids: a child's words, the text optional, no lesson and no "danger now"; student; classic (guardians, adults); teacher. */
export type ConcernLook = 'kids' | 'student' | 'classic' | 'teacher';

/** GET /v1/safeguarding/new: what a concern can be about, in the reporter's own words. */
export type ConcernOptions = {
  look: ConcernLook;
  learner: { id: string; name: string } | null;
  categories: { key: string; label: string }[];
  text_required: boolean;
  urgent_allowed: boolean;
  lessons: { id: string; subject: string; starts_at: LocalTime | null; with: string }[];
  students: { id: string; name: string }[];
  danger: string;
};

/** One concern as its reporter sees it: never the leads' notes or what was done. */
export type MyConcern = {
  id: string;
  number: string;
  category: { key: string; label: string };
  status: { key: 'new' | 'open' | 'closed'; label: string };
  urgent: boolean;
  text: string;
  reply: string | null;
  created_at: LocalTime | null;
  closed_at: LocalTime | null;
};

/** The icon each kind of concern shows with. */
export const CATEGORY_ICONS = {
  adult_behaviour: 'frown',
  outside_contact: 'chat',
  content: 'eyeOff',
  bullying: 'hand',
  wellbeing: 'heart',
  other: 'dots',
} as const;
