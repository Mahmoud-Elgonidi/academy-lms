import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { notFound } from 'next/navigation';
import { CertificateCheck } from '@/components/site/certificate';
import { call } from '@/lib/api/server';
import { cleanCode } from '@/lib/quran/quran';
import type { VerifiedCertificate } from '@/lib/quran/types';

type Props = { params: Promise<{ locale: string; code: string }> };

/** The certificate by its code (GET quran/certificates/{code}), or null: never issued here, the Quran off, or the API not answering. */
async function verified(code: string): Promise<VerifiedCertificate | null> {
  const clean = cleanCode(code);
  if (!clean) return null;
  try {
    const response = await call('get', '/v1/quran/certificates/{code}', { params: { code: clean } });
    if (!response.ok) return null;
    return ((await response.json()) as { data: VerifiedCertificate }).data;
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const t = await getTranslations('certificate');
  return { title: t('pageTitle'), robots: { index: false, follow: false } };
}

/**
 * Anyone checks a Quran certificate by the code printed on it (its QR leads
 * here, on the academy's address): its number, title, portion, riwaya,
 * grade and day, the academy, the student's first name and last initial,
 * and whether it was revoked. A code this academy never issued is "not
 * found" (404). Server-rendered, in the academy's colours (components/site).
 */
export default async function CertificatePage({ params }: Props) {
  const { code } = await params;
  const found = await verified(code);
  if (!found) notFound();
  return <CertificateCheck certificate={found} code={cleanCode(code)!} />;
}
