import type { NextConfig } from 'next';
import createNextIntlPlugin from 'next-intl/plugin';

const withNextIntl = createNextIntlPlugin('./src/i18n/request.ts');

const config: NextConfig = {
  // One self-contained server for the production image (infra/deploy).
  output: 'standalone',
  poweredByHeader: false,
  // Notes for coding agents live in the repository's CLAUDE.md, not in a file next dev writes.
  agentRules: false,
  reactStrictMode: true,
  // Recordings handed in from the portal go through a Server Action.
  experimental: { serverActions: { bodySizeLimit: '50mb' } },
};

export default withNextIntl(config);
