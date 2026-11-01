import { createElement, type ComponentProps, type ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { NextIntlClientProvider } from 'next-intl';
import { describe, expect, it } from 'vitest';
import ar from '../../../messages/ar.json';
import type { VerifiedCertificate } from '@/lib/quran/types';
import { CertificateCheck, CertificateMissing } from './certificate';

function html(element: ReactElement): string {
  return renderToStaticMarkup(createElement(NextIntlClientProvider, { locale: 'ar', messages: ar, timeZone: 'UTC' } as unknown as ComponentProps<typeof NextIntlClientProvider>, element));
}

const found: VerifiedCertificate = {
  number: 'Q-2026-000123', title: 'شهادة حفظ جزء عمّ', portion: { kind: 'juz', number: 30, label: 'الجزء ٣٠' }, riwaya: 'حفص عن عاصم',
  grade: 'excellent', grade_label: 'ممتاز', issued_at: { utc: '', local: '', timezone: null, label: '١ نوفمبر ٢٠٢٦' },
  academy: 'أكاديمية النور', student: 'زياد م.', revoked: false, revoked_at: null,
};

describe('a certificate’s public check', () => {
  it('says what it is for, for whom (first name and initial), and that it stands', () => {
    const shown = html(createElement(CertificateCheck, { certificate: found, code: 'ABCD-EFGH-JKMN-PQRS' }));
    for (const part of ['Q-2026-000123', 'شهادة حفظ جزء عمّ', 'الجزء ٣٠', 'حفص عن عاصم', 'ممتاز', '١ نوفمبر ٢٠٢٦', 'أكاديمية النور', 'زياد م.', 'شهادة صحيحة', 'ABCD-EFGH-JKMN-PQRS']) {
      expect(shown).toContain(part);
    }
  });

  it('says plainly when it was revoked', () => {
    const shown = html(createElement(CertificateCheck, { certificate: { ...found, revoked: true, revoked_at: { utc: '', local: '', timezone: null, label: '٣ نوفمبر' } }, code: 'ABCD-EFGH-JKMN-PQRS' }));
    expect(shown).toContain('أُلغيت هذه الشهادة في ٣ نوفمبر');
    expect(shown).not.toContain('شهادة صحيحة');
  });

  it('has its own "not found"', () => {
    expect(html(createElement(CertificateMissing))).toContain('لم نجد هذه الشهادة');
  });
});
