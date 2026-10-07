import type { ReactNode } from 'react';

// The document is drawn by [locale]/layout.tsx, which knows the language and direction.
export default function RootLayout({ children }: { children: ReactNode }) {
  return children;
}
