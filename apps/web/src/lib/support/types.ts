import type { LocalTime } from '../time';

/**
 * A family's or a teacher's tickets to the academy, as /v1/support answers
 * them ("Help" in /my and in /teach). Each person sees
 * only their own, never the academy's internal notes or decisions.
 */
export type TicketKind = 'complaint' | 'billing' | 'technical' | 'other';
export type TicketStatus = 'open' | 'waiting' | 'resolved' | 'closed';

export type Labelled<K extends string> = { key: K; label: string };

export type TicketLesson = { id: string; subject: string; with: string; starts_at: LocalTime | null; status: string };

export type TicketSummary = {
  id: string;
  reference: string;
  subject: string;
  kind: Labelled<TicketKind>;
  status: Labelled<TicketStatus>;
  learner: { id: string; name: string } | null;
  created_at: LocalTime | null;
  last_message_at: LocalTime | null;
  your_turn: boolean;
};

export type TicketMessage = {
  id: string;
  side: 'requester' | 'staff' | 'system';
  kind: 'message' | 'event';
  author: string | null;
  body: string | null;
  event: string | null;
  file: { name: string; mime: string; size: number; url: string } | null;
  at: LocalTime | null;
};

export type Ticket = TicketSummary & {
  lesson: TicketLesson | null;
  can_reply: boolean;
  can_reopen: boolean;
  can_close: boolean;
  reopen_until: LocalTime | null;
  messages: TicketMessage[];
};

export type NewTicketOptions = {
  learner: { id: string; name: string } | null;
  kinds: { key: TicketKind; label: string }[];
  lessons: TicketLesson[];
};

/** Where a message's picture or PDF is read from in the portal: through the BFF, with the person's own token. */
export function fileHref(ticket: string, message: string): string {
  return `/bff/tickets/${ticket}/files/${message}`;
}
