import { ConcernPage } from '@/components/safeguarding/concern-page';
import { family } from '@/lib/family/context';

/** "Tell us": telling the academy's safeguarding leads that something is wrong, about the learner on screen; in every look. */
export default async function TellUs() {
  const fam = await family();
  return <ConcernPage side="family" learnerId={fam.current?.id} />;
}
