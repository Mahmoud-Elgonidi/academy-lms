import { ChatConversation } from '@/components/messages/chat-pages';

/** One conversation with a teacher (whoever's learner it is about: the API checks the reader is in it). */
export default async function FamilyConversation({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ChatConversation base="/family/messages" id={id} />;
}
