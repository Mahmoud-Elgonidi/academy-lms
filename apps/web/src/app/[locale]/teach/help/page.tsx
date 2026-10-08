import { HelpIndex } from '@/components/support/help-pages';

/** "Support": a teacher writes to the academy (technical, pay, anything else) and follows the answers. */
export default async function TeacherHelp() {
  return <HelpIndex base="/teach/help" side="teacher" />;
}
