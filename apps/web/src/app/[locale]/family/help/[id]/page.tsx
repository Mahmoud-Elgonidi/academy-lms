import { HelpTicket } from '@/components/support/help-pages';

/** One of the person's tickets: the conversation, answering, reopening, closing. */
export default async function HelpTicketPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <HelpTicket base="/family/help" id={id} />;
}
