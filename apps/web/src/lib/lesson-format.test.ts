import { createElement, type ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { NextIntlClientProvider } from 'next-intl';
import { describe, expect, it } from 'vitest';
import ar from '../../messages/ar.json';
import en from '../../messages/en.json';
import { FormatBadge, SeatDots } from '../components/lesson-format';
import { courseGroup, firstName, formatOf, groupOf, seatDots, seatsLeft, someNames } from './lesson-format';

// The provider takes its children as a prop's type: passed as createElement's third argument, typed through its props.
const provider = NextIntlClientProvider as unknown as (props: { locale: string; messages: typeof ar; timeZone: string }) => ReactElement;
const render = (element: ReactElement, locale: 'ar' | 'en' = 'ar') => renderToStaticMarkup(createElement(provider, { locale, messages: locale === 'ar' ? ar : en, timeZone: 'UTC' }, element));

describe('seat dots', () => {
  it('are one per seat, the taken ones first', () => {
    expect(seatDots(3, 5)).toEqual([true, true, true, false, false]);
    expect(seatDots(0, 2)).toEqual([false, false]);
    expect(seatDots(4, 4)).toEqual([true, true, true, true]);
  });

  it('never show more taken than there are seats, or fewer than none', () => {
    expect(seatDots(7, 5)).toEqual([true, true, true, true, true]);
    expect(seatDots(-2, 3)).toEqual([false, false, false]);
  });

  it('give way to the numbers past a row of dots', () => {
    expect(seatDots(5, 12)).toHaveLength(12);
    expect(seatDots(5, 13)).toEqual([]);
    expect(seatDots(5, 20)).toEqual([]);
    expect(seatDots(0, 0)).toEqual([]);
  });

  it('leave the seats still free', () => {
    expect(seatsLeft({ taken: 3, capacity: 5 })).toBe(2);
    expect(seatsLeft({ taken: 6, capacity: 5 })).toBe(0);
  });
});

describe('a lesson’s format', () => {
  const family = {
    format: 'group',
    group: { capacity: '5', seats_taken: 3, seat_number: 4, classmates: ['ليلى', 'عمر'], classmates_count: 2, classmates_label: 'مع زميلين', seat: 'excused', seat_label: 'معتذر', students: null },
  };

  it('is private unless the API says group', () => {
    expect(formatOf({ format: 'private', group: null })).toBe('private');
    expect(formatOf({})).toBe('private');
    expect(formatOf({ format: 'group', group: null })).toBe('group');
    expect(groupOf({ format: 'private', group: null })).toBeNull();
  });

  it('reads a family’s group: seats as numbers, classmates by first name, its own seat', () => {
    expect(groupOf(family)).toEqual({
      capacity: 5,
      taken: 3,
      seatNumber: 4,
      classmates: ['ليلى', 'عمر'],
      classmatesCount: 2,
      classmatesLabel: 'مع زميلين',
      seat: 'excused',
      seatLabel: 'معتذر',
      students: [],
    });
  });

  it('reads the kids’ look as a count only', () => {
    const kids = groupOf({ format: 'group', group: { capacity: 4, seats_taken: 4, classmates: null, classmates_count: 3, classmates_label: 'مع 3 من زملائك' } });
    expect(kids?.classmates).toBeNull();
    expect(kids?.classmatesCount).toBe(3);
  });

  it('reads a teacher’s roster, and never fewer seats than students', () => {
    const teacher = groupOf({ format: 'group', group: { capacity: 2, seats_taken: 3, classmates: null, students: [{ id: 'a', name: 'ليلى أحمد' }, { id: 'b', name: 'عمر' }, { id: 'c', name: 'سارة' }] } });
    expect(teacher?.students.map((s) => s.id)).toEqual(['a', 'b', 'c']);
    expect(teacher?.capacity).toBe(3);
    expect(teacher?.seatNumber).toBeNull();
  });

  it('shows some names and how many more', () => {
    expect(someNames(['a', 'b', 'c', 'd', 'e'], 3)).toEqual({ names: ['a', 'b', 'c'], more: 2 });
    expect(someNames(['a'], 3)).toEqual({ names: ['a'], more: 0 });
    expect(firstName('  ليلى أحمد ')).toBe('ليلى');
  });
});

describe('a course’s group, as the API says it', () => {
  it('reads the seats and the learner’s own', () => {
    expect(courseGroup({ format: 'group', group: { id: 'g', capacity: 5, seats_taken: 3, seat: 4 } })).toEqual({ id: 'g', capacity: 5, taken: 3, seat: 4 });
  });

  it('is null for a private course, and never fewer seats than students', () => {
    expect(courseGroup({ format: 'private', group: null })).toBeNull();
    expect(courseGroup({})).toBeNull();
    expect(courseGroup({ format: 'group', group: { id: 'g', capacity: 2, seats_taken: 3, seat: 0 } })).toEqual({ id: 'g', capacity: 3, taken: 3, seat: null });
  });
});

describe('the format badge', () => {
  it('draws a group with the people icon, its seats as dots and as numbers, and says them in words', () => {
    const html = render(createElement(FormatBadge, { format: 'group', seats: { taken: 3, capacity: 5 } }));
    expect(html).toContain('data-format="group"');
    expect(html).toContain('جماعية');
    expect(html.match(/data-seat="taken"/g)).toHaveLength(3);
    expect(html.match(/data-seat="free"/g)).toHaveLength(2);
    expect(html).toContain('3/5');
    expect(html).toContain('المقاعد المشغولة: 3 من 5');
    expect(html).toContain('<svg');
    expect(html).toContain('bg-group-soft');
  });

  it('is a plain badge for a private lesson, with no seats', () => {
    const html = render(createElement(FormatBadge, { format: 'private' }), 'en');
    expect(html).toContain('data-format="private"');
    expect(html).toContain('Private');
    expect(html).not.toContain('data-seat');
    expect(html).not.toContain('<svg');
  });

  it('says only "group" when its seats are not known, and only numbers for a large group', () => {
    expect(render(createElement(FormatBadge, { format: 'group' }), 'en')).not.toContain('data-seat');
    const large = render(createElement(FormatBadge, { format: 'group', seats: { taken: 9, capacity: 16 } }), 'en');
    expect(large).not.toContain('data-seat=');
    expect(large).toContain('9/16');
    expect(large).toContain('Seats taken: 9 of 16');
  });

  it('hides the dots from assistive technology', () => {
    expect(render(createElement(SeatDots, { taken: 1, capacity: 3 }))).toMatch(/^<span aria-hidden="true"/);
  });
});
