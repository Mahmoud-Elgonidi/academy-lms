import { HelpTicket } from '@/components/support/help-pages';

/** One of the teacher's tickets. */
export default async function TeacherHelpTicket({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <HelpTicket base="/teach/help" id={id} />;
}
