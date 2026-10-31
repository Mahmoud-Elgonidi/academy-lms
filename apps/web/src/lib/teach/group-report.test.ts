import { describe, expect, it } from 'vitest';
import { reportRows, rowErrors, rowProblems, rowsOf, sharedLessonNeeded, studentsPayload, type GroupStudent } from './group-report';

const student = (id: string, kind: 'lesson' | 'absence', change: Partial<GroupStudent> = {}): GroupStudent => ({
  report_id: `r-${id}`,
  student_id: id,
  name: `طالب ${id}`,
  kind,
  status: 'due',
  engagement: null,
  overall: null,
  notes_for_student: null,
  absence_note: null,
  ...change,
});

describe('a group report’s rows', () => {
  it('start from what each student’s report holds', () => {
    const [came, absent, reviewed] = rowsOf([
      student('a', 'lesson', { engagement: 4, notes_for_student: 'أحسنت' }),
      student('b', 'absence', { absence_note: 'مرض' }),
      student('c', 'lesson', { status: 'reviewed' }),
    ]);
    expect(came).toMatchObject({ studentId: 'a', kind: 'lesson', locked: false, engagement: 4, overall: null, note: 'أحسنت' });
    expect(absent).toMatchObject({ kind: 'absence', absenceNote: 'مرض' });
    expect(reviewed?.locked).toBe(true);
  });

  it('ask for the lesson’s shared part only while someone came whose report is still the teacher’s', () => {
    expect(sharedLessonNeeded(rowsOf([student('a', 'lesson'), student('b', 'absence')]))).toBe(true);
    expect(sharedLessonNeeded(rowsOf([student('a', 'absence'), student('b', 'absence')]))).toBe(false);
    expect(sharedLessonNeeded(rowsOf([student('a', 'lesson', { status: 'reviewed' }), student('b', 'absence')]))).toBe(false);
  });

  it('say what each row is missing: both ratings for one who came, the note for one absent', () => {
    const rows = rowsOf([
      student('a', 'lesson', { engagement: 4 }),
      student('b', 'lesson', { engagement: 4, overall: 5 }),
      student('c', 'absence'),
      student('d', 'absence', { absence_note: '  ' }),
      student('e', 'absence', { absence_note: 'سافر' }),
      student('f', 'lesson', { status: 'reviewed' }),
    ]);
    expect(rowProblems(rows)).toEqual({ a: ['ratings'], c: ['absence_note'], d: ['absence_note'] });
  });

  it('go to the API in their order, each with its own fields, a reviewed one by its id only', () => {
    const rows = rowsOf([
      student('a', 'lesson', { engagement: 4, overall: 5, notes_for_student: '  أحسنت  ' }),
      student('b', 'lesson', { engagement: 3, overall: 3 }),
      student('c', 'absence', { absence_note: ' مرض ' }),
      student('d', 'lesson', { status: 'reviewed', engagement: 2, overall: 2 }),
    ]);
    expect(studentsPayload(rows)).toEqual([
      { student_id: 'a', engagement: 4, overall: 5, notes_for_student: 'أحسنت' },
      { student_id: 'b', engagement: 3, overall: 3, notes_for_student: null },
      { student_id: 'c', absence_note: 'مرض' },
      { student_id: 'd' },
    ]);
  });

  it('take the API’s words back to the row they are about, by its place', () => {
    const rows = rowsOf([student('a', 'lesson'), student('b', 'absence'), student('c', 'lesson')]);
    expect(
      rowErrors(
        {
          'students.0.engagement': ['اختر التركيز.'],
          'students.1.absence_note': ['اكتب ملاحظة الغياب.'],
          'students.2.notes_for_student': ['طويلة جدًا.', 'ثانية'],
          'students.9.overall': ['لا صف هنا'],
          students: ['اكتب سطرًا لكل طالب في الحصة.'],
          'items.0.outcome': ['اختر النتيجة.'],
        },
        rows,
      ),
    ).toEqual({
      a: { engagement: 'اختر التركيز.' },
      b: { absence_note: 'اكتب ملاحظة الغياب.' },
      c: { notes_for_student: 'طويلة جدًا.' },
    });
  });
});

describe('the reports list', () => {
  const report = (id: string, session: string, change: Partial<Parameters<typeof reportRows>[0][number]> = {}) => ({
    id,
    session_id: session,
    kind: 'lesson' as const,
    status: 'due' as const,
    student: { id: `s-${id}`, name: `طالب ${id}` },
    late: false,
    format: 'private' as const,
    group_id: null,
    ...change,
  });

  it('keeps a one-to-one report a line of its own', () => {
    const rows = reportRows([report('a', 'x'), report('b', 'y')]);
    expect(rows.map((r) => [r.report.id, r.students])).toEqual([
      ['a', null],
      ['b', null],
    ]);
  });

  it('makes a group lesson one line that opens a student who came, the most pressing state and everyone in it', () => {
    const g = { format: 'group' as const, group_id: 'g1' };
    const rows = reportRows([
      report('p', 'x'),
      report('a', 'gs', { ...g, kind: 'absence' }),
      report('b', 'gs', { ...g, status: 'overdue', late: true }),
      report('c', 'gs', g),
      report('q', 'y'),
    ]);
    expect(rows).toHaveLength(3);
    expect(rows[1]).toMatchObject({ report: { id: 'b' }, status: 'overdue', late: true });
    expect(rows[1]?.students?.map((s) => s.id)).toEqual(['s-a', 's-b', 's-c']);
    expect(rows.map((r) => r.report.id)).toEqual(['p', 'b', 'q']);
  });
});
