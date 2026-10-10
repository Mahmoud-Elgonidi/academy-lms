import type { LocalTime } from '../time';

/**
 * Conversations between a teacher and a student, as /v1/messages answers
 * them (the same as "Messages" in /teach and /my): the student's guardians
 * are in every one, and a phone number, an email, a link or an account name
 * is hidden before anyone reads it.
 */
export type Side = 'teacher' | 'student' | 'guardian';

/** One row of my list: a student I teach, or a teacher of the learner; `id` is null until it is opened. */
export type ConversationRow = {
  id: string | null;
  with: { id: string; name: string };
  last_message: { body: string; mine: boolean; at: LocalTime | null } | null;
  unread: number;
  can_write: boolean;
  why_not: string | null;
};

export type ChatMessage = {
  id: string;
  side: Side;
  side_label: string;
  author: string;
  mine: boolean;
  body: string;
  /** What was hidden from it, in words. */
  hidden: string | null;
  at: LocalTime | null;
};

export type Conversation = {
  id: string;
  with: { id: string; name: string };
  /** Everyone in it, by name; `me` is the reader. */
  members: { id: string; name: string; side: Side; label: string; me: boolean }[];
  messages: ChatMessage[];
  can_write: boolean;
  why_not: string | null;
  notice: string;
};

export const MAX_LENGTH = 2000;
