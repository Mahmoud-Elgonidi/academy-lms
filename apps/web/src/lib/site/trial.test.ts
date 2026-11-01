import { describe, expect, it } from 'vitest';
import ar from '../../../messages/ar.json';
import en from '../../../messages/en.json';
import type { LocalTime } from '@/lib/time';
import {
  birthYearFor,
  bookingSubject,
  byDay,
  calendarEventOk,
  canPickTime,
  checkStep,
  cleanCode,
  EMPTY_DRAFT,
  googleCalendarUrl,
  icsFor,
  nextFrom,
  normalizePhone,
  openingDay,
  phoneLooksRight,
  secondsUntil,
  stepsFor,
  stillOffered,
  suits,
  waitLabel,
  weekdayOf,
  whatsappLink,
  type TrialDraft,
  type TrialSettings,
} from './trial';

const family: TrialSettings = { booking: 'family', response_hours: 4, notice_hours: 3, help_choose_subject: true, help_choose_subject_id: 'quran' };
const staff: TrialSettings = { ...family, booking: 'staff' };
const draft = (change: Partial<TrialDraft> = {}): TrialDraft => ({ ...EMPTY_DRAFT, ...change });

/** A time as the API writes it, on Cairo's clock in November (+02:00). */
const at = (local: string): LocalTime => {
  const utc = new Date(`${local}+02:00`).toISOString().replace(/\.\d{3}Z$/, 'Z');
  return { utc, local: `${local}+02:00`, timezone: 'Africa/Cairo', label: local };
};

describe('the steps of a free trial request', () => {
  it('picks a time only while families book and the subject is known', () => {
    expect(stepsFor(family, draft({ subjectId: 'arabic' }), false)).toEqual(['who', 'when', 'time', 'contact']);
    // Before a subject is chosen, the family still sees the four steps ahead.
    expect(stepsFor(family, draft(), false)).toEqual(['who', 'when', 'time', 'contact']);
    expect(stepsFor(staff, draft({ subjectId: 'arabic' }), false)).toEqual(['who', 'when', 'contact']);
    // They would rather be called: no time to pick.
    expect(stepsFor(family, draft({ subjectId: 'arabic' }), true)).toEqual(['who', 'when', 'contact']);
  });

  it('books "help me choose" in the academy\'s own trial subject, and only when it has one', () => {
    expect(bookingSubject(family, draft({ helpChoose: true }))).toBe('quran');
    expect(canPickTime(family, draft({ helpChoose: true }))).toBe(true);
    const none = { ...family, help_choose_subject: false, help_choose_subject_id: null };
    expect(bookingSubject(none, draft({ helpChoose: true }))).toBeNull();
    expect(stepsFor(none, draft({ helpChoose: true }), false)).toEqual(['who', 'when', 'contact']);
  });

  it('says what each step still needs, by the key of its sentence', () => {
    expect(checkStep('who', draft())).toEqual({ name: 'name', childName: 'childName', childAge: 'childAge', subjectId: 'subject' });
    expect(checkStep('who', draft({ who: 'me', name: 'Sara', helpChoose: true }))).toEqual({});
    expect(checkStep('who', draft({ name: 'Sara', childName: 'Yara', childAge: '2', subjectId: 'x' }))).toEqual({ childAge: 'childAge' });
    expect(checkStep('when', draft({ from: '18', to: '17' }))).toEqual({ to: 'hours' });
    expect(checkStep('when', draft({ from: '17', to: '21' }))).toEqual({});
    expect(checkStep('contact', draft())).toEqual({ phone: 'phoneNeeded' });
    expect(checkStep('contact', draft({ phone: '+966 50 123 4567' }))).toEqual({});
    expect(checkStep('contact', draft({ phone: '12', email: 'not-an-email' }))).toEqual({ phone: 'phone', email: 'email' });
  });

  it('has every sentence it names, in both languages', () => {
    const keys = new Set<string>();
    for (const step of ['who', 'when', 'contact'] as const) {
      for (const d of [draft(), draft({ from: '9', to: '8', phone: '1', email: 'x' })]) Object.values(checkStep(step, d)).forEach((k) => keys.add(k));
    }
    for (const messages of [ar, en]) {
      const errors = messages.site.trial.errors as Record<string, string>;
      for (const key of keys) expect(errors[key], key).toBeTruthy();
      for (const step of ['who', 'when', 'time', 'contact']) expect((messages.site.trial.steps as Record<string, string>)[step]).toBeTruthy();
    }
  });

  it('turns an age into a year of birth the API takes', () => {
    expect(birthYearFor('8', 2026)).toBe(2018);
    expect(birthYearFor('2', 2026)).toBeNull();
    expect(birthYearFor('', 2026)).toBeNull();
  });
});

