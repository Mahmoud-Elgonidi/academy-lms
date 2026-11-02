import type { CourseBalance } from './types';

/*
 * A course's balance as the family reads it (docs/architecture/02, section 27,
 * J5a), worked out from GET family/courses/{enrollment}/balance alone: how it
 * stands (one word for the card's tone), the bar of lessons left with the
 * mark where the next invoice goes out, and what to say under it, in order.
 * The sentences themselves are the API's; nothing here converts a time or a
 * sum.
 */

/** How the course stands, first match wins: the card's tone and its first words. */
export type BalanceState =
  /** Started by the family, waiting for its first payment: the lessons come once it is paid. */
  | 'first_payment'
  | 'paused'
  | 'ended'
  /** An end day is set: no renewal. */
  | 'ending'
  /** No paid lessons left. */
  | 'empty'
  /** At or below the renewal mark (or a package's invoice already waits). */
  | 'low'
  | 'calm';

export function balanceState(b: Pick<CourseBalance, 'status' | 'lessons_left' | 'plan' | 'ends_on' | 'waiting_invoice'>): BalanceState {
  if (b.status === 'pending_payment') return 'first_payment';
  if (b.status === 'paused') return 'paused';
  if (b.status === 'ended') return 'ended';
  if (b.ends_on) return 'ending';
  if (b.lessons_left === 0) return 'empty';
  if (b.waiting_invoice) return 'low';
  if (b.plan && b.lessons_left !== null && b.lessons_left <= b.plan.renews_at_lessons_left) return 'low';
  return 'calm';
}

export type Tone = 'brand' | 'attention' | 'danger' | 'info' | 'muted';

export const STATE_TONE: Record<BalanceState, Tone> = {
  first_payment: 'attention',
  paused: 'info',
  ended: 'muted',
  ending: 'attention',
  empty: 'danger',
  low: 'attention',
  calm: 'brand',
};

/**
 * The bar: the lessons left out of the package (or out of what is left, when
 * more than one package is paid ahead), and where the renewal mark sits on
 * it, both as fractions of the track. Null without a count of lessons.
 */
export type BalanceBar = { left: number; total: number; fill: number; mark: number | null; markAt: number | null };

export function balanceBar(b: Pick<CourseBalance, 'lessons_left' | 'plan'>): BalanceBar | null {
  if (b.lessons_left === null) return null;
  const left = Math.max(0, b.lessons_left);
  const total = Math.max(1, left, b.plan?.sessions_per_block ?? 0);
  const markAt = b.plan && b.plan.renews_at_lessons_left > 0 && b.plan.renews_at_lessons_left < total ? b.plan.renews_at_lessons_left : null;
  return { left, total, fill: left / total, mark: markAt === null ? null : markAt / total, markAt };
}

/**
 * The kids' look: one dot a lesson, at most `max`, and how many more there
 * are beyond the dots ("+3").
 */
export function kidsDots(left: number | null, max = 10): { dots: number; more: number } {
  const n = Math.max(0, left ?? 0);
  return { dots: Math.min(n, max), more: Math.max(0, n - max) };
}

/** How soon the waiting invoice is due: past it, within a day and a half, or later. */
export type DueSoon = 'overdue' | 'soon' | 'later' | null;

export function dueSoon(due: { utc: string } | null | undefined, now: number): DueSoon {
  if (!due) return null;
  const at = Date.parse(due.utc);
  if (Number.isNaN(at)) return null;
  if (at <= now) return 'overdue';
  return at - now <= 36 * 3600_000 ? 'soon' : 'later';
}

/** Where "pay" opens: the invoice's own page, for whoever pays it (the API gives the id to the payer alone). */
export function payHref(b: Pick<CourseBalance, 'waiting_invoice'>): string | null {
  return b.waiting_invoice?.id ? `/family/invoices/${b.waiting_invoice.id}` : null;
}

/** What the card says under the bar, in order: what to do first (pay), then what is coming, then what to know. */
export type BalanceRow =
  | { kind: 'waiting'; tone: Tone; text: string; due: DueSoon; href: string | null; amount: string; dueLabel: string | null }
  | { kind: 'expiry'; tone: Tone; text: string }
  | { kind: 'next'; tone: Tone; text: string; now: boolean }
  | { kind: 'note'; tone: Tone; text: string };

/** `now` is this request's moment (by default, the clock's). */
export function balanceRows(b: CourseBalance, now: number = Date.now()): BalanceRow[] {
  const rows: BalanceRow[] = [];
  if (b.waiting_invoice) {
    const due = dueSoon(b.waiting_invoice.due_at, now);
    rows.push({
      kind: 'waiting',
      tone: due === 'overdue' ? 'danger' : 'attention',
      text: b.waiting_invoice.text,
      due,
      href: payHref(b),
      amount: b.waiting_invoice.outstanding,
      dueLabel: b.waiting_invoice.due_at?.label ?? null,
    });
  }
  if (b.expiry) rows.push({ kind: 'expiry', tone: b.expiry.soon ? 'attention' : 'muted', text: b.expiry.text });
  if (b.next_invoice) rows.push({ kind: 'next', tone: 'info', text: b.next_invoice.text, now: b.next_invoice.lessons_until === 0 });
  if (b.renewal_note) rows.push({ kind: 'note', tone: 'muted', text: b.renewal_note });
  return rows;
}

/** Which way the card reads: a child's few words, a student's (no money), or the whole of it. */
export function balanceLook(b: Pick<CourseBalance, 'viewer_stage'>): 'kids' | 'student' | 'classic' {
  return b.viewer_stage === 'kids' ? 'kids' : b.viewer_stage === 'classic' ? 'classic' : 'student';
}

/** The moments the clock alone changes a card (its invoice due soon, then past due), for RefreshAt. */
export function balanceMoments(b: Pick<CourseBalance, 'waiting_invoice'>): string[] {
  const due = b.waiting_invoice?.due_at?.utc;
  const at = due ? Date.parse(due) : NaN;
  if (Number.isNaN(at)) return [];
  return [new Date(at - 36 * 3600_000).toISOString(), new Date(at).toISOString()];
}
