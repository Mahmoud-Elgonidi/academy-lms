import { ConcernPage } from '@/components/safeguarding/concern-page';

/** "Raise a concern": a teacher tells the academy's safeguarding leads what worries them about a student. */
export default async function RaiseConcernPage() {
  return <ConcernPage side="teacher" />;
}
