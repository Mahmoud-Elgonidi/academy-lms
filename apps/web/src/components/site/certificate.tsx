import { useTranslations } from 'next-intl';
import type { VerifiedCertificate } from '@/lib/quran/types';
import { Icon } from './icons';
import './site.css';
import './certificate.css';

/** A Quran certificate as anyone verifies it: who it is for (first name and initial), what, how well, when, and whether it still stands. */
export function CertificateCheck({ certificate: c, code }: { certificate: VerifiedCertificate; code: string }) {
  const t = useTranslations('certificate');
  return (
    <main className="ls ls-academy ls-cert-page">
      <article className="ls-cert" data-certificate={c.number} data-revoked={c.revoked ? '' : undefined}>
        <header className="ls-cert-head">
          <span className="ls-cert-seal" aria-hidden="true">
            <Icon name="shield" />
          </span>
          <div>
            <p className="ls-cert-academy">{c.academy}</p>
            <p className="ls-cert-kicker">{t('kicker')}</p>
          </div>
        </header>
        <div className="ls-cert-body">
          {c.revoked ? (
            <p className="ls-cert-state" data-revoked="">
              {c.revoked_at ? t('revokedOn', { when: c.revoked_at.label }) : t('revoked')}
            </p>
          ) : (
            <p className="ls-cert-state" data-valid="">
              {t('valid')}
            </p>
          )}
          <h1 className="ls-cert-title">{c.title}</h1>
          <p className="ls-cert-student">{t('awardedTo', { name: c.student })}</p>
          <dl className="ls-cert-facts">
            <div>
              <dt>{t('portion')}</dt>
              <dd>{c.portion.label}</dd>
            </div>
            <div>
              <dt>{t('riwaya')}</dt>
              <dd>{c.riwaya}</dd>
            </div>
            <div>
              <dt>{t('grade')}</dt>
              <dd>{c.grade_label}</dd>
            </div>
            <div>
              <dt>{t('issued')}</dt>
              <dd>{c.issued_at?.label ?? '—'}</dd>
            </div>
            <div>
              <dt>{t('number')}</dt>
              <dd dir="ltr">{c.number}</dd>
            </div>
            <div>
              <dt>{t('code')}</dt>
              <dd dir="ltr">{code}</dd>
            </div>
          </dl>
          <p className="ls-cert-note">{c.revoked ? t('revokedNote') : t('validNote')}</p>
        </div>
      </article>
    </main>
  );
}

/** A code this academy never issued (the segment's "not found", a 404): said calmly, in the same look. */
export function CertificateMissing() {
  const t = useTranslations('certificate');
  return (
    <main className="ls ls-academy ls-cert-page">
      <article className="ls-cert ls-cert-missing" data-certificate-missing="">
        <span className="ls-cert-seal" style={{ marginInline: 'auto' }} aria-hidden="true">
          <Icon name="shield" />
        </span>
        <h1>{t('missingTitle')}</h1>
        <p>{t('missingLead')}</p>
      </article>
    </main>
  );
}
