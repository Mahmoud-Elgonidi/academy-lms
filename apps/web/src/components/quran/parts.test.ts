import { createElement, type ComponentProps, type ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { NextIntlClientProvider } from 'next-intl';
import { describe, expect, it } from 'vitest';
import ar from '../../../messages/ar.json';
import { juzStrip } from '@/lib/quran/quran';
import type { Certificate, Exam, LessonResult, Plan } from '@/lib/quran/types';
import { CertificateList, ExamList, JuzStrip, PlanProgress, PortionCards, RecentResults } from './parts';

/** A component as the page writes it, in Arabic. */
function html(element: ReactElement): string {
  return renderToStaticMarkup(createElement(NextIntlClientProvider, { locale: 'ar', messages: ar, timeZone: 'UTC' } as unknown as ComponentProps<typeof NextIntlClientProvider>, element));
}

const range = { surah: 78, from: 1, to: 16, title: 'النبأ ١–١٦' };
const plan: Plan = {
  id: 'p1', enrollment_id: 'e1', group_id: null,
  program: { id: 'g', name: 'جزء عمّ', track: 'hifz', track_label: 'حفظ', direction: 'backward', unit: 'verses', unit_label: 'آيات', pass_grade: 'good', exam_stops: 'surah' },
  pace: { new_amount: 5, near_portions: 2, far_pages: 2 },
  position: { surah: 78, verse: 17, title: 'النبأ ١٧' }, finished: false, frozen: null, frozen_label: null, progress: 0.42,
  portion: { new: { label: 'New', ranges: [range], pages: [582] }, near: { label: 'Near review', ranges: [{ ...range, surah: 114, from: 1, to: 6, title: 'الناس ١–٦' }], pages: [604] }, far: null, lesson: null, stops: [{ kind: 'surah', number: 78, label: 'سورة النبأ' }], finished: false },
};
const result: LessonResult = {
  id: 'r1', session_id: 's1', student_id: 'u1', advanced: true, frozen: false, recorded_at: { utc: '', local: '', timezone: null, label: 'الأحد ١ نوفمبر' },
  parts: { new: { label: 'New', ranges: [range], lesson: null, grade: 'very_good', grade_label: 'جيد جدًا', mistakes: [{ kind: 'wrong', label: 'خطأ جلي', count: 2 }], note: 'تحسّن واضح' } },
};

describe('the Quran’s pieces', () => {
  it('shows how far along as a percent, and to a child as stars with no number', () => {
    expect(html(createElement(PlanProgress, { plan }))).toContain('42٪');
    const kids = html(createElement(PlanProgress, { plan, kids: true }));
    expect(kids).toContain('data-stars="2"');
    expect(kids).not.toContain('42');
  });

  it('draws the thirty parts, numbered except in the kids’ look', () => {
    const strip = juzStrip([{ juz: 30, pages: [{ page: 582, strength: 'strong' }] }]);
    const shown = html(createElement(JuzStrip, { strip }));
    expect(shown.match(/data-juz="/g)).toHaveLength(30);
    expect(shown).toContain('اكتمل جزء واحد من ٣٠');
    expect(html(createElement(JuzStrip, { strip, kids: true }))).not.toContain('من ٣٠');
  });

  it('gives a portion’s parts as cards with their passages, pages only for grown-ups', () => {
    const shown = html(createElement(PortionCards, { portion: plan.portion! }));
    expect(shown).toContain('data-part="new"');
    expect(shown).toContain('data-part="near"');
    expect(shown).not.toContain('data-part="far"');
    expect(shown).toContain('النبأ ١–١٦');
    expect(shown).toContain('الصفحات: 582');
    expect(shown).toContain('سورة النبأ');
    expect(html(createElement(PortionCards, { portion: plan.portion!, kids: true }))).not.toContain('الصفحات');
  });

  it('shows the teacher their note and the mistakes, a child neither', () => {
    const teacher = html(createElement(RecentResults, { results: [result], teacher: true }));
    expect(teacher).toContain('تحسّن واضح');
    expect(teacher).toContain('خطأ جلي: 2');
    const kids = html(createElement(RecentResults, { results: [result], kids: true }));
    expect(kids).not.toContain('تحسّن واضح');
    expect(kids).not.toContain('خطأ جلي');
    expect(kids).toContain('جيد جدًا');
  });

  it('lists exams with their result and the examiner’s words', () => {
    const exam: Exam = {
      id: 'x', student_id: 'u1', student: null, enrollment_id: 'e1', program: { id: 'g', name: 'جزء عمّ' }, stop: { kind: 'surah', number: 78, label: 'سورة النبأ' },
      status: 'failed', status_label: 'لم ينجح', retake: false, not_before: null, examiner: null, lesson: null,
      result: { grade: 'acceptable', grade_label: 'مقبول', passed: false, mistakes: [], note: 'راجع المدود', retake_on: '2026-11-20', recorded_at: null },
    };
    const shown = html(createElement(ExamList, { exams: [exam] }));
    expect(shown).toContain('data-exam="failed"');
    expect(shown).toContain('راجع المدود');
    expect(shown).toContain('موعد الإعادة المقترح');
  });

  it('gives a standing certificate its page and verification, a revoked one neither', () => {
    const base: Certificate = {
      id: 'c', number: 'Q-2026-000001', title: 'شهادة حفظ جزء عمّ', portion: { kind: 'juz', number: 30, label: 'الجزء ٣٠' }, riwaya: 'حفص عن عاصم',
      grade: 'excellent', grade_label: 'ممتاز', examiner: null, issued_at: null, revoked: false, url: 'https://demo.example/c', code: 'ABCD-EFGH-JKMN-PQRS',
    };
    const standing = html(createElement(CertificateList, { certificates: [base], verifyHref: (code: string) => `/ar/certificates/${code}` }));
    expect(standing).toContain('href="/ar/certificates/ABCD-EFGH-JKMN-PQRS"');
    expect(standing).toContain('href="https://demo.example/c"');
    const revoked = html(createElement(CertificateList, { certificates: [{ ...base, revoked: true, url: null, code: null }], verifyHref: () => '' }));
    expect(revoked).toContain('أُلغيت');
    expect(revoked).not.toContain('/certificates/');
  });
});
