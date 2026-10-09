import type { NextConfig } from 'next';
import createNextIntlPlugin from 'next-intl/plugin';

const withNextIntl = createNextIntlPlugin('./src/i18n/request.ts');

// The same security headers as the API's (apps/api/modules/SharedKernel/src/Http/SecurityHeaders.php).
const securityHeaders = [
  { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
  { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
  { key: 'Content-Security-Policy', value: "base-uri 'self'; object-src 'none'; frame-ancestors 'self'" },
  { key: 'Permissions-Policy', value: 'geolocation=(), usb=(), serial=(), hid=(), bluetooth=()' },
];

const config: NextConfig = {
  // One self-contained server for the production image (infra/deploy).
  output: 'standalone',
  poweredByHeader: false,
  // Notes for coding agents live in the repository's CLAUDE.md, not in a file next dev writes.
  agentRules: false,
  reactStrictMode: true,
  // Recordings handed in from the portal go through a Server Action.
  experimental: { serverActions: { bodySizeLimit: '50mb' } },
  async headers() {
    return [
      { source: '/:path*', headers: securityHeaders },
      // Over https (the server's nginx says so): the browser keeps to https on this host for half a year.
      {
        source: '/:path*',
        has: [{ type: 'header', key: 'x-forwarded-proto', value: 'https' }],
        headers: [{ key: 'Strict-Transport-Security', value: 'max-age=15552000' }],
      },
    ];
  },
};

export default withNextIntl(config);
