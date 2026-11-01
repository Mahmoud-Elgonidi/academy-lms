import type { LocalTime } from '@/lib/time';

/**
 * A family's free trial from the academy's page (docs/architecture/02,
 * section 27), as the portal walks it: who is learning and what, when suits
 * them, then (while the academy lets families book) a time from the API's
 * list and the number with its code, else a request the academy answers
 * within its hours. Pure: the steps, what each needs, the times by day on the
 * family's clock as the API wrote them (never converting zones), and the
 * calendar file of a booked lesson from its UTC instant.
 */

/** How the academy takes a free trial asked for on its page (GET v1/site `trials`). */
export type TrialSettings = {
  booking: 'family' | 'staff';
  response_hours: number;
  notice_hours: number;
  help_choose_subject: boolean;
  help_choose_subject_id?: string | null;
};

export type Who = 'me' | 'child';
export type TeacherGender = '' | 'female' | 'male';

/** What the family has typed so far; kept whole whatever step fails. */
export type TrialDraft = {
  who: Who;
  name: string;
  childName: string;
  /** Years, as typed ("8"); the server turns it into a year of birth. */
  childAge: string;
  subjectId: string;
  helpChoose: boolean;
  /** ISO weekdays (1 Monday … 7 Sunday). */
  days: number[];
  /** Hours on the family's clock ("17"), or empty for any time. */
  from: string;
  to: string;
  teacherGender: TeacherGender;
  phone: string;
  email: string;
  country: string;
  message: string;
};

export const EMPTY_DRAFT: TrialDraft = {
  who: 'child',
  name: '',
  childName: '',
  childAge: '',
  subjectId: '',
  helpChoose: false,
  days: [],
  from: '',
  to: '',
  teacherGender: '',
  phone: '',
  email: '',
  country: '',
  message: '',
};

export type StepKey = 'who' | 'when' | 'time' | 'contact';

/** Ages a child may book a trial at (the API takes a year of birth 3 to 18 years back). */
export const CHILD_AGES = Array.from({ length: 15 }, (_, i) => i + 3);

/** The subject whose times the family books from: theirs, the academy's own for "help me choose", or none. */
export function bookingSubject(settings: TrialSettings, draft: TrialDraft): string | null {
  if (draft.helpChoose) return settings.help_choose_subject_id ?? null;
  return draft.subjectId || null;
}

/**
 * Whether the family picks its own time here: the academy lets families book,
 * and the subject will be known (theirs, once chosen, or the academy's own for
 * "help me choose"; without one, sales choose it with them).
 */
export function canPickTime(settings: TrialSettings, draft: TrialDraft): boolean {
  if (settings.booking !== 'family') return false;
  return draft.helpChoose ? Boolean(settings.help_choose_subject_id) : true;
}

/** The steps in order: a time to pick only while the family books it (and did not choose to leave it to the academy). */
export function stepsFor(settings: TrialSettings, draft: TrialDraft, leaveTimeToUs: boolean): StepKey[] {
  return canPickTime(settings, draft) && !leaveTimeToUs ? ['who', 'when', 'time', 'contact'] : ['who', 'when', 'contact'];
}

/** A year of birth from an age, on the server's calendar. */
export function birthYearFor(age: string, year: number): number | null {
  const years = Number(age);
  return Number.isInteger(years) && years >= 3 && years <= 17 ? year - years : null;
}

const ARABIC_DIGITS: Record<string, string> = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
  '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };

/** Latin digits for any typed ("٠٥٠", "۰۵"). */
export function latinDigits(text: string): string {
  return text.replace(/[٠-٩۰-۹]/g, (d) => ARABIC_DIGITS[d] ?? d);
}

/** A number as the API takes it (E.164): digits only after a "+", "00" read as "+". */
export function normalizePhone(raw: string): string {
  const latin = latinDigits(raw).trim();
  const digits = latin.replace(/\D/g, '');
  if (latin.startsWith('+')) return `+${digits}`;
  if (digits.startsWith('00')) return `+${digits.slice(2)}`;
  return digits ? `+${digits}` : '';
}

/** Whether a number looks like one with its country code (the API's own rule). */
export function phoneLooksRight(raw: string): boolean {
  return /^\+[1-9]\d{6,14}$/.test(normalizePhone(raw));
}

/** Only the digits of a code ("١٢٣ ٤٥٦" is "123456"). */
export function cleanCode(raw: string): string {
  return latinDigits(raw).replace(/\D/g, '').slice(0, 6);
}

/** What a step still needs: field → the message key (site.trial.errors.*) that says so. */
export function checkStep(step: StepKey, draft: TrialDraft, needEmailOrPhone = true): Record<string, string> {
  const wrong: Record<string, string> = {};
  if (step === 'who') {
    if (draft.name.trim().length < 2) wrong.name = 'name';
    if (draft.who === 'child') {
      if (!draft.childName.trim()) wrong.childName = 'childName';
      if (!CHILD_AGES.includes(Number(draft.childAge))) wrong.childAge = 'childAge';
    }
    if (!draft.helpChoose && !draft.subjectId) wrong.subjectId = 'subject';
  }
  if (step === 'when' && draft.from && draft.to && Number(draft.to) <= Number(draft.from)) wrong.to = 'hours';
  if (step === 'contact' && needEmailOrPhone) {
    if (!phoneLooksRight(draft.phone)) wrong.phone = draft.phone.trim() ? 'phone' : 'phoneNeeded';
    if (draft.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(draft.email.trim())) wrong.email = 'email';
  }
  return wrong;
}

/** The family's day of a time ("YYYY-MM-DD"), as the API wrote it on their clock. */
export function dayOf(time: LocalTime): string {
  return time.local.slice(0, 10);
}

