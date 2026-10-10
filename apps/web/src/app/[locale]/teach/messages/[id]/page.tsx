import { ChatConversation } from '@/components/messages/chat-pages';

/** One conversation with a student and their family. */
export default async function TeacherConversation({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ChatConversation base="/teach/messages" id={id} />;
}
