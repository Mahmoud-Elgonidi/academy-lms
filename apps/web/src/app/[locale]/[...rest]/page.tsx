import { notFound } from 'next/navigation';

// Any other path under a locale: the locale's own "not found" page, in its language and look.
export default function Rest() {
  notFound();
}