/** The ISO weekday (1 Monday … 7 Sunday) of a calendar day. */
export function weekdayOf(day: string): number {
  const weekday = new Date(`${day}T00:00:00Z`).getUTCDay();
  return weekday === 0 ? 7 : weekday;
}

/** Whether a time fits the days and hours the family said suit them (any, when they said none). */
export function suits(time: LocalTime, draft: Pick<TrialDraft, 'days' | 'from' | 'to'>): boolean {
  if (draft.days.length > 0 && !draft.days.includes(weekdayOf(dayOf(time)))) return false;
  const hour = Number(time.local.slice(11, 13)) + Number(time.local.slice(14, 16)) / 60;
  if (draft.from !== '' && hour < Number(draft.from)) return false;
  if (draft.to !== '' && hour >= Number(draft.to)) return false;
  return true;
}

export type Day = { day: string; times: LocalTime[]; suiting: number };

/** The times by the family's day, soonest first, with how many of each day fit what they asked for. */
export function byDay(times: LocalTime[], draft: Pick<TrialDraft, 'days' | 'from' | 'to'>): Day[] {
  const days = new Map<string, LocalTime[]>();
  for (const time of [...times].sort((a, b) => a.utc.localeCompare(b.utc))) {
    const day = dayOf(time);
    days.set(day, [...(days.get(day) ?? []), time]);
  }
  return [...days].map(([day, list]) => ({ day, times: list, suiting: list.filter((t) => suits(t, draft)).length }));
}

/** The day to open on: the first with a time that suits them, else the first with any. */
export function openingDay(days: Day[]): string | null {
  return (days.find((d) => d.suiting > 0) ?? days[0])?.day ?? null;
}

/** The day after the last one shown, to ask for the next two weeks from. */
export function nextFrom(days: Day[], asked: string | null): string | null {
  const last = days.at(-1)?.day ?? asked;
  if (!last) return null;
  const date = new Date(`${last}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + 1);
  return date.toISOString().slice(0, 10);
}

/** Whether a time is still offered (the same instant). */
export function stillOffered(times: LocalTime[], utc: string): boolean {
  return times.some((t) => t.utc === utc);
}

/** "20261101T163000Z" from "2026-11-01T16:30:00Z". */
function compactUtc(utc: string): string {
  return utc.replace(/[-:]/g, '').replace(/\.\d+/, '');
}

/** An instant some minutes later, both in UTC. */
function laterUtc(utc: string, minutes: number): string {
  return new Date(Date.parse(utc) + minutes * 60_000).toISOString().replace(/\.\d{3}Z$/, 'Z');
}

/** A text line for a calendar file: no line breaks, and its commas, semicolons and backslashes escaped. */
function icsText(text: string): string {
  return text.replace(/[\r\n]+/g, ' ').replace(/[\\;,]/g, (c) => `\\${c}`).slice(0, 200);
}

export type CalendarEvent = { utc: string; minutes: number; title: string; details?: string };

/** Whether a calendar event is one we write: an instant in UTC and a sensible length. */
export function calendarEventOk(event: { utc?: string | null; minutes?: number }): event is CalendarEvent {
  return typeof event.utc === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(event.utc) && !Number.isNaN(Date.parse(event.utc))
    && Number.isInteger(event.minutes) && (event.minutes ?? 0) >= 10 && (event.minutes ?? 0) <= 240;
}

/** The lesson as a calendar file (iCalendar), in UTC: every calendar shows it on its owner's clock. */
export function icsFor(event: CalendarEvent, stamp: string): string {
  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Academy LMS//Trial lesson//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:trial-${compactUtc(event.utc)}-${event.minutes}@academy-lms`,
    `DTSTAMP:${compactUtc(stamp)}`,
    `DTSTART:${compactUtc(event.utc)}`,
    `DTEND:${compactUtc(laterUtc(event.utc, event.minutes))}`,
    `SUMMARY:${icsText(event.title)}`,
    ...(event.details ? [`DESCRIPTION:${icsText(event.details)}`] : []),
    'BEGIN:VALARM',
    'ACTION:DISPLAY',
    `DESCRIPTION:${icsText(event.title)}`,
    'TRIGGER:-PT30M',
    'END:VALARM',
    'END:VEVENT',
    'END:VCALENDAR',
  ];
  return `${lines.join('\r\n')}\r\n`;
}

/** The same lesson for Google Calendar, in UTC. */
export function googleCalendarUrl(event: CalendarEvent): string {
  const query = new URLSearchParams({
    action: 'TEMPLATE',
    text: event.title,
    dates: `${compactUtc(event.utc)}/${compactUtc(laterUtc(event.utc, event.minutes))}`,
    ...(event.details ? { details: event.details } : {}),
  });
  return `https://calendar.google.com/calendar/render?${query.toString()}`;
}

/** Our own calendar file's address for the lesson. */
export function calendarFileUrl(event: CalendarEvent): string {
  const query = new URLSearchParams({ start: event.utc, minutes: String(event.minutes), title: event.title, ...(event.details ? { details: event.details } : {}) });
  return `/bff/trial-calendar?${query.toString()}`;
}

/** Seconds left until an instant (never below zero): for a code's resend wait and its end. */
export function secondsUntil(at: number, now: number): number {
  return Math.max(0, Math.ceil((at - now) / 1000));
}

/** "1:05" for a wait in seconds. */
export function waitLabel(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

/** The academy's WhatsApp as a link, with words to start the chat. */
export function whatsappLink(number: string | null | undefined, words?: string): string | null {
  if (!number) return null;
  const digits = number.replace(/\D/g, '');
  return `https://wa.me/${digits}${words ? `?text=${encodeURIComponent(words)}` : ''}`;
}
