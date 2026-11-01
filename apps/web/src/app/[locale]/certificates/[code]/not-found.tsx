import { CertificateMissing } from '@/components/site/certificate';

/** A code this academy never issued: a 404 in the certificate's own look. */
export default function NotFound() {
  return <CertificateMissing />;
}
