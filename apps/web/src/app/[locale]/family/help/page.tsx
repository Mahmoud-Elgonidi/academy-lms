import { HelpIndex } from '@/components/support/help-pages';
import { family } from '@/lib/family/context';

/** "Help": writing to the academy about the learner on screen, and following the answers. */
export default async function Help() {
  const fam = await family();
  return <HelpIndex base="/family/help" side="family" learnerId={fam.current?.id} />;
}
