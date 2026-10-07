import next from 'eslint-config-next';

const config = [
  { ignores: ['.next/**', 'node_modules/**', 'src/lib/api/schema.ts', 'playwright-report/**', 'test-results/**'] },
  ...next,
  {
    // The catch-all [locale]/[...rest] page makes the rule take every path for a page of
    // this app; /admin and /my are Laravel's panels, reached on purpose with a plain link.
    rules: { '@next/next/no-html-link-for-pages': 'off' },
  },
];

export default config;