describe('a number and its code', () => {
  it('reads a number as typed, Arabic digits and 00 included', () => {
    expect(normalizePhone('+966 50 123 4567')).toBe('+966501234567');
    expect(normalizePhone('00966501234567')).toBe('+966501234567');
    expect(normalizePhone('+٩٦٦٥٠١٢٣٤٥٦٧')).toBe('+966501234567');
    expect(phoneLooksRight('+20 100 123 4567')).toBe(true);
    expect(phoneLooksRight('050')).toBe(false);
  });

  it('keeps only the code\'s six digits', () => {
    expect(cleanCode('١٢٣ ٤٥٦')).toBe('123456');
    expect(cleanCode('12-34-56-78')).toBe('123456');
  });

  it('counts down the wait for another code', () => {
    expect(secondsUntil(61_000, 0)).toBe(61);
    expect(secondsUntil(0, 5_000)).toBe(0);
    expect(waitLabel(65)).toBe('1:05');
  });
});

describe('the times on the family\'s clock', () => {
  const times = [at('2026-11-03T19:00:00'), at('2026-11-01T18:30:00'), at('2026-11-01T09:00:00'), at('2026-11-02T17:00:00')];

  it('groups them by the family\'s day, soonest first, as the API wrote them', () => {
    const days = byDay(times, draft());
    expect(days.map((d) => d.day)).toEqual(['2026-11-01', '2026-11-02', '2026-11-03']);
    expect(days[0]!.times.map((t) => t.local.slice(11, 16))).toEqual(['09:00', '18:30']);
  });

  it('marks what suits the days and hours they gave, and opens on the first day with one', () => {
    // 2026-11-01 is a Sunday (7), 2026-11-02 a Monday (1).
    expect(weekdayOf('2026-11-01')).toBe(7);
    expect(weekdayOf('2026-11-02')).toBe(1);
    const evenings = { days: [1, 2], from: '17', to: '21' };
    expect(suits(at('2026-11-02T17:00:00'), evenings)).toBe(true);
    expect(suits(at('2026-11-02T21:00:00'), evenings)).toBe(false);
    expect(suits(at('2026-11-01T18:30:00'), evenings)).toBe(false);
    const days = byDay(times, evenings);
    expect(days.map((d) => d.suiting)).toEqual([0, 1, 1]);
    expect(openingDay(days)).toBe('2026-11-02');
    expect(openingDay(byDay(times, draft()))).toBe('2026-11-01');
    expect(openingDay([])).toBeNull();
  });

  it('asks for the next two weeks from the day after the last one shown', () => {
    expect(nextFrom(byDay(times, draft()), null)).toBe('2026-11-04');
    expect(nextFrom([], '2026-11-15')).toBe('2026-11-16');
  });

  it('knows a time that went from the list', () => {
    expect(stillOffered(times, times[0]!.utc)).toBe(true);
    expect(stillOffered(times, '2026-11-01T10:00:00Z')).toBe(false);
  });
});

describe('the booked lesson in a calendar', () => {
  const event = { utc: '2026-11-01T16:30:00Z', minutes: 30, title: 'Free trial lesson: Quran, Hifz; Noor', details: 'With Mona' };

  it('writes the calendar file in UTC, its words escaped', () => {
    const ics = icsFor(event, '2026-10-31T10:00:00Z');
    expect(ics).toContain('DTSTART:20261101T163000Z\r\n');
    expect(ics).toContain('DTEND:20261101T170000Z\r\n');
    expect(ics).toContain('SUMMARY:Free trial lesson: Quran\\, Hifz\\; Noor\r\n');
    expect(ics).toContain('DTSTAMP:20261031T100000Z');
    expect(ics.startsWith('BEGIN:VCALENDAR\r\n')).toBe(true);
    expect(icsFor({ ...event, title: 'a\r\nEND:VCALENDAR' }, event.utc)).not.toContain('\r\nEND:VCALENDAR\r\nEND');
  });

  it('opens Google Calendar on the same UTC span', () => {
    expect(googleCalendarUrl(event)).toContain('dates=20261101T163000Z%2F20261101T170000Z');
  });

  it('writes only a UTC instant and a sensible length', () => {
    expect(calendarEventOk(event)).toBe(true);
    expect(calendarEventOk({ ...event, utc: '2026-11-01T18:30:00+02:00' })).toBe(false);
    expect(calendarEventOk({ ...event, minutes: 600 })).toBe(false);
    expect(calendarEventOk({ ...event, utc: null })).toBe(false);
  });

  it('links the academy\'s WhatsApp with the first words', () => {
    expect(whatsappLink('+966500000000', 'Hello')).toBe('https://wa.me/966500000000?text=Hello');
    expect(whatsappLink(null)).toBeNull();
  });
});
